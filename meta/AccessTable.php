<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AccessTable
 *
 * Base class for data accessors
 *
 * @package dokuwiki\plugin\struct\meta
 */
abstract class AccessTable
{
    public const DEFAULT_REV = 0;
    public const DEFAULT_LATEST = 1;

    /** @var  Schema */
    protected $schema;
    protected $pid;
    protected $rid;
    protected $labels = [];
    protected $ts = 0;
    protected $published;
    /** @var \helper_plugin_sqlite */
    protected $sqlite;

    // options on how to retrieve data
    protected $opt_skipempty = false;

    protected $optQueries = [];

    /**
     * @var string Name of single-value table
     */
    protected $stable;

    /**
     * @var string Name of multi-value table
     */
    protected $mtable;

    /**
     * @var array Column names for the single-value insert/update
     */
    protected $singleCols;

    /**
     * @var array Input values for the single-value insert/update
     */
    protected $singleValues;

    /**
     * @var array Input values for the multi-value inserts/updates
     */
    protected $multiValues;

    public static function getPageAccess($tablename, $pid, $ts = 0)
    {
        $schema = new Schema($tablename, $ts);
        return new AccessTablePage($schema, $pid, $ts, 0);
    }

    public static function getSerialAccess($tablename, $pid, $rid = 0)
    {
        $schema = new Schema($tablename, 0);
        return new AccessTableSerial($schema, $pid, 0, $rid);
    }

    public static function getGlobalAccess($tablename, $rid = 0)
    {
        $schema = new Schema($tablename, 0);
        return new AccessTableGlobal($schema, '', 0, $rid);
    }

    /**
     * Factory method returning the appropriate data accessor (page, global or serial)
     *
     * @param Schema $schema schema to load
     * @param string $pid Page id to access
     * @param int $ts Time at which the data should be read or written
     * @param int $rid Row id, 0 for page type data, otherwise autoincrement
     * @return AccessTablePage|AccessTableGlobal
     * @deprecated
     */
    public static function bySchema(Schema $schema, $pid, $ts = 0, $rid = 0)
    {
        if (self::isTypePage($pid, $ts)) {
            return new AccessTablePage($schema, $pid, $ts, $rid);
        }
        return new AccessTableGlobal($schema, $pid, $ts, $rid);
    }

    /**
     * Factory Method to access data
     *
     * @param string $tablename schema to load
     * @param string $pid Page id to access
     * @param int $ts Time at which the data should be read or written
     * @param int $rid Row id, 0 for page type data, otherwise autoincrement
     * @return AccessTablePage|AccessTableGlobal
     * @deprecated  Use specific methods since we can no longer
     *              guarantee instantiating the required descendant class
     */
    public static function byTableName($tablename, $pid, $ts = 0, $rid = 0)
    {
        // force loading the latest schema for anything other than page data,
        // for which we might actually need the history
        if (!self::isTypePage($pid, $ts)) {
            $schema = new Schema($tablename, time());
        } else {
            $schema = new Schema($tablename, $ts);
        }
        return self::bySchema($schema, $pid, $ts, $rid);
    }

    /**
     * AccessTable constructor
     *
     * @param Schema $schema The schema valid at $ts
     * @param string $pid Page id
     * @param int $ts Time at which the data should be read or written, 0 for now
     * @param int $rid Row id: 0 for pages, autoincremented for other types
     */
    public function __construct($schema, $pid, $ts = 0, $rid = 0)
    {
        /** @var \helper_plugin_struct_db $helper */
        $helper = plugin_load('helper', 'struct_db');
        $this->sqlite = $helper->getDB();

        if (!$schema->getId()) {
            throw new StructException('Schema does not exist. Only data of existing schemas can be accessed');
        }

        $this->schema = $schema;
        $this->pid = $pid;
        $this->rid = $rid;
        $this->setTimestamp($ts);
        foreach ($this->schema->getColumns() as $col) {
            $this->labels[$col->getColref()] = $col->getType()->getLabel();
        }
    }

