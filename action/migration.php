<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\sqlite\SQLiteDB;

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */
/**
 * Class action_plugin_struct_migration
 *
 * Handle migrations that need more than just SQL
 */
class action_plugin_struct_migration extends ActionPlugin
{
    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_SQLITE_DATABASE_UPGRADE', 'BEFORE', $this, 'handleMigrations');
    }

    /**
     * Call our custom migrations when defined
     *
     * @param Event $event
     * @param $param
     */
    public function handleMigrations(Event $event, $param)
    {
        if ($event->data['adapter']->getDbname() !== 'struct') {
            return;
        }
        $to = $event->data['to'];

        if (is_callable([$this, "migration$to"])) {
            $event->preventDefault();
            $event->result = call_user_func([$this, "migration$to"], $event->data['adapter']);
        }
    }

    /**
     * Executes Migration 12
     *
     * Add a latest column to all existing multi tables
     *
     * @param SQLiteDB $sqlite
     * @return bool
     */
    protected function migration12($sqlite)
    {
        /** @noinspection SqlResolve */
        $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'multi_%'";
        $tables = $sqlite->queryAll($sql);

        foreach ($tables as $row) {
            $table = $row['name']; // no escaping needed, it's our own tables
            $sql = "ALTER TABLE $table ADD COLUMN latest INT DEFAULT 1";
            $sqlite->query($sql);
        }

        return true;
    }

    /**
     * Executes Migration 16
     *
     * Unifies previous page and lookup schema types
     *
     * @param SQLiteDB $sqlite
     * @return bool
     */
    protected function migration16($sqlite)
    {
        // get tables and their SQL definitions
        $sql = "SELECT sql, name FROM sqlite_master
                WHERE type = 'table'
                AND (name LIKE 'data_%' OR name LIKE 'multi_%')";
        $tables = $sqlite->queryAll($sql);

        // get latest versions of schemas with islookup property
        $sql = "SELECT MAX(id) AS id, tbl, islookup FROM schemas
                    GROUP BY tbl
            ";
        $schemas = $sqlite->queryAll($sql);

        $ok = true;

        // Step 1: move original data to temporary tables and create new ones with modified schemas
        foreach ($tables as $table) {
            $name = $table['name'];
            $sql = $table['sql'];

            // move original data to temp_*
            $ok = $ok && $sqlite->query("ALTER TABLE $name RENAME TO temp_$name");

            // update pid definitions
            $sql = preg_replace('/pid (\w* ?NOT NULL|\w* ?PRIMARY KEY)/', 'pid TEXT DEFAULT ""', $sql);

            // add rid and new primary key to regular tables
            $cnt = 0;
            $sql = preg_replace('/(PRIMARY KEY ?\([^\)]+?)(\))/', ' rid INTEGER, $1, rid $2', $sql, -1, $cnt);
            // add rid and new primary key to lookup tables
            if (!$cnt) {
                $sql = str_replace(')', ', rid INTEGER, PRIMARY KEY(pid,rid) )', $sql);
            }

            // create the new table
            $ok = $ok && $sqlite->query($sql);
            if (!$ok) return false;
        }

        // Step 2: transfer data back from original tables (temp_*)
        foreach ($schemas as $schema) {
            $name = $schema['tbl'];
            $sid = $schema['id'];
            $isLookup = $schema['islookup'];

            if (!$isLookup) {
                $s = sprintf('INSERT INTO data_%s SELECT *, 0 FROM temp_data_%s', $name, $name);
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;

                $s = sprintf('INSERT INTO multi_%s SELECT *, 0 FROM temp_multi_%s', $name, $name);
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;
            } else {
                // transfer pid to rid
                $s = sprintf('INSERT INTO data_%s SELECT *, pid FROM temp_data_%s', $name, $name);
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;

                $s = sprintf('INSERT INTO multi_%s SELECT *, pid FROM temp_multi_%s', $name, $name);
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;

                // all lookup data has empty pids at this point
                $s = "UPDATE data_$name SET pid = ''";
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;

                $s = "UPDATE multi_$name SET pid = ''";
                $ok = $ok && $sqlite->query($s);
                if (!$ok) return false;
            }

            // introduce composite ids in lookup columns
            $s = $this->getLookupColsSql($sid);
            $cols = $sqlite->queryAll($s);

            if ($cols) {
                foreach ($cols as $col) {
                    $colno = $col['COL'];
                    $colname = "col$colno";
                    // lookup fields pointing to pages have to be migrated first!
                    // they rely on a simplistic not-a-number check, and already migrated lookups pass the test!
                    $f = 'UPDATE data_%s
                             SET %s = \'["\'||%s||\'",0]\'
                           WHERE %s != \'\'
                             AND CAST(%s AS DECIMAL) != %s';
                    $s = sprintf($f, $name, $colname, $colname, $colname, $colname, $colname);
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;
                    // multi_
                    $f = 'UPDATE multi_%s
                             SET value = \'["\'||value||\'",0]\'
                           WHERE colref = %s
                             AND CAST(value AS DECIMAL) != value';
                    $s = sprintf($f, $name, $colno);
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;

                    // simple lookup fields
                    $s = "UPDATE data_$name
                             SET col$colno = '[" . '""' . ",'||col$colno||']'
                           WHERE col$colno != ''
                             AND CAST(col$colno AS DECIMAL) = col$colno";
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;
                    // multi_
                    $s = "UPDATE multi_$name
                             SET value = '[" . '""' . ",'||value||']'
                           WHERE colref=$colno
                             AND CAST(value AS DECIMAL) = value";
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;
                }
            }
        }

        // Step 3: delete temp_* tables
        foreach ($tables as $table) {
            $name = $table['name'];
            $s = "DROP TABLE temp_$name";
            $ok = $ok && $sqlite->query($s);
            if (!$ok) return false;
        }

        // Step 4: remove islookup in schemas table
        $sql = "SELECT sql FROM sqlite_master
                WHERE type = 'table'
                AND name = 'schemas'";
        $t = $sqlite->queryAll($sql);
        $sql = $t[0]['sql'];
        $sql = str_replace('islookup INTEGER,', '', $sql);

        $s = 'ALTER TABLE schemas RENAME TO temp_schemas';
        $ok = $ok && $sqlite->query($s);
        if (!$ok) return false;

        // create a new table without islookup
        $ok = $ok && $sqlite->query($sql);
        if (!$ok) return false;

        $s = 'INSERT INTO schemas SELECT id, tbl, ts, user, comment, config FROM temp_schemas';
        $ok = $ok && $sqlite->query($s);

        return $ok;
    }

    /**
     * Executes Migration 17
     *
     * Fixes lookup data not correctly migrated by #16
     * All lookups were presumed to reference lookup data, not pages, so the migrated value
     * was always ["", <previous-pid-aka-new-rid>]. For page references it is ["<previous-pid>", 0]
     *
     * @param SQLiteDB $sqlite
     * @return bool
     */
    protected function migration17($sqlite)
    {
        $sql = "SELECT MAX(id) AS id, tbl FROM schemas
                    GROUP BY tbl
            ";
        $schemas = $sqlite->queryAll($sql);

        $ok = true;

        foreach ($schemas as $schema) {
            // find lookup columns
            $name = $schema['tbl'];
            $sid = $schema['id'];
            $s = $this->getLookupColsSql($sid);
            $cols = $sqlite->queryAll($s);

            if ($cols) {
                $colnames = array_map(static fn($c) => 'col' . $c['COL'], $cols);

                // data_ tables
                $s = 'SELECT pid, rid, rev, ' . implode(', ', $colnames) . " FROM data_$name";
                $allValues = $sqlite->queryAll($s);

                if (!empty($allValues)) {
                    foreach ($allValues as $row) {
                        [$pid, $rid, $rev, $colref, $rowno, $fixes] = $this->getFixedValues($row);
                        // now fix the values
                        if (!empty($fixes)) {
                            $sql = "UPDATE data_$name
                                       SET " . implode(', ', $fixes) . "
                                     WHERE pid = ?
                                       AND rid = ?
                                       AND rev = ?";
                            $params = [$pid, $rid, $rev];
                            $ok = $ok && $sqlite->query($sql, $params);
                        }
                    }
                }

                // multi_ tables
                $s = "SELECT colref, pid, rid, rev, row, value FROM multi_$name";
                $allValues = $sqlite->queryAll($s);

                if (!empty($allValues)) {
                    foreach ($allValues as $row) {
                        [$pid, $rid, $rev, $colref, $rowno, $fixes] = $this->getFixedValues($row);
                        // now fix the values
                        if (!empty($fixes)) {
                            $sql = "UPDATE multi_$name
                                       SET " . implode(', ', $fixes) . "
                                     WHERE pid = ?
                                       AND rid = ?
                                       AND rev = ?
                                       AND colref = ?
                                       AND row = ?";
                            $params = [$pid, $rid, $rev, $colref, $rowno];
                            $ok = $ok && $sqlite->query($sql, $params);
                        }
                    }
                }
            }
        }

        return $ok;
    }

    /**
     * Removes a temp table left over by migration 16
     *
     * @param SQLiteDB $sqlite
     * @return bool
     */
    protected function migration18($sqlite)
    {
        $ok = true;

        $sql = 'DROP TABLE IF EXISTS temp_schemas';
        $ok = $ok && $sqlite->query($sql);

        return $ok;
    }

    /**
     * Executes Migration 19
     *
     * Add "published" column to all existing tables
     *
     * @param SQLiteDB $sqlite
     * @return bool
     */
    protected function migration19($sqlite)
    {
        $ok = true;

        /** @noinspection SqlResolve */
        $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND (name LIKE 'data_%' OR name LIKE 'multi_%')";
        $tables = $sqlite->queryAll($sql);

        foreach ($tables as $row) {
            $table = $row['name']; // no escaping needed, it's our own tables
            $sql = "ALTER TABLE $table ADD COLUMN published INT DEFAULT NULL";
            $ok = $ok && $sqlite->query($sql);
        }
        return $ok;
    }


    /**
     * Returns a select statement to fetch Lookup columns in the current schema
     *
     * @param int $sid Id of the schema
     * @return string SQL statement
     */
    protected function getLookupColsSql($sid)
    {
        return "SELECT C.colref AS COL, T.class AS TYPE
                FROM schema_cols AS C
                LEFT OUTER JOIN types AS T
                    ON C.tid = T.id
                WHERE C.sid = $sid
                AND TYPE LIKE '%Lookup'
            ";
    }

    /**
     * Checks for improperly migrated values and returns an array with
     * "<column> = <fixed-value>" fragments to be used in the UPDATE statement.
     *
     * @param array $row
     * @return array
     */
    protected function getFixedValues($row)
    {
        $pid = $row['pid'];
        $rid = $row['rid'];
        $rev = $row['rev'];
        $colref = $row['colref'];
        $rowno = $row['row'];
        $fixes = [];
        $matches = [];

        // check if anything needs to be fixed in data columns
        foreach ($row as $col => $value) {
            if (in_array($col, ['pid', 'rid', 'rev', 'colref', 'row'])) {
                continue;
            }
            preg_match('/^\["",(?<pid>.*?\D+.*?)\]$/', $value, $matches);
            if (!empty($matches['pid'])) {
                $fixes[$col] = '["' . $matches['pid'] . '",0]';
            }
        }

        if (!empty($fixes)) {
            $fixes = array_map(static fn($set, $key) => "$key = '$set'", $fixes, array_keys($fixes));
        }

        return [$pid, $rid, $rev, $colref, $rowno, $fixes];
    }
}
