<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\types\Lookup;
use dokuwiki\plugin\struct\types\Media;
use dokuwiki\plugin\struct\types\Page;

class action_plugin_struct_move extends ActionPlugin
{
    /** @var helper_plugin_sqlite */
    protected $db;

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PLUGIN_MOVE_PAGE_RENAME', 'AFTER', $this, 'handleMove', true);
        $controller->register_hook('PLUGIN_MOVE_MEDIA_RENAME', 'AFTER', $this, 'handleMove', false);
    }

    /**
     * Renames all occurrences of a page ID in the database
     *
     * @param Event $event event object by reference
     * @param bool $ispage is this a page move operation?
     * @return bool
     */
    public function handleMove(Event $event, $ispage)
    {
        /** @var helper_plugin_struct_db $hlp */
        $hlp = plugin_load('helper', 'struct_db');
        $this->db = $hlp->getDB(false);
        if (!$this->db instanceof SQLiteDB) return false;
        $old = $event->data['src_id'];
        $new = $event->data['dst_id'];

        // prepare work
        $this->db->query('BEGIN TRANSACTION');

        // general update of our meta tables
        if ($ispage) {
            $this->updateDataTablePIDs($old, $new);
            $this->updateAssignments($old, $new);
            $this->updateTitles($old, $new);
        }

        // apply updates to all columns in all schemas depending on type
        $schemas = Schema::getAll();
        foreach ($schemas as $table) {
            $schema = new Schema($table);
            foreach ($schema->getColumns() as $col) {
                if ($ispage) {
                    if (get_class($col->getType()) == Page::class) {
                        $this->updateColumnID($schema, $col, $old, $new, true);
                    } elseif (get_class($col->getType()) == Lookup::class) {
                        $this->updateColumnLookup($schema, $col, $old, $new);
                    }
                } elseif ($col->getType() instanceof Media) {
                    $this->updateColumnID($schema, $col, $old, $new);
                }
            }
        }

        // execute everything
        $ok = $this->db->query('COMMIT TRANSACTION');
        if (!$ok) {
            $this->db->query('ROLLBACK TRANSACTION');
            return false;
        }

        return true;
    }

    /**
     * Update the pid column of ALL data tables
     *
     * (we don't trust the assigments are still there)
     *
     * @param string $old old page id
     * @param string $new new page id
     */
    protected function updateDataTablePIDs($old, $new)
    {
        foreach (Schema::getAll() as $tbl) {
            /** @noinspection SqlResolve */
            $sql = "UPDATE data_$tbl SET pid = ? WHERE pid = ?";
            $this->db->query($sql, [$new, $old]);

            /** @noinspection SqlResolve */
            $sql = "UPDATE multi_$tbl SET pid = ? WHERE pid = ?";
            $this->db->query($sql, [$new, $old]);
        }
    }

    /**
     * Update the page-schema assignments
     *
     * @param string $old old page id
     * @param string $new new page id
     */
    protected function updateAssignments($old, $new)
    {
        // assignments
        $sql = "UPDATE schema_assignments SET pid = ? WHERE pid = ?";
        $this->db->query($sql, [$new, $old]);
        // make sure assignments still match patterns;
        $assignments = Assignments::getInstance();
        $assignments->reevaluatePageAssignments($new);
    }

    /**
     * Update the Title information for the moved page
     *
     * @param string $old old page id
     * @param string $new new page id
     */
    protected function updateTitles($old, $new)
    {
        $sql = "UPDATE titles SET pid = ? WHERE pid = ?";
        $this->db->query($sql, [$new, $old]);
    }

    /**
     * Update the ID in a given column
     *
     * @param Schema $schema
     * @param Column $col
     * @param string $old old page id
     * @param string $new new page id
     * @param bool $hashes could the ID have a hash part? (for Page type)
     */
    protected function updateColumnID(Schema $schema, Column $col, $old, $new, $hashes = false)
    {
        $colref = $col->getColref();
        $table = $schema->getTable();

        if ($col->isMulti()) {
            /** @noinspection SqlResolve */
            $sql = "UPDATE multi_$table
                               SET value = REPLACE(value, ?, ?)
                             WHERE value LIKE ?
                               AND colref = $colref
                               AND latest = 1";
        } else {
            /** @noinspection SqlResolve */
            $sql = "UPDATE data_$table
                               SET col$colref = REPLACE(col$colref, ?, ?)
                             WHERE col$colref LIKE ?
                               AND latest = 1";
        }
        $this->db->query($sql, [$old, $new, $old]); // exact match
        if ($hashes) {
            $this->db->query($sql, [$old, $new, "$old#%"]); // match with hashes
        }
        if ($col->getType() instanceof Lookup) {
            $this->db->query($sql, [$old, $new, "[\"$old\",%]"]); // match JSON string
            if ($hashes) {
                $this->db->query($sql, [$old, $new, "[\"$old#%\",%]"]); // match JSON string with hash
            }
        }
    }

    /**
     * Update a Lookup type column
     *
     * Lookups contain a page id when the referenced schema is a data schema
     *
     * @param Schema $schema
     * @param Column $col
     * @param string $old old page id
     * @param string $new new page id
     */
    protected function updateColumnLookup(Schema $schema, Column $col, $old, $new)
    {
        $tconf = $col->getType()->getConfig();
        $ref = new Schema($tconf['schema']);
        if (!$ref->getId()) return; // this schema does not exist
        if (!$ref->getTimeStamp()) return; // a lookup is referenced, nothing to do
        $this->updateColumnID($schema, $col, $old, $new);
    }
}