    /**
     * gives access to the schema
     *
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * The current pid
     *
     * @return string
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * The current rid
     *
     * @return int
     */
    public function getRid()
    {
        return $this->rid;
    }

    /**
     * Published status
     *
     * @return int|null
     */
    public function getPublished()
    {
        return $this->published;
    }

    /**
     * Should remove the current data, by either deleting or ovewriting it
     *
     * @return bool if the delete succeeded
     */
    abstract public function clearData();

    /**
     * Save the data to the database.
     *
     * We differentiate between single-value-column and multi-value-column by the value to the respective column-name,
     * i.e. depending on if that is a string or an array, respectively.
     *
     * @param array $data typelabel => value for single fields or typelabel => array(value, value, ...) for multi fields
     * @return bool success of saving the data to the database
     */
    public function saveData($data)
    {
        if (!$this->validateTypeData($data)) {
            return false;
        }

        $this->stable = 'data_' . $this->schema->getTable();
        $this->mtable = 'multi_' . $this->schema->getTable();

        $colrefs = array_flip($this->labels);

        foreach ($data as $colname => $value) {
            if (!isset($colrefs[$colname])) {
                throw new StructException("Unknown column %s in schema.", hsc($colname));
            }

            $this->singleCols[] = 'col' . $colrefs[$colname];
            if (is_array($value)) {
                foreach ($value as $index => $multivalue) {
                    $this->multiValues[] = [$colrefs[$colname], $index + 1, $multivalue];
                }
                // copy first value to the single column
                if (isset($value[0])) {
                    $this->singleValues[] = $value[0];
                    if ($value[0] === '') {
                        $this->handleEmptyMulti($this->pid, $this->rid, $colrefs[$colname]);
                    }
                } else {
                    $this->singleValues[] = null;
                }
            } else {
                $this->singleValues[] = $value;
            }
        }

        $this->sqlite->query('BEGIN TRANSACTION');

        $ok = $this->beforeSave();

        // insert single values
        $ok = $ok && $this->sqlite->query(
            $this->getSingleSql(),
            array_merge($this->getSingleNoninputValues(), $this->singleValues)
        );

        $ok = $ok && $this->afterSingleSave();

        // insert multi values
        if ($ok && $this->multiValues) {
            $multisql = $this->getMultiSql();
            $multiNoninputValues = $this->getMultiNoninputValues();
            foreach ($this->multiValues as $value) {
                $ok = $ok && $this->sqlite->query(
                    $multisql,
                    array_merge($multiNoninputValues, $value)
                );
            }
        }

        $ok = $ok && $this->afterSave();

        if (!$ok) {
            $this->sqlite->query('ROLLBACK TRANSACTION');
            return false;
        }
        $this->sqlite->query('COMMIT TRANSACTION');
        return true;
    }

    /**
     * Check whether all required data is present
     *
     * @param array $data
     * @return bool
     */
    abstract protected function validateTypeData($data);

    /**
     * Names of non-input columns to be inserted into SQL query
     *
     * @return array
     */
    abstract protected function getSingleNoninputCols();

    /**
     * Values for non-input columns to be inserted into SQL query
     * for single-value tables
     *
     * @return array
     */
    abstract protected function getSingleNoninputValues();

    /**
     * String template for single-value table
     *
     * @return string
     */
    protected function getSingleSql()
    {
        $cols = array_merge($this->getSingleNoninputCols(), $this->singleCols);
        $cols = implode(',', $cols);

        $vals = array_merge($this->getSingleNoninputValues(), $this->singleValues);

        return "INSERT INTO $this->stable ($cols) VALUES (" . trim(str_repeat('?,', count($vals)), ',') . ');';
    }

    /**
     * Optional operations to be executed before saving data
     *
     * @return bool False if any of the operations failed and transaction should be rolled back
     */
    protected function beforeSave()
    {
        return true;
    }

    /**
     * Optional operations to be executed after saving data to single-value table,
     * before saving multivalues
     *
     * @return bool False if anything goes wrong and transaction should be rolled back
     */
    protected function afterSingleSave()
    {
        return true;
    }

