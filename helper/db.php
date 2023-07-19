<?php

/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\ErrorHandler;
use dokuwiki\plugin\sqlite\SQLiteDB;
use dokuwiki\plugin\struct\meta\StructException;

class helper_plugin_struct_db extends DokuWiki_Plugin
{
    /** @var SQLiteDB */
    protected $sqlite;

    /**
     * Initialize the database
     *
     * @throws Exception
     */
    protected function init()
    {
        $this->sqlite = new SQLiteDB('struct', DOKU_PLUGIN . 'struct/db/');

        // register our JSON function with variable parameters
        $this->sqlite->getPdo()->sqliteCreateFunction('STRUCT_JSON', [$this, 'STRUCT_JSON'], -1);

        // this function is meant to be overwritten by plugins
        $this->sqlite->getPdo()->sqliteCreateFunction('IS_PUBLISHER', [$this, 'IS_PUBLISHER'], -1);
    }

    /**
     * @param bool $throw throw an Exception when sqlite not available
     * @return SQLiteDB|null
     */
    public function getDB($throw = true)
    {
        if ($this->sqlite === null) {
            try {
                $this->init();
            } catch (\Exception $exception) {
                if (defined('DOKU_UNITTEST')) throw new \RuntimeException('Could not load SQLite', 0, $exception);
                ErrorHandler::logException($exception);
                if ($throw) throw new StructException('no sqlite');
                return null;
            }
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
        $this->sqlite = null;
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
