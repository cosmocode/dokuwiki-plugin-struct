<?php

namespace plugin\struct\meta;

/**
 * Class SchemaData
 * @package plugin\struct\meta
 *
 * This class is for accessing the data stored for a page in a schema
 *
 */
class SchemaData extends Schema {

    protected $page;
    protected $labels = array();

    /**
     * SchemaData constructor
     *
     * @param string $table The table this schema is for
     * @param string $page The page of which the data is for
     * @param int $ts The timestamp for when this schema was valid, 0 for current
     */
    public function __construct($table, $page, $ts) {
        parent::__construct($table, $ts);
        $this->page = $page;
        foreach ($this->columns as $col ){
            $this->labels[$col->getColref()] = $col->getType()->getLabel();
        }
    }

    /**
     * Save the data to the database.
     *
     * We differentiate between single-value-column and multi-value-column by the value to the respective column-name,
     * i.e. depending on if that is a string or an array, respectively.
     *
     * @param array $data typelabel => value for single fields or typelabel => array(value, value, ...) for multi fields
     *
     * @return bool success of saving the data to the database
     */
    public function saveData($data) {
        $stable = 'data_' . $this->table;
        $mtable = 'multi_' . $this->table;

        $colrefs = array_flip($this->labels);
        $now = $this->ts;
        $opt = array($this->page, $now, 1);
        $multiopts = array();
        $singlecols = 'pid, rev, latest';
        foreach ($data as $colname => $value) {
            if(!isset($colrefs[$colname])) {
                throw new StructException("Unknown column %s in schema.", hsc($colname));
            }

            if (is_array($value)) {
                foreach ($value as $index => $multivalue) {
                    $multiopts[] = array($colrefs[$colname], $index+1, $multivalue,);
                }
            } else {
                $singlecols .= ",col" . $colrefs[$colname];
                $opt[] = $value;
            }
        }
        $singlesql = "INSERT INTO $stable ($singlecols) VALUES (" . trim(str_repeat('?,',count($opt)),',') . ")";
        /** @noinspection SqlResolve */
        $multisql = "INSERT INTO $mtable (rev, pid, colref, row, value) VALUES (?,?,?,?,?)";

        $this->sqlite->query('BEGIN TRANSACTION');

        // remove latest status from previous data
        /** @noinspection SqlResolve */
        $ok = $this->sqlite->query( "UPDATE $stable SET latest = 0 WHERE latest = 1 AND pid = ?",array($this->page));

        // insert single values
        $ok = $ok && $this->sqlite->query($singlesql, $opt);


        // insert multi values
        foreach ($multiopts as $multiopt) {
            $multiopt = array_merge(array($now, $this->page,), $multiopt);
            $ok = $ok && $this->sqlite->query($multisql, $multiopt);
        }

        if (!$ok) {
            $this->sqlite->query('ROLLBACK TRANSACTION');
            return false;
        }
        $this->sqlite->query('COMMIT TRANSACTION');
        return true;
    }

    /**
     * returns the data saved for the page
     *
     * @return Value[] a list of values saved for the current page
     */
    public function getData() {

        $this->setCorrectTimestamp($this->ts);
        $data = $this->getDataFromDB();
        $data = $this->consolidateData($data);

        return $data;
    }

    /**
     * retrieve the data saved for the page from the database. Usually there is no need to call this function.
     * Call @see SchemaData::getData instead.
     *
     * @return array
     */
    protected function getDataFromDB() {
        // prepare column names
        $singles = array();
        $multis = array();
        foreach($this->columns as $col) {
            if(!$col->isEnabled()) continue;

            if($col->getType()->isMulti()) {
                $multis[] = $col->getColref();
            } else {
                $singles[] = $col->getColref();
            }
        }
        list($sql, $opt) = $this->buildGetDataSQL($singles, $multis);

        $res = $this->sqlite->query($sql, $opt);
        $data = $this->sqlite->res2arr($res);

        return $data;
    }

    /**
     * @param array $DBdata the data as it is retrieved from the database, i.e. by SchemaData::getDataFromDB
     * @return Value[]
     */
    protected function consolidateData($DBdata) {
        $data = array();

        foreach($this->getColumns() as $col) {
            if(!$DBdata) {
                // if no data saved, yet return empty strings
                $val = '';
            } else if($col->isMulti()) {
                // data may be in multiple rows
                $val = array();
                foreach($DBdata as $row) {
                    if(!is_null($row['col'.$col->getColref()])) {
                        $val[] = $row['col'.$col->getColref()];
                    }
                }
            }else {
                // data is in the first row only
                $val = $DBdata[0]['col'.$col->getColref()];
            }

            $data[] = new Value($col, $val);
        }

        return $data;
    }

    /**
     * Builds the SQL statement to select the data for this page and schema
     *
     * @param int[] $singles Column reference numbers of single value columns to select
     * @param int[] $multis Column reference numbers of multi value columns to select
     * @return array Two fields: the SQL string and the parameters array
     */
    protected function buildGetDataSQL($singles, $multis) {
        $stable = 'data_' . $this->table;
        $mtable = 'multi_' . $this->table;

        $colsel = join(',', preg_filter('/^/', 'col', $singles));

        $select = 'SELECT ' . $colsel;
        $join = '';
        foreach($multis as $col) {
            $tn = 'M' . $col;
            $select .= ",$tn.value AS col$col";
            $join .= "LEFT OUTER JOIN $mtable $tn";
            $join .= " ON DATA.pid = $tn.pid AND DATA.rev = $tn.rev";
            $join .= " AND $tn.colref = $col\n";
        }

        $where = "WHERE DATA.pid = ? AND DATA.rev <= ?";
        $opt = array($this->page, $this->ts);

        $sql = "$select FROM $stable DATA\n$join $where";

        return array($sql, $opt);
    }

    /**
     * Set $this->ts to an existing timestamp, which is either current timestamp if it exists
     * or the next oldest timestamp that exists. If not timestamp is provided it is the newest timestamp that exists.
     *
     * @param int|null $ts
     */
    protected function setCorrectTimestamp($ts = null) {
        $table = 'data_' . $this->table;
        $where = '';
        if ($ts) {
            $where = "WHERE rev <= $ts";
        }
        $sql = "SELECT rev FROM $table $where ORDER BY rev DESC LIMIT 1";
        $res = $this->sqlite->query($sql);
        $this->ts = $this->sqlite->res2single($res);
    }

}
