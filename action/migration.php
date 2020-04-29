<?php

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
class action_plugin_struct_migration extends DokuWiki_Action_Plugin
{
    /**
     * @inheritDoc
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PLUGIN_SQLITE_DATABASE_UPGRADE', 'BEFORE', $this, 'handleMigrations');
    }

    /**
     * Call our custom migrations when defined
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handleMigrations(Doku_Event $event, $param)
    {
        if ($event->data['sqlite']->getAdapter()->getDbname() !== 'struct') {
            return;
        }
        $to = $event->data['to'];

        if (is_callable(array($this, "migration$to"))) {
            $event->preventDefault();
            $event->result = call_user_func(array($this, "migration$to"), $event->data['sqlite']);
        }
    }

    /**
     * Executes Migration 12
     *
     * Add a latest column to all existing multi tables
     *
     * @param helper_plugin_sqlite $sqlite
     * @return bool
     */
    protected function migration12(helper_plugin_sqlite $sqlite)
    {
        /** @noinspection SqlResolve */
        $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'multi_%'";
        $res = $sqlite->query($sql);
        $tables = $sqlite->res2arr($res);
        $sqlite->res_close($res);

        foreach ($tables as $row) {
            $sql = 'ALTER TABLE ? ADD COLUMN latest INT DEFAULT 1';
            $sqlite->query($sql, $row['name']);
        }

        return true;
    }

    /**
     * Executes Migration 16
     *
     * Unifies previous page and lookup schema types
     *
     * @param helper_plugin_sqlite $sqlite
     * @return bool
     */
    protected function migration16(helper_plugin_sqlite $sqlite)
    {
        // get tables and their SQL definitions
        $sql = "SELECT sql, name FROM sqlite_master
                WHERE type = 'table'
                AND (name LIKE 'data_%' OR name LIKE 'multi_%')";
        $res = $sqlite->query($sql);
        $tables = $sqlite->res2arr($res);
        $sqlite->res_close($res);

        // get latest versions of schemas with islookup property
        $sql = "SELECT MAX(id) AS id, tbl, islookup FROM schemas
                    GROUP BY tbl
            ";
        $res = $sqlite->query($sql);
        $schemas = $sqlite->res2arr($res);

        $sqlite->query('BEGIN TRANSACTION');
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
            $s = "SELECT C.colref AS COL, T.class AS TYPE
                FROM schema_cols AS C
                LEFT OUTER JOIN types AS T
                    ON C.tid = T.id
                WHERE C.sid = $sid
                AND TYPE = 'Lookup'
            ";
            $res = $sqlite->query($s);
            $cols = $sqlite->res2arr($res);

            if ($cols) {
                foreach ($cols as $col) {
                    $colno = $col['COL'];
                    $s = "UPDATE data_$name SET col$colno = '[" . '""' . ",'||col$colno||']' WHERE col$colno != ''";
                    $ok = $ok && $sqlite->query($s);
                    if (!$ok) return false;

                    // multi_
                    $s = "UPDATE multi_$name SET value = '[" . '""' . ",'||value||']' WHERE colref=$colno";
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
        $res = $sqlite->query($sql);
        $t = $sqlite->res2arr($res);
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

        if (!$ok) {
            $sqlite->query('ROLLBACK TRANSACTION');
            return false;
        }
        $sqlite->query('COMMIT TRANSACTION');
        return true;
    }
}
