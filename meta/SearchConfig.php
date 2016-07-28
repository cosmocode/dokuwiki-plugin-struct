<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class SearchConfig
 *
 * The same as @see Search but can be initialized by a configuration array
 *
 * @package dokuwiki\plugin\struct\meta
 */
class SearchConfig extends Search {

    /** @var int default aggregation caching (depends on last struct save) */
    static public $CACHE_DEFAULT = 1;
    /** @var int caching depends on current user */
    static public $CACHE_USER = 2;
    /** @var int caching depends on current date */
    static public $CACHE_DATE = 4;

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
     */
    public function __construct($config) {
        parent::__construct();

        // setup schemas and columns
        if(!empty($config['schemas'])) foreach($config['schemas'] as $schema) {
            $this->addSchema($schema[0], $schema[1]);
        }
        if(!empty($config['cols'])) foreach($config['cols'] as $col) {
            $this->addColumn($col);
        }

        // cache flag setting
        $this->cacheFlag = self::$CACHE_DEFAULT;
        if(!empty($config['filters'])) $this->cacheFlag = $this->determineCacheFlag($config['filters']);

        // apply dynamic paramters
        $this->dynamicParameters = new SearchConfigParameters($this);
        $config = $this->dynamicParameters->updateConfig($config);

        // configure search from configuration
        if(!empty($config['filter'])) foreach($config['filter'] as $filter) {
            $this->addFilter($filter[0], $this->applyFilterVars($filter[2]), $filter[1], $filter[3]);
        }

        if(!empty($config['sort'])) foreach($config['sort'] as $sort) {
            $this->addSort($sort[0], $sort[1]);
        }

        if(!empty($config['limit'])) {
            $this->setLimit($config['limit']);
        }

        if(!empty($config['offset'])) {
            $this->setLimit($config['offset']);
        }

        $this->config = $config;
    }

    /**
     * Set the cache flag accordingly to the set filter placeholders
     *
     * @param array $filters
     * @return int
     */
    protected function determineCacheFlag($filters) {
        $flags = self::$CACHE_DEFAULT;

        foreach($filters as $filter) {
            if(is_array($filter)) $filter = $filter[2]; // this is the format we get fro the config parser

            if(strpos($filter, '$USER$') !== false) {
                $flags |= self::$CACHE_USER;
            } else if(strpos($filter, '$TODAY$') !== false) {
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
    protected function applyFilterVars($filter) {
        global $ID;

        // apply inexpensive filters first
        $filter = str_replace(
            array(
                '$ID$',
                '$NS$',
                '$PAGE$',
                '$USER$',
                '$TODAY$'
            ),
            array(
                $ID,
                getNS($ID),
                noNS($ID),
                isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : '',
                date('Y-m-d')
            ),
            $filter
        );

        // apply struct column placeholder (we support only one!)
        if(preg_match('/^(.*?)(?:\$STRUCT\.(.*?)\$)(.*?)$/', $filter, $match)) {
            $key = $match[2];

            // we try to resolve the key via current schema aliases first, otherwise take it literally
            $column = $this->findColumn($key);
            if($column) {
                $label = $column->getLabel();
                $table = $column->getTable();
            } else {
                list($table, $label) = explode('.', $key);
            }

            // get the data from the current page
            if($table && $label) {
                $schemaData = new SchemaData($table, $ID, 0);
                $schemaData->optionRawValue(true);
                $data = $schemaData->getDataArray();
                $value = $data[$label];
            } else {
                $value = '';
            }

            // apply any pre and postfixes, even when multi value
            if(is_array($value)) {
                $filter = array();
                foreach($value as $item) {
                    $filter[] = $match[1] . $item . $match[3];
                }
            } else {
                $filter = $match[1] . $value . $match[3];
            }
        }

        return $filter;
    }

    /**
     * @return int cacheflag for this search
     */
    public function getCacheFlag() {
        return $this->cacheFlag;
    }

    /**
     * Access the dynamic paramters of this search
     *
     * Note: This call returns a clone of the parameters as they were initialized
     *
     * @return SearchConfigParameters
     */
    public function getDynamicParameters() {
        return clone $this->dynamicParameters;
    }

    /**
     * @return array the current config
     */
    public function getConf() {
        return $this->config;
    }

}