    /**
     * Executes final optional queries.
     *
     * @return bool False if anything goes wrong and transaction should be rolled back
     */
    protected function afterSave()
    {
        $ok = true;
        foreach ($this->optQueries as $query) {
            $sql = array_shift($query);
            $ok = $ok && $this->sqlite->query($sql, $query);
        }
        return $ok;
    }

    /**
     * String template for multi-value table
     *
     * @return string
     */
    abstract protected function getMultiSql();

    /**
     * Values for non-input columns to be inserted into SQL query
     * for multi-value tables
     * @return array
     */
    abstract protected function getMultiNoninputValues();


    /**
     * Should empty or invisible (inpage) fields be returned?
     *
     * Defaults to false
     *
     * @param null|bool $set new value, null to read only
     * @return bool current value (after set)
     */
    public function optionSkipEmpty($set = null)
    {
        if (!is_null($set)) {
            $this->opt_skipempty = $set;
        }
        return $this->opt_skipempty;
    }

    /**
     * Get the value of a single column
     *
     * @param Column $column
     * @return Value|null
     */
    public function getDataColumn($column)
    {
        $data = $this->getData();
        foreach ($data as $value) {
            if ($value->getColumn() == $column) {
                return $value;
            }
        }
        return null;
    }

    /**
     * returns the data saved for the page
     *
     * @return Value[] a list of values saved for the current page
     */
    public function getData()
    {
        $data = $this->getDataFromDB();
        $data = $this->consolidateData($data, false);
        return $data;
    }

    /**
     * returns the data saved for the page as associative array
     *
     * The array returned is in the same format as used in @return array
     * @see saveData()
     *
     * It always returns raw Values!
     *
     * @return array
     */
    public function getDataArray()
    {
        $data = $this->getDataFromDB();
        $data = $this->consolidateData($data, true);
        return $data;
    }

    /**
     * Return the data in pseudo syntax
     */
    public function getDataPseudoSyntax()
    {
        $result = '';
        $data = $this->getData();

        foreach ($data as $value) {
            $key = $value->getColumn()->getFullQualifiedLabel();
            $value = $value->getDisplayValue();
            if (is_array($value)) $value = implode(', ', $value);
            $result .= sprintf("% -20s : %s\n", $key, $value);
        }
        return $result;
    }

    /**
     * retrieve the data saved for the page from the database. Usually there is no need to call this function.
     * Call @see SchemaData::getData instead.
     */
    protected function getDataFromDB()
    {
        $idColumn = self::isTypePage($this->pid, $this->ts) ? 'pid' : 'rid';
        [$sql, $opt] = $this->buildGetDataSQL($idColumn);

        return $this->sqlite->queryAll($sql, $opt);
    }

    /**
     * Creates a proper result array from the database data
     *
     * @param array $DBdata the data as it is retrieved from the database, i.e. by SchemaData::getDataFromDB
     * @param bool $asarray return data as associative array (true) or as array of Values (false)
     * @return array|Value[]
     */
    protected function consolidateData($DBdata, $asarray = false)
    {
        $data = [];

        $sep = Search::CONCAT_SEPARATOR;

        foreach ($this->schema->getColumns(false) as $col) {
            // if no data saved yet, return empty strings
            if ($DBdata) {
                $val = (string) $DBdata[0]['out' . $col->getColref()];
            } else {
                $val = '';
            }

            // multi val data is concatenated
            if ($col->isMulti()) {
                $val = explode($sep, $val);
                $val = array_filter($val);
            }

            $value = new Value($col, $val);

            if ($this->opt_skipempty && $value->isEmpty()) continue;
            if ($this->opt_skipempty && !$col->isVisibleInPage()) continue; //FIXME is this a correct assumption?

            // for arrays, we return the raw value only
            if ($asarray) {
                $data[$col->getLabel()] = $value->getRawValue();
            } else {
                $data[$col->getLabel()] = $value;
            }
        }

        return $data;
    }

