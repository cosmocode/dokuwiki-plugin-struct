<?php

/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\Event;
use dokuwiki\plugin\struct\meta\StructException;

class helper_plugin_struct_db extends DokuWiki_Plugin
{
    /** @var \dokuwiki\plugin\sqlite\SQLiteDB */
    protected $sqlite;

    /**
     * helper_plugin_struct_db constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the database
     *
     * @throws Exception
     */
    protected function init()
    {
        try {
            /** @var \dokuwiki\plugin\sqlite\SQLiteDB $sqlite */
            $this->sqlite = new \dokuwiki\plugin\sqlite\SQLiteDB('struct', DOKU_PLUGIN . 'struct/db/');
        } catch (Exception $exception) {
            if (defined('DOKU_UNITTEST')) throw new \Exception('Couldn\'t load sqlite.');
            return;
        }

        // register our JSON function with variable parameters
        $this->sqlite->getDb()->sqliteCreateFunction('STRUCT_JSON', [$this, 'STRUCT_JSON'], -1);

        // this function is meant to be overwritten by plugins
        $this->sqlite->getDb()->sqliteCreateFunction('IS_PUBLISHER', [$this, 'IS_PUBLISHER'], -1);
    }

    /**
     * @param bool $throw throw an Exception when sqlite not available
     * @return \dokuwiki\plugin\sqlite\SQLiteDB|null
     */
    public function getDB($throw = true)
    {
        global $conf;
        $len = strlen($conf['metadir']);
        if ($this->sqlite && $conf['metadir'] != substr($this->sqlite->getDbFile(), 0, $len)) {
            $this->init();
        }
        if (!$this->sqlite && $throw) {
            throw new StructException('no sqlite');
        }
        return $this->sqlite;
    }

    /**
     * Completely remove the database and reinitialize it
     *
     * You do not want to call this except for testing!
     */
    public function resetDB()
    {
        if (!$this->sqlite) return;
        $file = $this->sqlite->getDbFile();
        if (!$file) return;
        unlink($file);
        clearstatcache(true, $file);
        $this->init();
    }

    /**
     * Encodes all given arguments into a JSON encoded array
     *
     * @param string ...
     * @return string
     */
    public function STRUCT_JSON() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $args = func_get_args();
        return json_encode($args);
    }

    /**
     * This dummy implementation can be overwritten by a plugin
     *
     * @return int
     */
    public function IS_PUBLISHER() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return 1;
    }
}

// vim:ts=4:sw=4:et:
