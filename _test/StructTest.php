<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\SchemaImporter;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\test\mock\Assignments;
use dokuwiki\plugin\struct\types\Text;

/**
 * Base class for all struct tests
 *
 * It cleans up the database in teardown and provides some useful helper methods
 *
 * @package dokuwiki\plugin\struct\test
 */
abstract class StructTest extends \DokuWikiTest
{

    /** @var array alway enable the needed plugins */
    protected $pluginsEnabled = array('struct', 'sqlite');

    /**
     * Default teardown
     *
     * we always make sure the database is clear
     */
    protected function tearDown(): void
    {
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
     */
    protected function loadSchemaJSON($schema, $json = '', $rev = 0)
    {
        if (!$json) $json = $schema;
        $file = __DIR__ . "/json/$json.struct.json";
        if (!file_exists($file)) {
            throw new \RuntimeException("$file does not exist");
        }

        $importer = new SchemaImporter($schema, file_get_contents($file));

        if (!$importer->build($rev)) {
            throw new \RuntimeException("build of $schema from $file failed");
        }
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
    protected function saveData($page, $table, $data, $rev = 0, $rid = 0)
    {
        saveWikiText($page, "test for $page", "saved for testing");
        if (AccessTable::isTypePage($page, $rev)) {
            $access = AccessTable::getPageAccess($table, $page, $rev);
        } elseif (AccessTable::isTypeSerial($page, $rev)) {
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
    protected function getLang($key)
    {
        static $lang = null;
        if (is_null($lang)) {
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
    protected function cleanWS($string)
    {
        return preg_replace(['/\s+/s', '/\:val(\d{1,3})/'], ['', '?'], $string);
    }

    /**
     * Create an Aggregation result set from a given flat array
     *
     * The result will contain simple Text columns
     *
     * @param array $rows
     * @return array
     */
    protected function createAggregationResult($rows)
    {
        $result = [];

        foreach ($rows as $row) {
            $resultRow = [];
            foreach ($row as $num => $cell) {
                $colRef = $num + 1;
                $resultRow[] = new Value(
                    new Column(
                        10,
                        new Text(['label' => ['en' => "Label $colRef"]], "field$colRef", is_array($cell)),
                        $colRef,
                        true,
                        'test'
                    ),
                    $cell
                );
            }
            $result[] = $resultRow;
        }

        return $result;
    }
}
