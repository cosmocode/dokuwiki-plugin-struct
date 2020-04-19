<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\ConfigParser;
use dokuwiki\plugin\struct\test\mock\AccessTable;
use dokuwiki\plugin\struct\test\mock\AccessTableData;
use dokuwiki\plugin\struct\test\mock\LookupTable;
use dokuwiki\plugin\struct\test\mock\SearchConfig;
use dokuwiki\test\mock\Doku_Renderer;

/**
 * Testing serial data
 *
 * @group plugin_struct
 * @group plugins
 */
class DataSerial_struct_test extends StructTest
{
    public function setUp()
    {
        parent::setUp();
        $this->loadSchemaJSON('schema1');

        /** @var \helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');

        for ($i = 0; $i < 3; $i++) {
            // save wiki pages
            saveWikiText("test$i", "test$i", "test$i");

            // save serial data
            $data = [
                'first' => "foo$i",
                'second' => ["bar$i", "baz$i"],
                'third' => "foobar$i",
                'fourth' => "barfoo$i",
            ];
            $access = AccessTable::byTableName('schema1', "test$i");
            $access->saveData($data);
        }
    }

    /**
     * Test whether serial syntax produces a table of serial data limited to current page
     */
    public function test_pid()
    {
        // \syntax_plugin_struct_serial accesses the global $ID
        $ID = 'test1';

        // syntax to table
        $syntaxConfig = ['schema: schema1', 'cols: %pageid%, %rowid%, *'];
        $configParser = new ConfigParser($syntaxConfig);
        $config = $configParser->getConfig();
        // FIXME simulate addYypeFilter() from \syntax_plugin_struct_serial
        $config['filter'][] = ['%rowid%', '!=', (string)AccessTableData::DEFAULT_PAGE_RID, 'AND'];
        $config['filter'][] = ['%pageid%', '=', $ID, 'AND'];
        $search = new SearchConfig($config);

        $table = new LookupTable($ID, 'xhtml', new Doku_Renderer(), $search);
        $result = $table->getResult();

        $this->assertEquals(1, count($result));
        $this->assertEquals('test1', $result[0][0]->getValue());
        // skip %rowid% column and test saved values
        $this->assertEquals('foo1', $result[0][2]->getValue());
        $this->assertEquals(['bar1', 'baz1'], $result[0][3]->getValue());
        $this->assertEquals('foobar1', $result[0][4]->getValue());
        $this->assertEquals('barfoo1', $result[0][5]->getValue());
    }
}
