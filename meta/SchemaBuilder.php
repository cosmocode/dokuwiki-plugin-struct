<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\Utf8\PhpString;

/**
 * Class SchemaBuilder
 *
 * This class builds and updates the schema definitions for our tables. This includes CREATEing and ALTERing
 * the actual data tables as well as updating the meta information in our meta data tables.
 *
 * To use, simply instantiate a new object of the Builder and run the build() method on it.
 *
 * Note: even though data tables use a data_ prefix in the database, this prefix is internal only and should
 *       never be passed as $table anywhere!
 *
 * @package dokuwiki\plugin\struct\meta
 */
class SchemaBuilder
{
    /**
     * @var array The posted new data for the schema
     * @see Schema::AdminEditor()
     */
    protected $data = [];

    protected $user;

    /**
     * @var string The table name associated with the schema
     */
    protected $table = '';

    /**
     * @var Schema the previously valid schema for this table
     */
    protected $oldschema;

    /** @var int the ID of the newly created schema */
    protected $newschemaid = 0;

    /** @var \helper_plugin_struct_db */
    protected $helper;

    /** @var SQLiteDB|null */
    protected $sqlite;

    /** @var int the time for which this schema should be created - default to time() can be overriden for tests */
    protected $time = 0;

    /**
     * SchemaBuilder constructor.
     *
     * @param string $table The table's name
     * @param array $data The defining of the table (basically what get's posted in the schema editor form)
     * @see Schema::AdminEditor()
     */
    public function __construct($table, $data)
    {
        global $INPUT;

        $this->table = $table;
        $this->data = $data;
        $this->oldschema = new Schema($table, 0);

        $this->helper = plugin_load('helper', 'struct_db');
        $this->sqlite = $this->helper->getDB();
        $this->user = $_SERVER['REMOTE_USER'] ?? '';
    }

    /**
     * Create the new schema
     *
     * @param int $time when to create this schema 0 for now
     * @return int the new schema id on success
     */
    public function build($time = 0)
    {
        $this->time = $time;
        $this->fixLabelUniqueness();

        $this->sqlite->query('BEGIN TRANSACTION');
        $ok = true;
        // create the data table if new schema
        if (!$this->oldschema->getId()) {
            $ok = $this->newDataTable();
        }

        // create a new schema
        $ok = $ok && $this->newSchema();

        // update column info
        $ok = $ok && $this->updateColumns();
        $ok = $ok && $this->addColumns();

        if (!$ok) {
            $this->sqlite->query('ROLLBACK TRANSACTION');
            return false;
        }
        $this->sqlite->query('COMMIT TRANSACTION');

        return (int)$this->newschemaid;
    }

    /**
     * Makes sure all labels in the schema to save are unique
     */
    protected function fixLabelUniqueness()
    {
        $labels = [];

        if (isset($this->data['cols'])) foreach ($this->data['cols'] as $idx => $column) {
            $this->data['cols'][$idx]['label'] = $this->fixLabel($column['label'], $labels);
        }

        if (isset($this->data['new'])) foreach ($this->data['new'] as $idx => $column) {
            $this->data['new'][$idx]['label'] = $this->fixLabel($column['label'], $labels);
        }
    }

    /**
     * Creates a unique label from the given one
     *
     * @param string $wantedlabel
     * @param array $labels list of already assigned labels (will be filled)
     * @return string
     */
    protected function fixLabel($wantedlabel, &$labels)
    {
        $wantedlabel = trim($wantedlabel);
        $fixedlabel = $wantedlabel;
        $idx = 1;
        while (isset($labels[PhpString::strtolower($fixedlabel)])) {
            $fixedlabel = $wantedlabel . $idx++;
        }
        // did we actually do a rename? apply it.
        if ($fixedlabel !== $wantedlabel) {
            msg(sprintf($this->helper->getLang('duplicate_label'), $wantedlabel, $fixedlabel), -1);
            $this->data['cols']['label'] = $fixedlabel;
        }
        $labels[PhpString::strtolower($fixedlabel)] = 1;
        return $fixedlabel;
    }

    /**
     * Creates a new schema
     */
    protected function newSchema()
    {
        if (!$this->time) $this->time = time();

        $config = $this->data['config'] ?? '{}';

        /** @noinspection SqlResolve */
        $sql = "INSERT INTO schemas (tbl, ts, user, config) VALUES (?, ?, ?, ?)";
        $this->sqlite->query($sql, [$this->table, $this->time, $this->user, $config]);
        $this->newschemaid = $this->sqlite->queryValue('SELECT last_insert_rowid()');

        if (!$this->newschemaid) return false;
        return true;
    }

