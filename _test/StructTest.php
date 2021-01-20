<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\test\mock\AccessTablePage;
use dokuwiki\plugin\struct\test\mock\Assignments;
use dokuwiki\plugin\struct\meta\SchemaImporter;

/**
 * Base class for all struct tests
 *
 * It cleans up the database in teardown and provides some useful helper methods
 *
 * @package dokuwiki\plugin\struct\test
 */
abstract class StructTest extends \DokuWikiTest {

    /** @var array alway enable the needed plugins */
    protected $pluginsEnabled = array('struct', 'sqlite');

    /**
     * Default teardown
     *
     * we always make sure the database is clear
     */
    protected function tearDown() {
        parent::tearDown();
        /** @var \helper_plugin_struct_db $db */
        $db = plugin_load('helper', 'struct_db');
        $db->resetDB();
        Assignments::reset();
    }

    /**
     * Creates a schema from one of the available schema files
     *
     * @param string $schema
     * @param string $json base name of the JSON file optional, defaults to $schema
     * @param int $rev allows to create schemas back in time
     * @param bool $lookup create as a lookup schema
     */
    protected function loadSchemaJSON($schema, $json = '', $rev = 0) {
        if(!$json) $json = $schema;
        $file = __DIR__ . "/json/$json.struct.json";
        if(!file_exists($file)) {
            throw new \RuntimeException("$file does not exist");
        }

        $importer = new SchemaImporter($schema, file_get_contents($file));

        if(!$importer->build($rev)) {
            throw new \RuntimeException("build of $schema from $file failed");
        }
    }

    /**
     * This waits until a new second has passed
     *
     * The very first call will return immeadiately, proceeding calls will return
     * only after at least 1 second after the last call has passed.
     *
     * When passing $init=true it will not return immeadiately but use the current
     * second as initialization. It might still return faster than a second.
     *
     * @param bool $init wait from now on, not from last time
     * @return int new timestamp
     */
    protected function waitForTick($init = false) {
        // this will be in DokuWiki soon
        if (is_callable('parent::waitForTick')) {
            return parent::waitForTick($init);
        }

        static $last = 0;
        if($init) $last = time();

        while($last === $now = time()) {
            usleep(100000); //recheck in a 10th of a second
        }
        $last = $now;
        return $now;
    }

    /**
     * Saves struct data for given page and schema
     *
     * Please note that setting the $rev only influences the struct data timestamp,
     * not the page and changelog entries.
     *
     * @param string $page
     * @param string $table
     * @param array $data
     * @param int $rev allows to override the revision timestamp
     * @param int $rid
     */
    protected function saveData($page, $table, $data, $rev = 0, $rid = 0) {
        saveWikiText($page, "test for $page", "saved for testing");
        if (AccessTable::isTypePage($page, $rev)) {
            $access = AccessTable::getPageAccess($table, $page, $rev);
        } elseif(AccessTable::isTypeSerial($page, $rev)) {
            $access = AccessTable::getSerialAccess($table, $page);
        } else {
            $access = AccessTable::getGlobalAccess($table, $rid);
        }
        $access->saveData($data);
        $assignments = Assignments::getInstance();
        $assignments->assignPageSchema($page, $table);
    }

    /**
     * Access the plugin's English language strings
     *
     * @param string $key
     * @return string
     */
    protected function getLang($key) {
        static $lang = null;
        if(is_null($lang)) {
            $lang = array();
            include(DOKU_PLUGIN . 'struct/lang/en/lang.php');
        }
        return $lang[$key];
    }

    /**
     * Removes Whitespace
     *
     * Makes comparing sql statements a bit simpler as it ignores formatting
     *
     * @param $string
     * @return string
     */
    protected function cleanWS($string) {
        return preg_replace('/\s+/s', '', $string);
    }
}
