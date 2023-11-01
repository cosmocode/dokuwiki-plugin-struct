<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Manage dynamic parameters for aggregations
 *
 * @package dokuwiki\plugin\struct\meta
 */
class SearchConfigParameters
{
    /** @var string parameter name to pass filters */
    public static $PARAM_FILTER = 'flt';
    /** @var string parameter name to pass offset */
    public static $PARAM_OFFSET = 'ofs';
    /** @var string parameter name to pass srt */
    public static $PARAM_SORT = 'srt';

    /** @var SearchConfig */
    protected $searchConfig;

    /** @var null|array */
    protected $sort;
    /** @var int */
    protected $offset = 0;
    /** @var  array */
    protected $filters = [];

    /**
     * Initializes the dynamic parameters from $INPUT
     *
     * @param SearchConfig $searchConfig
     */
    public function __construct(SearchConfig $searchConfig)
    {
        global $INPUT;
        $this->searchConfig = $searchConfig;
        /** @var \helper_plugin_struct_config $confHlp */
        $confHlp = plugin_load('helper', 'struct_config');

        if ($INPUT->has(self::$PARAM_SORT)) {
            [$colname, $sort] = $confHlp->parseSort($INPUT->str(self::$PARAM_SORT));
            $this->setSort($colname, $sort);
        }

        if ($INPUT->has(self::$PARAM_FILTER)) {
            foreach ($INPUT->arr(self::$PARAM_FILTER) as $colcomp => $filter) {
                [$colname, $comp, $value, ] = $confHlp->parseFilterLine('AND', $colcomp . $filter);
                $this->addFilter($colname, $comp, $value);
            }
        }

        if ($INPUT->has(self::$PARAM_OFFSET)) {
            $this->setOffset($INPUT->int(self::$PARAM_OFFSET));
        }
    }

    /**
     * Returns the full qualified name for a given column
     *
     * @param string|Column $column
     * @return false|string
     */
    protected function resolveColumn($column)
    {
        if (!is_a($column, Column::class)) {
            $column = $this->searchConfig->findColumn($column);
            if (!$column) return false;
        }
        /** @var Column $column */
        return $column->getFullQualifiedLabel();
    }

    /**
     * Sets the sorting column
     *
     * @param string|Column $column
     * @param bool $asc
     */
    public function setSort($column, $asc = true)
    {
        $column = $this->resolveColumn($column);
        if (!$column) return;
        $this->sort = [$column, $asc];
    }

    /**
     * Remove the sorting column
     */
    public function removeSort()
    {
        $this->sort = null;
    }

    /**
     * Set the offset
     *
     * @param int $offset
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * Removes the offset
     */
    public function removeOffset()
    {
        $this->offset = 0;
    }

    /**
     * Adds another filter
     *
     * When there is a filter for that column already, the new filter overwrites it. Setting a
     * blank value is the same as calling removeFilter()
     *
     * @param string|Column $column
     * @param string $comp the comparator
     * @param string $value the value to compare against
     */
    public function addFilter($column, $comp, $value)
    {
        $column = $this->resolveColumn($column);
        if (!$column) return;

        if (trim($value) === '') {
            $this->removeFilter($column);
        } else {
            $this->filters[$column] = [$comp, $value];
        }
    }

    /**
     * Removes the filter for the given column
     *
     * @param $column
     */
    public function removeFilter($column)
    {
        $column = $this->resolveColumn($column);
        if (!$column) return;
        if (isset($this->filters[$column])) unset($this->filters[$column]);
    }

    /**
     * Remove all filter
     */
    public function clearFilters()
    {
        $this->filters = [];
    }

    /**
     * @return array the current filters
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Get the current parameters
     *
     * It creates a flat key value in a form that can be used to
     * create URLs or Form parameters
     *
     *
     * @return array
     */
    public function getURLParameters()
    {
        $params = [];
        if ($this->offset) {
            $params[self::$PARAM_OFFSET] = $this->offset;
        }

        if ($this->sort) {
            [$column, $asc] = $this->sort;
            if (!$asc) $column = "^$column";
            $params[self::$PARAM_SORT] = $column;
        }

        if ($this->filters) {
            foreach ($this->filters as $column => $filter) {
                [$comp, $value] = $filter;
                $key = self::$PARAM_FILTER . '[' . $column . $comp . ']';
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Applies the dynamic filter settings to the SearchConfig
     */
    public function apply()
    {
        if ($this->offset) {
            $this->searchConfig->setOffset($this->offset);
        }

        if ($this->sort) {
            $this->searchConfig->clearSort(); // remove any existing sort
            $this->searchConfig->addSort($this->sort[0], $this->sort[1]);
        }

        foreach ($this->filters as $colName => $filter) {
            [$comp, $value] = $filter;
            $this->searchConfig->addDynamicFilter($colName, $value, $comp, 'AND');
        }
    }
}
