<?php

namespace dokuwiki\plugin\struct\test\action;

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\test\StructTest;

/**
 * @covers action_plugin_struct_aggregationeditor
 *
 * @group plugin_struct
 * @group plugins
 * @group integration
 */
class LookupAjaxAction extends StructTest
{
    public function setUp()
    {
        parent::setUp();

        $this->loadSchemaJSON('wikilookup', '', 0);

        /** @var \helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');

        $saveDate = [
            'FirstFieldText' => 'abc def',
            'SecondFieldLongText' => "abc\ndef\n",
            'ThirdFieldWiki' => "  * hi\n  * ho",
        ];
        $access = AccessTable::getGlobalAccess('wikilookup');
        $helper->saveLookupData($access, $saveDate);
    }

    public function testSaveGlobalDataEvent()
    {
        $testLabel = 'testcontent';
        global $INPUT;
        $INPUT->post->set('schema', 'wikilookup');
        $INPUT->post->set('entry', ['FirstFieldText' => $testLabel]);
        $INPUT->post->set('searchconf', json_encode([
            'schemas' => [['wikilookup', '']],
            'cols' => ['*']
        ]));
        $call = 'plugin_struct_aggregationeditor_save';
        $evt = new \Doku_Event('AJAX_CALL_UNKNOWN', $call);

        $this->expectOutputRegex('/\s*<tr.*' . $testLabel . '.*<\/td>\s*/');

        $evt->advise_before();
    }
}
