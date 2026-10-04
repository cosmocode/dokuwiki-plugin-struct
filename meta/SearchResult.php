<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class SearchResult
 *
 * Search is executed only once per request.
 */
class SearchResult
{
    /** @var Value[][] */
    protected $rows = [];
    /** @var array */
    protected $pids = [];
    protected $rids = [];
    /** @var array */
    protected $revs = [];
    /** @var int */
    protected $count = -1;

    /**
     * Construct SearchResult
     *
     * @param \PDOStatement $res PDO statement containing the search result
     * @param int $rangeBegin Begin of requested result range
     * @param int $rangeEnd End of requested result range
     * @param Column[] $columns Search columns
     * @param bool $pageidAndRevOnly
     */
    public function __construct($res, $rangeBegin, $rangeEnd, $columns, $pageidAndRevOnly)
    {
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $this->increaseCount();
            if ($this->getCount() < $rangeBegin) continue;
            if ($rangeEnd && $this->getCount() >= $rangeEnd) continue;

            $C = 0;
            $resrow = [];
            $isempty = true;
            foreach ($columns as $col) {
                $val = $row["C$C"];
                if ($col->isMulti()) {
                    $val = explode(Search::CONCAT_SEPARATOR, $val);
                }
                $value = new Value($col, $val);
                $isempty &= $this->isEmptyValue($value);
                $resrow[] = $value;
                $C++;
            }

            // skip empty rows
            if ($isempty && !$pageidAndRevOnly) {
                $this->decreaseCount();
                continue;
            }

            $this->pids[] = $row['PID'];
            $this->rids[] = $row['rid'];
            $this->revs[] = $row['rev'];
            $this->rows[] = $resrow;
        }

        $this->increaseCount();
    }

    /**
     * @return array
     */
    public function getPids(): array
    {
        return $this->pids;
    }

    /**
     * @return Value[][]
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @return array
     */
    public function getRids(): array
    {
        return $this->rids;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return array
     */
    public function getRevs(): array
    {
        return $this->revs;
    }

    /**
     * @return void
     */
    public function increaseCount()
    {
        $this->count++;
    }
/**
     * @return void
     */
    public function decreaseCount()
    {
        $this->count--;
    }

    /**
     * Check if the given row is empty or references our own row
     *
     * @param Value $value
     * @return bool
     */
    protected function isEmptyValue(Value $value)
    {
        if ($value->isEmpty()) return true;
        if ($value->getColumn()->getTid() == 0) return true;
        return false;
    }
}
