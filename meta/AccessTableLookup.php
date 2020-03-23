<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AccessTableLookup
 *
 * Load and (more importantly) save data for Lookup Schemas
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AccessTableLookup extends AccessTable {

    /**
     * Remove the current data
     */
    public function clearData() {
        if(!$this->rid) return; // no data

        /** @noinspection SqlResolve */
        $sql = 'DELETE FROM ? WHERE rid = ?';
        $this->sqlite->query($sql, 'data_'.$this->schema->getTable(), $this->rid);
        $this->sqlite->query($sql, 'multi_'.$this->schema->getTable(), $this->rid);
    }

    /**
     * Save the data to the database.
     *
     * We differentiate between single-value-column and multi-value-column by the value to the respective column-name,
     * i.e. depending on if that is a string or an array, respectively.
     *
     * @param array $data typelabel => value for single fields or typelabel => array(value, value, ...) for multi fields
     * @return bool success of saving the data to the database
     * @todo this duplicates quite a bit code from AccessTableData - could we avoid that?
     */
    public function saveData($data) {
        $stable = 'data_' . $this->schema->getTable();
        $mtable = 'multi_' . $this->schema->getTable();

        // we do not store completely empty rows
        $isempty = array_reduce($data, function ($isempty, $cell) {
            return $isempty && ($cell === '' || $cell === array() || $cell === null);
        }, true);
        if($isempty) return false;


        $singlecols = ['rev', 'latest'];
        $opt = [0, 1];

        $colrefs = array_flip($this->labels);
        $multiopts = array();
        foreach($data as $colname => $value) {
            if(!isset($colrefs[$colname])) {
                throw new StructException("Unknown column %s in schema.", hsc($colname));
            }

            $singlecols[] = 'col' . $colrefs[$colname];
            if(is_array($value)) {
                foreach($value as $index => $multivalue) {
                    $multiopts[] = array($colrefs[$colname], $index + 1, $multivalue,);
                }
                // copy first value to the single column
                if(isset($value[0])) {
                    $opt[] = $value[0];
                } else {
                    $opt[] = null;
                }
            } else {
                $opt[] = $value;
            }
        }

        $ridSingle = "(SELECT (COALESCE(MAX(rid), 0 ) + 1) FROM $stable)";
        $ridMulti = "(SELECT (COALESCE(MAX(rid), 0 ) + 1) FROM $mtable)";

        $singlesql = "REPLACE INTO $stable (pid, rid, " . join(',', $singlecols) . ") VALUES (NULL, $ridSingle, " . trim(str_repeat('?,', count($opt)), ',') . ")";
        /** @noinspection SqlResolve */
        $multisql = "REPLACE INTO $mtable (pid, rid, colref, row, value) VALUES (NULL, $ridMulti, ?,?,?)";

        $this->sqlite->query('BEGIN TRANSACTION');
        $ok = true;

        // insert single values
        $ok = $ok && $this->sqlite->query($singlesql, $opt);

        // get new rid if this is a new insert
        if($ok && !$this->rid) {
            $res = $this->sqlite->query('SELECT last_insert_rowid()');
            $this->rid = $this->sqlite->res2single($res);
            $this->sqlite->res_close($res);
            if(!$this->rid) $ok = false;
        }

        // insert multi values
        if($ok) foreach($multiopts as $multiopt) {
            $multiopt = array_merge(array($this->rid,), $multiopt);
            $ok = $ok && $this->sqlite->query($multisql, $multiopt);
        }

        if(!$ok) {
            $this->sqlite->query('ROLLBACK TRANSACTION');
            return false;
        }
        $this->sqlite->query('COMMIT TRANSACTION');
        return true;
    }

    protected function getLastRevisionTimestamp() {
        return 0;
    }

}
