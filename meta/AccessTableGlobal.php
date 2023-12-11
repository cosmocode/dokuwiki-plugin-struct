<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AccessTableGlobal
 *
 * Load and (more importantly) save data for Global Schemas
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AccessTableGlobal extends AccessTable
{
    public function __construct($table, $pid, $ts = 0, $rid = 0)
    {
        parent::__construct($table, $pid, $ts, $rid);
    }

    /**
     * Remove the current data
     */
    public function clearData()
    {
        if (!$this->rid) return; // no data

        /** @noinspection SqlResolve */
        $sql = 'DELETE FROM data_' . $this->schema->getTable() . ' WHERE rid = ?';
        $this->sqlite->query($sql, $this->rid);
        $sql = 'DELETE FROM multi_' . $this->schema->getTable() . ' WHERE rid = ?';
        $this->sqlite->query($sql, $this->rid);
    }

    /**
     * @inheritDoc
     */
    protected function getLastRevisionTimestamp()
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    protected function buildGetDataSQL($idColumn = 'rid')
    {
        return parent::buildGetDataSQL($idColumn);
    }

    /**
     * @inheritDoc
     */
    protected function getSingleSql()
    {
        $cols = array_merge($this->getSingleNoninputCols(), $this->singleCols);
        $cols = implode(',', $cols);

        $vals = array_merge($this->getSingleNoninputValues(), $this->singleValues);
        $rid = $this->getRid() ?: "(SELECT (COALESCE(MAX(rid), 0 ) + 1) FROM $this->stable)";

        return "REPLACE INTO $this->stable (rid, $cols) 
                      VALUES ($rid," . trim(str_repeat('?,', count($vals)), ',') . ');';
    }

    /**
     * @inheritDoc
     */
    protected function getMultiSql()
    {
        return "REPLACE INTO $this->mtable (pid, rid, rev, latest, colref, row, value) VALUES (?,?,?,?,?,?,?)";
    }

    /**
     * @inheritDoc
     */
    protected function validateTypeData($data)
    {
        // we do not store completely empty rows
        $isempty = array_reduce($data, static fn($isempty, $cell) => $isempty && ($cell === '' || $cell === [] || $cell === null), true);

        return !$isempty;
    }

    /**
     * @inheritDoc
     */
    protected function getSingleNoninputCols()
    {
        return ['pid', 'rev', 'latest'];
    }

    /**
     * @inheritDoc
     */
    protected function getSingleNoninputValues()
    {
        return [$this->pid, AccessTable::DEFAULT_REV, AccessTable::DEFAULT_LATEST];
    }

    /**
     * @inheritDoc
     */
    protected function getMultiNoninputValues()
    {
        return [$this->pid, $this->rid, AccessTable::DEFAULT_REV, AccessTable::DEFAULT_LATEST];
    }

    /**
     * Set new rid if this is a new insert
     * @return bool
     */
    protected function afterSingleSave()
    {
        $ok = true;
        if (!$this->rid) {
            $this->rid = $this->sqlite->queryValue("SELECT rid FROM $this->stable WHERE ROWID = last_insert_rowid()");
            if (!$this->rid) {
                $ok = false;
            }
        }

        // FIXME this might replace handleEmptyMulti() but would it always be safe? in remote API context?
        if (!empty($this->multiValues)) {
            $ok = $ok && $this->clearMulti();
        }

        return $ok;
    }

    /**
     * Add an optional query to clear any previous multi values if the first one is empty.
     * Allows for deleting multi values from the inline editor.
     *
     * @param string $pid
     * @param int $rid
     * @param int $colref
     */
    protected function handleEmptyMulti($pid, $rid, $colref)
    {
        $table = 'multi_' . $this->schema->getTable();
        $this->optQueries[] = [
            "DELETE FROM $table WHERE pid = ? AND rid = ? AND colref = ?",
            $pid, $rid, $colref
        ];
    }
}
