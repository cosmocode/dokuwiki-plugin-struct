<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;
use dokuwiki\plugin\struct\meta\Search;

/**
 * Tests to the DB for the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class AccessTableDataDB_struct_test extends StructTest {

    /** @var \helper_plugin_sqlite $sqlite */
    protected $sqlite;

    public function setUp() {
        parent::setUp();

        /** @var \helper_plugin_struct_db $sqlite */
        $sqlite = plugin_load('helper', 'struct_db');
        $this->sqlite = $sqlite->getDB();

        $this->loadSchemaJSON('testtable', '', 100);

        // revision 1
        $this->saveData(
            'testpage',
            'testtable',
            array(
                'testcolumn' => 'value1',
                'testMulitColumn' => array('value2.1', 'value2.2')
            ),
            123
        );

        // revision 2
        $this->saveData(
            'testpage',
            'testtable',
            array(
                'testcolumn' => 'value1a',
                'testMulitColumn' => array('value2.1a', 'value2.2a')
            ),
            789
        );

        // revision 1 of different page
        $this->saveData(
            'testpage2',
            'testtable',
            array(
                'testcolumn' => 'value1a',
                'testMulitColumn' => array('value2.1a')
            ),
            789
        );
    }

    public function test_getDataFromDB_currentRev() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage');
        $actual_data = $schemaData->getDataFromDb();

        $expected_data = array(
            array(
                'out1' => 'value1a',
                'out2' => 'value2.1a' . Search::CONCAT_SEPARATOR . 'value2.2a',
                'PID' => 'testpage',
            ),
        );

        $this->assertEquals($expected_data, $actual_data);
    }

    public function test_getDataFromDB_oldRev() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage', 200);
        $actual_data = $schemaData->getDataFromDB();

        $expected_data = array(
            array(
                'out1' => 'value1',
                'out2' => 'value2.1' . Search::CONCAT_SEPARATOR . 'value2.2',
                'PID' => 'testpage',
            ),
        );

        $this->assertEquals($expected_data, $actual_data, '');
    }

    public function test_getData_currentRev() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage');
        $actual_data = $schemaData->getData();

        $expected_data = array(
            'testMulitColumn' => array('value2.1a', 'value2.2a'),
            'testcolumn' => 'value1a',
        );

        // assert
        foreach($expected_data as $key => $value) {
            $this->assertEquals($value, $actual_data[$key]->getValue());
        }
    }

    public function test_getDataArray_currentRev() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage');
        $actual_data = $schemaData->getDataArray();

        $expected_data = array(
            'testMulitColumn' => array('value2.1a', 'value2.2a'),
            'testcolumn' => 'value1a'
        );

        // assert
        $this->assertEquals($expected_data, $actual_data, '');
    }

    public function test_getData_currentRev2() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage2');
        $actual_data = $schemaData->getData();

        $expected_data = array(
            'testMulitColumn' => array('value2.1a'),
            'testcolumn' => 'value1a',
        );

        // assert
        foreach($expected_data as $index => $value) {
            $this->assertEquals($value, $actual_data[$index]->getValue());
        }
    }

    public function test_getData_oldRev() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage', 200);
        $actual_data = $schemaData->getData();

        $expected_data = array(
            'testMulitColumn' => array('value2.1', 'value2.2'),
            'testcolumn' => 'value1',
        );

        // assert
        foreach($expected_data as $index => $value) {
            $this->assertEquals($value, $actual_data[$index]->getValue());
        }
    }

    public function test_saveData() {
        // arrange
        $testdata = array(
            'testcolumn' => 'value1_saved',
            'testMulitColumn' => array(
                "value2.1_saved",
                "value2.2_saved",
                "value2.3_saved",
            )
        );

        // act
        $schemaData = meta\AccessTable::getPageAccess('testtable', 'testpage');
        $result = $schemaData->saveData($testdata);

        // assert
        /** @noinspection SqlResolve */
        $res = $this->sqlite->query("SELECT pid, col1, col2 FROM data_testtable WHERE pid = ? ORDER BY rev DESC LIMIT 1", array('testpage'));
        $actual_saved_single = $this->sqlite->res2row($res);
        $expected_saved_single = array(
            'pid' => 'testpage',
            'col1' => 'value1_saved',
            'col2' => 'value2.1_saved' # copy of the multi-value's first value
        );

        /** @noinspection SqlResolve */
        $res = $this->sqlite->query("SELECT colref, row, value FROM multi_testtable WHERE pid = ? ORDER BY rev DESC LIMIT 3", array('testpage'));
        $actual_saved_multi = $this->sqlite->res2arr($res);
        $expected_saved_multi = array(
            array(
                'colref' => '2',
                'row' => '1',
                'value' => "value2.1_saved"
            ),
            array(
                'colref' => '2',
                'row' => '2',
                'value' => "value2.2_saved"
            ),
            array(
                'colref' => '2',
                'row' => '3',
                'value' => "value2.3_saved"
            )
        );

        $this->assertTrue($result, 'should be true on success');
        $this->assertEquals($expected_saved_single, $actual_saved_single, 'single value fields');
        $this->assertEquals($expected_saved_multi, $actual_saved_multi, 'multi value fields');
    }

    public function test_getDataFromDB_clearData() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage');
        $schemaData->clearData();
        $actual_data = $schemaData->getDataFromDB();

        $expected_data = array(
            array(
                'out1' => '',
                'out2' => null,
                'PID' => 'testpage',
            )
        );

        $this->assertEquals($expected_data, $actual_data, '');
    }

    public function test_getData_clearData() {

        // act
        $schemaData = mock\AccessTable::getPageAccess('testtable', 'testpage');
        $schemaData->clearData();
        $actual_data = $schemaData->getData();

        // assert
        $this->assertEquals(array(), $actual_data['testMulitColumn']->getValue());
        $this->assertEquals(null, $actual_data['testcolumn']->getValue());
    }

    public function test_getData_skipEmpty() {
        // arrange
        $testdata = array(
            'testcolumn' => '',
            'testMulitColumn' => array(
                "value2.1_saved",
                "value2.2_saved",
            )
        );
        $schemaData = meta\AccessTable::getPageAccess('testtable', 'testpage');
        $schemaData->saveData($testdata);

        // act
        $schemaData->optionSkipEmpty(true);
        $actual_data = $schemaData->getData();

        $expected_data = array('value2.1_saved', 'value2.2_saved');

        // assert
        $this->assertEquals(1, count($actual_data), 'There should be only one value returned and the empty value skipped');
        $this->assertEquals($expected_data, $actual_data['testMulitColumn']->getValue());
    }

    public function test_getDataArray_skipEmpty() {
        // arrange
        $testdata = array(
            'testcolumn' => '',
            'testMulitColumn' => array(
                "value2.1_saved",
                "value2.2_saved",
            )
        );
        $schemaData = meta\AccessTable::getPageAccess('testtable', 'testpage');
        $schemaData->saveData($testdata);

        // act
        $schemaData->optionSkipEmpty(true);
        $actual_data = $schemaData->getDataArray();

        $expected_data = array(
            'testMulitColumn' => array('value2.1_saved', 'value2.2_saved')
        );

        // assert
        $this->assertEquals(1, count($actual_data), 'There should be only one value returned and the empty value skipped');
        $this->assertEquals($expected_data, $actual_data);
    }

    public function test_pseudodiff() {
        $this->loadSchemaJSON('pageschema');
        $this->saveData(
            'syntax',
            'pageschema',
            array(
                'singlepage' => 'wiki:dokuwiki',
                'multipage' => array('wiki:dokuwiki', 'wiki:syntax', 'wiki:welcome'),
                'singletitle' => 'wiki:dokuwiki',
                'multititle' => array('wiki:dokuwiki', 'wiki:syntax', 'wiki:welcome'),
            )
        );

        // make sure titles for some pages are known (not for wiki:welcome)
        $pageMeta = new \dokuwiki\plugin\struct\meta\PageMeta('wiki:dokuwiki');
        $pageMeta->setTitle('DokuWiki Overview');
        $pageMeta = new \dokuwiki\plugin\struct\meta\PageMeta('wiki:syntax');
        $pageMeta->setTitle('DokuWiki Foobar Syntax');
        $pageMeta->savePageData();

        $schemaData = meta\AccessTable::getPageAccess('pageschema', 'syntax');
        $actual_pseudodiff = $schemaData->getDataPseudoSyntax();
        $expected_pseudodiff = "pageschema.singlepage : wiki:dokuwiki
pageschema.multipage : wiki:dokuwiki, wiki:syntax, wiki:welcome
pageschema.singletitle : DokuWiki Overview
pageschema.multititle : DokuWiki Overview, DokuWiki Foobar Syntax, wiki:welcome\n";

        $this->assertEquals($expected_pseudodiff, $actual_pseudodiff);
    }
}