    /**
     * Builds the SQL statement to select the data for this page and schema
     *
     * @return array Two fields: the SQL string and the parameters array
     */
    protected function buildGetDataSQL($idColumn = 'pid')
    {
        $sep = Search::CONCAT_SEPARATOR;
        $stable = 'data_' . $this->schema->getTable();
        $mtable = 'multi_' . $this->schema->getTable();

        $QB = new QueryBuilder();
        $QB->addTable($stable, 'DATA');
        $QB->addSelectColumn('DATA', $idColumn, strtoupper($idColumn));
        $QB->addGroupByStatement("DATA.$idColumn");

        foreach ($this->schema->getColumns(false) as $col) {
            $colref = $col->getColref();
            $colname = 'col' . $colref;
            $outname = 'out' . $colref;

            if ($col->getType()->isMulti()) {
                $tn = 'M' . $colref;
                $QB->addLeftJoin(
                    'DATA',
                    $mtable,
                    $tn,
                    "DATA.$idColumn = $tn.$idColumn AND DATA.rev = $tn.rev AND $tn.colref = $colref"
                );
                $col->getType()->select($QB, $tn, 'value', $outname);
                $sel = $QB->getSelectStatement($outname);
                $QB->addSelectStatement("GROUP_CONCAT_DISTINCT($sel, '$sep')", $outname);
            } else {
                $col->getType()->select($QB, 'DATA', $colname, $outname);
                $QB->addGroupByStatement($outname);
            }
        }

        $pl = $QB->addValue($this->{$idColumn});
        $QB->filters()->whereAnd("DATA.$idColumn = $pl");
        $pl = $QB->addValue($this->getLastRevisionTimestamp());
        $QB->filters()->whereAnd("DATA.rev = $pl");

        return $QB->getSQL();
    }

    /**
     * @param int $ts
     */
    public function setTimestamp($ts)
    {
        if ($ts && $ts < $this->schema->getTimeStamp()) {
            throw new StructException('Given timestamp is not valid for current Schema');
        }

        $this->ts = $ts;
    }

    /**
     * Returns the timestamp from the current data
     * @return int
     */
    public function getTimestamp()
    {
        return $this->ts;
    }

    /**
     * Return the last time an edit happened for this table for the currently set
     * time and pid. Used in
     * @see buildGetDataSQL()
     *
     * @return int
     */
    abstract protected function getLastRevisionTimestamp();

    /**
     * Check if the given data validates against the current types.
     *
     * @param array $data
     * @return AccessDataValidator
     */
    public function getValidator($data)
    {
        return new AccessDataValidator($this, $data);
    }

    /**
     * Returns true if data is of type "page"
     *
     * @param string $pid
     * @param int $rev
     * @param int $rid
     * @return bool
     */
    public static function isTypePage($pid, $rev)
    {
        return $rev > 0;
    }

    /**
     * Returns true if data is of type "global"
     *
     * @param string $pid
     * @param int $rev
     * @param int $rid
     * @return bool
     */
    public static function isTypeGlobal($pid, $rev)
    {
        return $pid === '';
    }

    /**
     * Returns true if data is of type "serial"
     *
     * @param string $pid
     * @param int $rev
     * @param int $rid
     * @return bool
     */
    public static function isTypeSerial($pid, $rev)
    {
        return $pid !== '' && $rev === 0;
    }

    /**
     * Global and serial data require additional queries. They are put into query queue
     * in descendants of this method.
     *
     * @param string $pid
     * @param int $rid
     * @param int $colref
     */
    protected function handleEmptyMulti($pid, $rid, $colref)
    {
    }

    /**
     * Clears all multi_ values for the current row.
     * Executed when updating global and serial data. Otherwise removed (deselected) values linger in database.
     *
     * @return bool|\SQLiteResult
     */
    protected function clearMulti()
    {
        $colrefs = array_unique(array_map(static fn($val) => $val[0], $this->multiValues));
        return $this->sqlite->query(
            "DELETE FROM $this->mtable WHERE pid = ? AND rid = $this->rid AND rev = 0 AND colref IN (" .
            implode(',', $colrefs) . ")",
            [$this->pid]
        );
    }
}
