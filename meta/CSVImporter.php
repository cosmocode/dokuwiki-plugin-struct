<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\plugin\struct\types\Page;

/**
 * Class CSVImporter
 *
 * Imports CSV data
 *
 * @package dokuwiki\plugin\struct\meta
 */
class CSVImporter
{

    /** @var  Schema */
    protected $schema;

    /** @var  resource */
    protected $fh;

    /** @var  \helper_plugin_sqlite */
    protected $sqlite;

    /** @var Column[] The single values to store index => col */
    protected $columns = array();

    /** @var int current line number */
    protected $line = 0;

    /** @var array list of headers */
    protected $header;

    /** @var  array list of validation errors */
    protected $errors;

    /**
     * @var string data type, must be one of page, global, serial
     */
    protected $type;

    /**
     * CSVImporter constructor.
     *
     * @param string $table
     * @param string $file
     * @param string $type
     */
    public function __construct($table, $file, $type)
    {
        $this->type = $type;
        $this->openFile($file);

        $this->schema = new Schema($table);
        if (!$this->schema->getId()) throw new StructException('Schema does not exist');

        /** @var \helper_plugin_struct_db $db */
        $db = plugin_load('helper', 'struct_db');
        $this->sqlite = $db->getDB(true);
    }

    /**
     * Import the data from file.
     *
     * @throws StructException
     */
    public function import()
    {
        // Do the import
        $this->readHeaders();
        $this->importCSV();
    }

    /**
     * Open a given file path
     *
     * The main purpose of this method is to be overridden in a mock for testing
     *
     * @param string $file the file path
     *
     * @return void
     */
    protected function openFile($file)
    {
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new StructException('Failed to open CSV file for reading');
        }
    }

    /**
     * Get a parsed line from the opened CSV file
     *
     * The main purpose of this method is to be overridden in a mock for testing
     *
     * @return array|false|null
     */
    protected function getLine()
    {
        return fgetcsv($this->fh);
    }

    /**
     * Read the CSV headers and match it with the Schema columns
     */
    protected function readHeaders()
    {
        $header = $this->getLine();
        if (!$header) throw new StructException('Failed to read CSV');
        $this->line++;

        // we might have to create a page column first
        if ($this->type !== CSVExporter::DATATYPE_GLOBAL) {
            $pageType = new Page(null, 'pid');
            $pidCol = new Column(0, $pageType, 0, true, $this->schema->getTable());
            $this->columns[] = $pidCol;
        }

        foreach ($header as $i => $head) {
            $col = $this->schema->findColumn($head);
            // just skip the checks for 'pid' but discard other columns not present in the schema
            if (!$col) {
                if ($head !== 'pid') {
                    unset($header[$i]);
                }
                continue;
            }
            if (!$col->isEnabled()) continue;
            $this->columns[$i] = $col;
        }

        if (!$this->columns) {
            throw new StructException('None of the CSV headers matched any of the schema\'s fields');
        }

        $this->header = $header;
    }

    /**
     * Walks through the CSV and imports
     */
    protected function importCSV()
    {
        while (($data = $this->getLine()) !== false) {
            $this->line++;
            $this->importLine($data);
        }
    }

    /**
     * The errors that occured during validation
     *
     * @return string[] already translated error messages
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Validate a single value
     *
     * @param Column $col the column of that value
     * @param mixed &$rawvalue the value, will be fixed according to the type
     * @return bool true if the data validates, otherwise false
     */
    protected function validateValue(Column $col, &$rawvalue)
    {
        //by default no validation
        return true;
    }

    /**
     * Read and validate CSV parsed line
     *
     * @param $line
     * @return array|bool
     */
    protected function readLine($line)
    {
        // prepare values for single value table
        $values = array();
        foreach ($this->columns as $i => $column) {
            if (!isset($line[$i])) throw new StructException('Missing field at CSV line %d', $this->line);

            if (!$this->validateValue($column, $line[$i])) return false;

            if ($column->isMulti()) {
                // multi values get split on comma, but JSON values contain commas too, hence preg_split
                if ($line[$i][0] === '[') {
                    $line[$i] = preg_split('/,(?=\[)/', $line[$i]);
                } else {
                    $line[$i] = array_map('trim', explode(',', $line[$i]));
                }
            }
            // data access will handle multivalues, no need to manipulate them here
            $values[] = $line[$i];
        }
        //if no ok don't import
        return $values;
    }

    /**
     * Save one CSV line into database
     *
     * @param string[] $values parsed line values
     */
    protected function saveLine($values)
    {
        $data = array_combine($this->header, $values);
        // pid is a non-data column and must be supplied to the AccessTable separately
        $pid = isset($data['pid']) ? $data['pid'] : '';
        unset($data['pid']);
        $table = $this->schema->getTable();

        /** @var 'helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        if ($this->type === CSVExporter::DATATYPE_PAGE) {
            $helper->saveData($pid, [$table => $data], 'CSV data imported');
            return;
        }
        if ($this->type === CSVExporter::DATATYPE_SERIAL) {
            $access = AccessTable::getSerialAccess($table, $pid);
        } else {
            $access = AccessTable::getGlobalAccess($table);
        }
        $helper->saveLookupData($access, $data);
    }

    /**
     * Imports one line into the schema
     *
     * @param string[] $line the parsed CSV line
     */
    protected function importLine($line)
    {
        //read values, false if invalid, empty array if the same as current data
        $values = $this->readLine($line);

        if ($values) {
            $this->saveLine($values);
        } else foreach ($this->errors as $error) {
            msg($error, -1);
        }
    }
}
