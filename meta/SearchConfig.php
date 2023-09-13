<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class SearchConfig
 *
 * The same as @see Search but can be initialized by a configuration array
 *
 * @package dokuwiki\plugin\struct\meta
 */
class SearchConfig extends Search
{
    /** @var int default aggregation caching (depends on last struct save) */
    public static $CACHE_DEFAULT = 1;
    /** @var int caching depends on current user */
    public static $CACHE_USER = 2;
    /** @var int caching depends on current date */
    public static $CACHE_DATE = 4;

    /**
     * @var array hold the configuration as parsed and extended by dynamic params
     */
    protected $config;

    /**
     * @var SearchConfigParameters manages dynamic parameters
     */
    protected $dynamicParameters;

    /**
     * @var int the cache flag to use (binary flags)
     */
    protected $cacheFlag;

    /**
     * SearchConfig constructor.
     * @param array $config The parsed configuration for this search
     * @param bool $dynamic Should dynamic parameters be applied?
     */
    public function __construct($config, $dynamic = true)
    {
        parent::__construct();

        // setup schemas and columns
        if (!empty($config['schemas'])) foreach ($config['schemas'] as $schema) {
            $this->addSchema($schema[0], $schema[1]);
        }
        if (!empty($config['cols'])) foreach ($config['cols'] as $col) {
            $this->addColumn($col);
        }

        // cache flag setting
        $this->cacheFlag = self::$CACHE_DEFAULT;
        if (!empty($config['filters'])) $this->cacheFlag = $this->determineCacheFlag($config['filters']);

        // configure search from configuration
        if (!empty($config['filter'])) foreach ($config['filter'] as $filter) {
            $this->addFilter($filter[0], $this->applyFilterVars($filter[2]), $filter[1], $filter[3]);
        }

        if (!empty($config['sort'])) foreach ($config['sort'] as $sort) {
            $this->addSort($sort[0], $sort[1]);
        }

        if (!empty($config['limit'])) {
            $this->setLimit($config['limit']);
        }

        if (!empty($config['offset'])) {
            $this->setOffset($config['offset']);
        }

        // prepare dynamic parameters
        $this->dynamicParameters = new SearchConfigParameters($this);
        if ($dynamic) {
            $this->dynamicParameters->apply();
        }

        $this->config = $config;
    }

    /**
     * Set the cache flag accordingly to the set filter placeholders
     *
     * @param array $filters
     * @return int
     */
    protected function determineCacheFlag($filters)
    {
        $flags = self::$CACHE_DEFAULT;

        foreach ($filters as $filter) {
            if (is_array($filter)) $filter = $filter[2]; // this is the format we get fro the config parser

            if (strpos($filter, '$USER$') !== false) {
                $flags |= self::$CACHE_USER;
            } elseif (strpos($filter, '$TODAY$') !== false) {
                $flags |= self::$CACHE_DATE;
            }
        }

        return $flags;
    }

    /**
     * Replaces placeholders in the given filter value by the proper value
     *
     * @param string $filter
     * @return string|string[] Result may be an array when a multi column placeholder is used
     */
    protected function applyFilterVars($filter)
    {
        global $INPUT;
        global $INFO;
        if (!isset($INFO['id'])) {
            $INFO['id'] = '';
        }

        // apply inexpensive filters first
        $filter = str_replace(
            [
                '$ID$',
                '$NS$',
                '$PAGE$',
                '$USER$',
                '$TODAY$'
            ],
            [
                $INFO['id'],
                getNS($INFO['id']),
                noNS($INFO['id']),
                $INPUT->server->str('REMOTE_USER'),
                date('Y-m-d')
            ],
            $filter
        );

        // apply struct column placeholder (we support only one!)
        // or apply date formula, given as strtotime
        if (preg_match('/^(.*?)(?:\$STRUCT\.(.*?)\$)(.*?)$/', $filter, $match)) {
            $filter = $this->applyFilterVarsStruct($match);
        } elseif (preg_match('/^(.*?)(?:\$USER\.(.*?)\$)(.*?)$/', $filter, $match)) {
            $filter = $this->applyFilterVarsUser($match);
        } elseif (preg_match('/^(.*?)(?:\$DATE\((.*?)\)\$?)(.*?)$/', $filter, $match)) {
            $toparse = $match[2];
            if ($toparse == '') {
                $toparse = 'now';
            }
            $timestamp = strtotime($toparse);
            if ($timestamp === false) {
                throw new StructException('datefilter', hsc($toparse));
            } else {
                $filter = str_replace($filter, date('Y-m-d', $timestamp), $filter);
            }
        }

        return $filter;
    }

    /**
     * Replaces struct placeholders in the given filter value by the proper value
     *
     * @param string $match
     * @return string|string[] Result may be an array when a multi column placeholder is used
     */
    protected function applyFilterVarsStruct($match)
    {
        global $INFO;

        $key = $match[2];

        // we try to resolve the key via the assigned schemas first, otherwise take it literally
        $column = $this->findColumn($key, true);
        if ($column) {
            $label = $column->getLabel();
            $table = $column->getTable();
        } else {
            [$table, $label] = sexplode('.', $key, 2, '');
        }

        // get the data from the current page
        if ($table && $label) {
            $schemaData = AccessTable::getPageAccess($table, $INFO['id']);
            $data = $schemaData->getData();
            if (!isset($data[$label])) {
                throw new StructException("column not in table", $label, $table);
            }
            $value = $data[$label]->getCompareValue();

            if (is_array($value) && $value === []) {
                $value = '';
            }
        } else {
            $value = '';
        }

        // apply any pre and postfixes, even when multi value
        if (is_array($value)) {
            $filter = [];
            foreach ($value as $item) {
                $filter[] = $match[1] . $item . $match[3];
            }
        } else {
            $filter = $match[1] . $value . $match[3];
        }

        return $filter;
    }

    /**
     * Replaces user placeholders in the given filter value by the proper value
     *
     * @param string $match
     * @return string|string[] String for name and mail, array for grps
     */
    protected function applyFilterVarsUser($match)
    {
        global $INFO;

        $key = strtolower($match[2]);

        if (!in_array($key, ['name', 'mail', 'grps'])) {
            throw new StructException('"%s" is not a valid USER key', $key);
        }

        if (empty($INFO['userinfo'])) {
            $filter = '';
        } else {
            $filter = $INFO['userinfo'][$key];
        }

        return $filter;
    }

    /**
     * @return int cacheflag for this search
     */
    public function getCacheFlag()
    {
        return $this->cacheFlag;
    }

    /**
     * Access the dynamic parameters of this search
     *
     * Note: This call returns a clone of the parameters as they were initialized
     *
     * @return SearchConfigParameters
     */
    public function getDynamicParameters()
    {
        return clone $this->dynamicParameters;
    }

    /**
     * Get the config this search was initialized with
     *
     * Note that the search may have been modified by dynamic parameters or additional member calls
     *
     * @return array
     */
    public function getConf()
    {
        return $this->config;
    }
}
