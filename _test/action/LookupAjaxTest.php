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
class LookupAjaxTest extends StructTest
{
    public function setUp(): void
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

    public function testSaveSerialDataDeniedWithoutPagePermission()
    {
        $page = 'serial:readonly';
        saveWikiText($page, 'test page', 'test');
        $this->denyPageEditing();

        global $INPUT;
        $INPUT->post->set('schema', 'wikilookup');
        $INPUT->post->set('pid', $page);
        $INPUT->post->set('entry', ['FirstFieldText' => 'injected']);
        $INPUT->post->set('searchconf', json_encode([
            'schemas' => [['wikilookup', '']],
            'cols' => ['*']
        ]));
        $call = 'plugin_struct_aggregationeditor_save';
        $evt = new \Doku_Event('AJAX_CALL_UNKNOWN', $call);

        $this->expectOutputRegex('/page edit error: acl/');

        $evt->advise_before();

        /** @var \helper_plugin_struct_db $db */
        $db = plugin_load('helper', 'struct_db');
        $this->assertEquals(
            0,
            $db->getDB()->queryValue('SELECT COUNT(*) FROM data_wikilookup WHERE pid = ?', $page)
        );
    }

    public function testDeleteSerialDataDeniedWithoutPagePermission()
    {
        $page = 'serial:readonly';
        saveWikiText($page, 'test page', 'test');

        /** @var \helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        $access = AccessTable::getSerialAccess('wikilookup', $page);
        $helper->saveLookupData($access, ['FirstFieldText' => 'keep me']);
        $rid = $access->getRid();

        $this->denyPageEditing();

        global $INPUT;
        $INPUT->post->set('schema', 'wikilookup');
        $INPUT->post->set('pid', $page);
        $INPUT->post->set('rid', $rid);
        $call = 'plugin_struct_aggregationeditor_delete';
        $evt = new \Doku_Event('AJAX_CALL_UNKNOWN', $call);

        $this->expectOutputRegex('/page edit error: acl/');

        $evt->advise_before();

        $access = AccessTable::getSerialAccess('wikilookup', $page, $rid);
        $this->assertEquals('keep me', $access->getDataArray()['FirstFieldText']);
    }

    public function testDeleteSerialDataKeepsRowsOfOtherPages()
    {
        $page = 'serial:owner';
        $claimed = 'serial:claimed';
        saveWikiText($page, 'test page', 'test');
        saveWikiText($claimed, 'test page', 'test');

        /** @var \helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        $access = AccessTable::getSerialAccess('wikilookup', $page);
        $helper->saveLookupData($access, ['FirstFieldText' => 'keep me']);
        $rid = $access->getRid();

        global $INPUT;
        $INPUT->post->set('schema', 'wikilookup');
        $INPUT->post->set('pid', $claimed);
        $INPUT->post->set('rid', $rid);
        $call = 'plugin_struct_aggregationeditor_delete';
        $evt = new \Doku_Event('AJAX_CALL_UNKNOWN', $call);

        $evt->advise_before();

        $access = AccessTable::getSerialAccess('wikilookup', $page, $rid);
        $this->assertEquals('keep me', $access->getDataArray()['FirstFieldText']);
    }

    /**
     * Restrict the current user to reading pages
     */
    protected function denyPageEditing()
    {
        global $conf;
        global $AUTH_ACL;
        $conf['useacl'] = 1;
        $AUTH_ACL = ['*	@ALL	' . AUTH_READ];
    }
}
