<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\ConfigParser;
use dokuwiki\plugin\struct\meta\PageMeta;
use dokuwiki\plugin\struct\test\mock\AccessTable;
use dokuwiki\plugin\struct\test\mock\AccessTablePage;
use dokuwiki\plugin\struct\test\mock\AggregationTable;
use dokuwiki\plugin\struct\test\mock\AggregationEditorTable;
use dokuwiki\plugin\struct\test\mock\SearchConfig;

/**
 * Testing serial data
 *
 * @group plugin_struct
 * @group plugins
 */
class AggregationResults_struct_test extends StructTest
{
    protected $sqlite;

    public function setUp()
    {
        parent::setUp();

        $sqlite = plugin_load('helper', 'struct_db');
        $this->sqlite = $sqlite->getDB();

        $this->loadSchemaJSON('schema1');

        $assignments = mock\Assignments::getInstance();
        $assignments->clear(true);

        for ($i = 0; $i < 3; $i++) {
            // assign a schema
            $assignments->assignPageSchema("test$i", 'schema1');

            // save wiki pages
            saveWikiText("test$i", "test$i", "test$i");

            // save serial data
            $data = [
                'first' => "foo$i",
                'second' => ["bar$i", "baz$i"],
                'third' => "foobar$i",
                'fourth' => "barfoo$i",
            ];
            $access = AccessTable::getSerialAccess('schema1', "test$i");
            $access->saveData($data);
        }
    }

    /**
     * Test whether serial syntax produces a table of serial data limited to current page
     */
    public function test_pid()
    {
        // \syntax_plugin_struct_serial accesses the global $ID
        $id = 'test1';
        $schema = 'schema1';
        $result = $this->fetchResult($schema, $id, []);

        $this->assertEquals(1, count($result));
        $this->assertEquals('test1', $result[0][0]->getValue());
        // skip %rowid% column and test saved values
        $this->assertEquals('foo1', $result[0][2]->getValue());
        $this->assertEquals(['bar1', 'baz1'], $result[0][3]->getValue());
        $this->assertEquals('foobar1', $result[0][4]->getValue());
        $this->assertEquals('barfoo1', $result[0][5]->getValue());
    }

    /**
     * Test simple text filter
     */
    public function test_filter_text()
    {
        $schema = 'schema1';
        $result = $this->fetchResult($schema, 'test0');
        $this->assertEquals(1, count($result));

        $result = $this->fetchResult($schema, 'test0', ['first', '=', 'foo0', 'AND']);
        $this->assertEquals(1, count($result));

        $result = $this->fetchResult($schema,'test0', ['first', '!=', 'foo0', 'AND']);
        $this->assertEquals(0, count($result));
    }

    /**
     * Test filtering on a page field, with 'usetitles' set to true and false
     */
    public function test_filter_page()
    {
        $this->prepareLookup();
        $schema = 'pageschema';
        $result = $this->fetchResult($schema);
        $this->assertEquals(3, count($result));

        // 'usetitles' = true
        $result = $this->fetchResult($schema, '', ['singletitle', '*~', 'another', 'AND']);
        $this->assertEquals(1, count($result));

        // 'usetitles' = false
        $result = $this->fetchResult($schema, '', ['singlepage', '*~', 'this', 'AND']);
        $this->assertEquals(0, count($result));
    }

    /**
     * Test whether aggregation tables respect revoking of schema assignments
     */
    public function test_assignments()
    {
        $result = $this->fetchPagesResult('schema1');
        $this->assertEquals(3, count($result));

        // revoke assignment
        $assignments = mock\Assignments::getInstance();
        $assignments->deassignPageSchema('test0', 'schema1');

        $result = $this->fetchPagesResult('schema1');
        $this->assertEquals(2, count($result));
    }


    /**
     * Initialize a lookup table from syntax and return the result from its internal search.
     *
     * @param string $schema
     * @param string $id
     * @param array $filters
     * @return \dokuwiki\plugin\struct\meta\Value[][]
     */
    protected function fetchPagesResult($schema, $id = '', $filters = [])
    {
        $syntaxConfig = ['schema: ' . $schema, 'cols: %pageid%, %rowid%, *'];
        $configParser = new ConfigParser($syntaxConfig);
        $config = $configParser->getConfig();

        if ($filters) array_push($config['filter'], $filters);
        $search = new SearchConfig($config);

        $table = new AggregationTable($id, 'xhtml', new \Doku_Renderer_xhtml(), $search);
        return $table->getResult();
    }

    /**
     * Initialize a lookup table from syntax and return the result from its internal search.
     *
     * @param string $schema
     * @param string $id
     * @param array $filters
     * @return \dokuwiki\plugin\struct\meta\Value[][]
     */
    protected function fetchResult($schema, $id = '', $filters = [])
    {
        $syntaxConfig = ['schema: ' . $schema, 'cols: %pageid%, %rowid%, *'];
        $configParser = new ConfigParser($syntaxConfig);
        $config = $configParser->getConfig();

        // FIXME simulate addYypeFilter() from \syntax_plugin_struct_serial or \syntax_plugin_struct_lookup
        if ($id) {
            $config['filter'][] = ['%rowid%', '!=', (string)AccessTablePage::DEFAULT_PAGE_RID, 'AND'];
            $config['filter'][] = ['%pageid%', '=', $id, 'AND'];
        } else {
            $config['filter'][] = ['%rowid%', '!=', (string)\dokuwiki\plugin\struct\meta\AccessTablePage::DEFAULT_PAGE_RID, 'AND'];
            $config['filter'][] = ['%pageid%', '=*', '^(?![\s\S])', 'AND'];
        }

        if ($filters) array_push($config['filter'], $filters);
        $search = new SearchConfig($config);

        $table = new AggregationEditorTable($id, 'xhtml', new \Doku_Renderer_xhtml(), $search);
        return $table->getResult();
    }

    protected function prepareLookup()
    {
        saveWikiText('title1', 'test', 'test');
        $pageMeta = new PageMeta('title1');
        $pageMeta->setTitle('This is a title');

        saveWikiText('title2', 'test', 'test');
        $pageMeta = new PageMeta('title2');
        $pageMeta->setTitle('This is a 2nd title');

        saveWikiText('title3', 'test', 'test');
        $pageMeta = new PageMeta('title3');
        $pageMeta->setTitle('Another Title');

        $this->loadSchemaJSON('pageschema');
        $access = AccessTable::getGlobalAccess('pageschema');
        $access->saveData(
            array(
                'singlepage' => 'title1',
                'multipage' => array('title1'),
                'singletitle' => 'title1',
                'multititle' => array('title1'),
            )
        );
        $access = AccessTable::getGlobalAccess('pageschema');
        $access->saveData(
            array(
                'singlepage' => 'title2',
                'multipage' => array('title2'),
                'singletitle' => 'title2',
                'multititle' => array('title2'),
            )
        );
        $access = AccessTable::getGlobalAccess('pageschema');
        $access->saveData(
            array(
                'singlepage' => 'title3',
                'multipage' => array('title3'),
                'singletitle' => 'title3',
                'multititle' => array('title3'),
            )
        );
    }
}
