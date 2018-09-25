<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\Search;

/**
 * Testing the CSV exports of aggregations
 *
 * @group plugin_struct
 * @group plugins
 */
class ImportPageCSV extends StructTest
{

    public function setUp()
    {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
    }

    public function test_importExistingPageCSV()
    {
        $csvImporter = new mock\CSVPageImporter('schema1', '');
        $csvImporter->setTestData([
            ['pid', 'first', 'second', 'third', 'fourth'],
            ['wiki:syntax', 'e', 'f,i', 'g', 'h'],
        ]);
        $csvImporter->import();


        $schemaData = mock\AccessTable::byTableName('schema1', 'wiki:syntax');
        $actual_data = $schemaData->getDataFromDB();

        $expected_data = array(
            array(
                'PID' => 'wiki:syntax',
                'out1' => 'e',
                'out2' => 'f' . Search::CONCAT_SEPARATOR . 'i',
                'out3' => 'g',
                'out4' => 'h',
            ),
        );

        $this->assertSame($expected_data, $actual_data);
    }

    public function test_importNewPageCSV()
    {
        // arrange
        global $INPUT;
        $INPUT->set('createPage', true);
        $pageID = 'new:page';
        $csvImporter = new mock\CSVPageImporter('schema1', '');
        $csvImporter->setTestData([
            ['pid', 'first', 'third', 'second', 'fourth', 'fifth'],
            [$pageID, 'a', 'c', 'b,e', 'd',],
        ]);
        $templateFN = substr(wikiFN($pageID), 0, -1 * strlen('page.txt')) . '_template.txt';
        io_makeFileDir($templateFN);
        file_put_contents($templateFN,
'====== @PAGE@ ======
<ifnotempty schema1.first>
first: @@schema1.first@@
</ifnotempty>
second: ##schema1.second##
<ifnotempty fifth>
fifth: @@fifth@@
</ifnotempty>');

        // act
        $csvImporter->import();

        // assert
        $schemaData = mock\AccessTable::byTableName('schema1', $pageID);
        $actual_data = $schemaData->getDataFromDB();
        $expected_data = array(
            array(
                'PID' => $pageID,
                'out1' => 'a',
                'out2' => 'b' . Search::CONCAT_SEPARATOR . 'e',
                'out3' => 'c',
                'out4' => 'd',
            ),
        );
        $this->assertSame($expected_data, $actual_data);
        $this->assertTrue(page_exists($pageID));
        $expectedText = '====== page ======

first: a

second: b, e
';
        $text = file_get_contents(wikiFN($pageID));
        $this->assertSame($expectedText, $text);
    }
}
