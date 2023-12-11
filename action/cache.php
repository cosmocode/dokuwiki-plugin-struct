<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\SearchConfigParameters;

/**
 * Handle caching of pages containing struct aggregations
 */
class action_plugin_struct_cache extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handleCacheSchemachange');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handleCacheAggregation');
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'handleCacheDynamic');
    }

    /**
     * @return string the refresh file
     */
    public static function getSchemaRefreshFile()
    {
        global $conf;
        return $conf['cachedir'] . '/struct.schemarefresh';
    }

    /**
     * For pages potentially containing schema data, refresh the cache when schema data has been
     * updated
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleCacheSchemachange(Event $event, $param)
    {
        /** @var \cache_parser $cache */
        $cache = $event->data;
        if ($cache->mode != 'xhtml') return true;
        if (!$cache->page) return true; // not a page cache

        $assignments = Assignments::getInstance();
        if (!$assignments->getPageAssignments($cache->page)) return true; // no struct here

        $cache->depends['files'][] = self::getSchemaRefreshFile();
        return true;
    }

    /**
     * For pages containing an aggregation, add the last modified date of the database itself
     * to the cache dependencies
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleCacheAggregation(Event $event, $param)
    {
        global $INPUT;

        /** @var \cache_parser $cache */
        $cache = $event->data;
        if ($cache->mode != 'xhtml') return true;
        if (!$cache->page) return true; // not a page cache

        $meta = p_get_metadata($cache->page, 'plugin struct');
        if (isset($meta['hasaggregation'])) {
            /** @var helper_plugin_struct_db $db */
            $db = plugin_load('helper', 'struct_db');
            // cache depends on last database save
            $sqlite = $db->getDB(false);
            if ($sqlite instanceof SQLiteDB) {
                $cache->depends['files'][] = $sqlite->getDbFile();
            }

            // dynamic renders should never overwrite the default page cache
            // we need this in additon to handle_cache_dynamic() below because we can only
            // influence if a cache is used, not that it will be written
            if (
                $INPUT->has(SearchConfigParameters::$PARAM_FILTER) ||
                $INPUT->has(SearchConfigParameters::$PARAM_OFFSET) ||
                $INPUT->has(SearchConfigParameters::$PARAM_SORT)
            ) {
                $cache->key .= 'dynamic';
            }

            // cache depends on today's date
            if ($meta['hasaggregation'] & SearchConfig::$CACHE_DATE) {
                $oldage = $cache->depends['age'];
                $newage = time() - mktime(0, 0, 1); // time since first second today
                $cache->depends['age'] = min($oldage, $newage);
            }

            // cache depends on current user
            if ($meta['hasaggregation'] & SearchConfig::$CACHE_USER) {
                $cache->key .= ';' . $INPUT->server->str('REMOTE_USER');
            }

            // rebuild cachename
            $cache->cache = getCacheName($cache->key, $cache->ext);
        }

        return true;
    }

    /**
     * Disable cache when dymanic parameters are present
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleCacheDynamic(Event $event, $param)
    {
        /** @var \cache_parser $cache */
        $cache = $event->data;
        if ($cache->mode != 'xhtml') return true;
        if (!$cache->page) return true; // not a page cache
        global $INPUT;

        // disable cache use when one of these parameters is present
        foreach (
            [
                SearchConfigParameters::$PARAM_FILTER,
                SearchConfigParameters::$PARAM_OFFSET,
                SearchConfigParameters::$PARAM_SORT
            ] as $key
        ) {
            if ($INPUT->has($key)) {
                $event->result = false;
                return true;
            }
        }

        return true;
    }
}