    /**
     * Updates all the existing column infos and adds them to the new schema
     */
    protected function updateColumns()
    {
        foreach ($this->oldschema->getColumns() as $column) {
            $oldEntry = $column->getType()->getAsEntry();
            $oldTid = $column->getTid();
            $newEntry = $oldEntry;
            $newTid = $oldTid;
            $sort = $column->getSort();
            if (isset($this->data['cols'][$column->getColref()])) {
                // todo I'm not too happy with this hardcoded here -
                // we should probably have a list of fields at one place
                $newEntry['config'] = $this->data['cols'][$column->getColref()]['config'];
                $newEntry['label'] = $this->data['cols'][$column->getColref()]['label'];
                $newEntry['ismulti'] = $this->data['cols'][$column->getColref()]['ismulti'] ?? 0;
                $newEntry['class'] = $this->data['cols'][$column->getColref()]['class'];
                $sort = $this->data['cols'][$column->getColref()]['sort'];
                $enabled = (bool)($this->data['cols'][$column->getColref()]['isenabled'] ?? 0);

                // when the type definition has changed, we create a new one
                if (array_diff_assoc($oldEntry, $newEntry)) {
                    $ok = $this->sqlite->saveRecord('types', $newEntry);
                    if (!$ok) return false;
                    $newTid = $this->sqlite->queryValue('SELECT last_insert_rowid()');
                    if (!$newTid) return false;
                    if ($oldEntry['ismulti'] == false && $newEntry['ismulti'] == '1') {
                        $this->migrateSingleToMulti($this->oldschema->getTable(), $column->getColref());
                    }
                }
            } else {
                $enabled = false; // no longer there for some reason
            }

            // add this type to the schema columns
            $schemaEntry = [
                'sid' => $this->newschemaid,
                'colref' => $column->getColref(),
                'enabled' => $enabled,
                'tid' => $newTid,
                'sort' => $sort
            ];
            $ok = $this->sqlite->saveRecord('schema_cols', $schemaEntry);
            if (!$ok) return false;
        }
        return true;
    }

    /**
     * Write the latest value from an entry in a data_ table to the corresponding multi_table
     *
     * @param string $table
     * @param int $colref
     */
    protected function migrateSingleToMulti($table, $colref)
    {
        /** @noinspection SqlResolve */
        $sqlSelect = "SELECT pid, rev, published, col$colref AS value FROM data_$table WHERE latest = 1";
        $valueSet = $this->sqlite->queryAll($sqlSelect);
        $valueString = [];
        $arguments = [];
        foreach ($valueSet as $values) {
            if (blank($values['value']) || trim($values['value']) == '') {
                continue;
            }
            $valueString[] = "(?, ?, ?, ?, ?, ?)";
            $arguments = [...$arguments, $colref, $values['pid'], $values['rev'], $values['published'], 1, $values['value']];
        }
        if ($valueString === []) {
            return;
        }
        $valueString = implode(',', $valueString);
        /** @noinspection SqlResolve */
        $sqlInsert = "INSERT OR REPLACE INTO multi_$table (colref, pid, rev, published, row, value) VALUES $valueString"; // phpcs:ignore
        $this->sqlite->query($sqlInsert, $arguments);
    }

    /**
     * Adds new columns to the new schema
     *
     * @return bool
     */
    protected function addColumns()
    {
        if (!isset($this->data['new'])) return true;

        $colref = count($this->oldschema->getColumns()) + 1;

        foreach ($this->data['new'] as $column) {
            if (!$column['isenabled']) continue; // we do not add a disabled column

            // todo this duplicates the hardcoding as in  the function above
            $newEntry = [];
            $newEntry['config'] = $column['config'] ?? '{}';
            $newEntry['label'] = $column['label'];
            $newEntry['ismulti'] = $column['ismulti'] ?? 0;
            $newEntry['class'] = $column['class'];
            $sort = $column['sort'];


            // only save if the column got a name
            if (!$newEntry['label']) continue;

            // add new column to the data table
            if (!$this->addDataTableColumn($colref)) {
                return false;
            }

            // save the type
            $ok = $this->sqlite->saveRecord('types', $newEntry);
            if (!$ok) return false;
            $newTid = $this->sqlite->queryValue('SELECT last_insert_rowid()');

            if (!$newTid) return false;


            // add this type to the schema columns
            $schemaEntry = [
                'sid' => $this->newschemaid,
                'colref' => $colref,
                'enabled' => true,
                'tid' => $newTid,
                'sort' => $sort
            ];
            $ok = $this->sqlite->saveRecord('schema_cols', $schemaEntry);
            if (!$ok) return false;
            $colref++;
        }

        return true;
    }

    /**
     * Create a completely new data table with no columns yet also create the appropriate
     * multi value table for the schema
     *
     * @return bool
     * @todo how do we want to handle indexes?
     */
    protected function newDataTable()
    {
        $ok = true;

        $tbl = 'data_' . $this->table;
        $sql = "CREATE TABLE $tbl (
                    pid TEXT DEFAULT '',
                    rid INTEGER,
                    rev INTEGER,
                    latest BOOLEAN NOT NULL DEFAULT 0,
                    published BOOLEAN DEFAULT NULL,
                    PRIMARY KEY(pid, rid, rev)
                )";
        $ok = $ok && (bool)$this->sqlite->query($sql);

        $tbl = 'multi_' . $this->table;
        $sql = "CREATE TABLE $tbl (
                    colref INTEGER NOT NULL,
                    pid TEXT DEFAULT '',
                    rid INTEGER,
                    rev INTEGER,
                    latest INTEGER NOT NULL DEFAULT 0,
                    published BOOLEAN DEFAULT NULL,
                    row INTEGER NOT NULL,
                    value,
                    PRIMARY KEY(colref, pid, rid, rev, row)
                );";
        $ok = $ok && (bool)$this->sqlite->query($sql);

        return $ok;
    }

    /**
     * Add an additional column to the existing data table
     *
     * @param int $index the new column index to add
     * @return bool
     */
    protected function addDataTableColumn($index)
    {
        $tbl = 'data_' . $this->table;
        $sql = " ALTER TABLE $tbl ADD COLUMN col$index DEFAULT ''";
        if (!$this->sqlite->query($sql)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $user
     * @return SchemaBuilder
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }
}
