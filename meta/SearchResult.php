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

    /** @var SearchResult */
    protected static $instance;

    /**
     * Get the singleton instance of SearchResult
     *
     * @return SearchResult
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            $class = static::class;
            self::$instance = new $class();
        }
        return self::$instance;
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
     * @param string $pid
     * @return void
     */
    public function addPid($pid)
    {
        $this->pids[] = $pid;
    }

    /**
     * @param int $rid
     * @return void
     */
    public function addRid($rid)
    {
        $this->rids[] = $rid;
    }

    /**
     * @param int $rev
     * @return void
     */
    public function addRev($rev)
    {
        $this->revs[] = $rev;
    }

    /**
     * @param array $result
     * @return void
     */
    public function addRow($row)
    {
        $this->rows[] = $row;
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
}
