<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\AccessTable;

/**
 * Tests for the integration with Bureaucracy plugin
 *
 * @group plugin_structb
 * @group plugins
 *
 */
class Bureaucracy_struct_test extends StructTest
{

    /** @var array alway enable the needed plugins */
    protected $pluginsEnabled = array('struct', 'sqlite', 'bureaucracy');

    /** @var array of lookup data */
    protected $lookup = array();

    public function setUp(): void
    {
        parent::setUp();

        if(!class_exists('\dokuwiki\plugin\bureaucracy\test\BureaucracyTest')) {
            $this->markTestSkipped('bureaucracy plugin not available');
        }

        $this->loadSchemaJSON('bureaucracy_lookup');
        $this->loadSchemaJSON('bureaucracy');

        //insert some data to lookup
        for ($i = 1; $i <= 10; ++$i) {
            $data = array(
                'lookup_first' => 'value first ' . $i,
                'lookup_second' => 'value second ' . $i
            );

            $lookupData = AccessTable::getGlobalAccess('bureaucracy_lookup');
            $lookupData->saveData($data);
            $this->lookup[] = $lookupData;
        }
    }

    public function test_bureaucracy_lookup_replacement_empty()
    {
        //page created by bureaucracy
        $id = 'bureaucracy_lookup_replacement_empty';
        //id of template page
        $template_id = 'template';

        //create template
        saveWikiText($template_id, 'Value:@@bureaucracy.lookup_select@@', 'summary');

        //build form
        $fields = array();

        /** @var \helper_plugin_struct_field $lookup_field */
        $lookup_field = plugin_load('helper', 'struct_field');

        $lookup_field->opt['cmd'] = '';
        $lookup_field->opt['label'] = 'bureaucracy.lookup_select';

        $lookup_field->initialize([$lookup_field->opt['cmd'], $lookup_field->opt['label']]);

        //empty lookup value
        $lookup_field->opt['value'] = json_encode([]);
        $fields[] = $lookup_field;

        /** @var  \helper_plugin_bureaucracy_actiontemplate $actiontemplate */
        $actiontemplate = plugin_load('helper', 'bureaucracy_actiontemplate');
        $actiontemplate->run($fields, '', array($template_id, $id, '_'));

        $page_content = io_readWikiPage(wikiFN($id), $id);

        $this->assertEquals('Value:', $page_content);
    }

    public function test_bureaucracy_lookup_replacement()
    {
        //page created by bureaucracy
        $id = 'bureaucracy_lookup_replacement';
        //id of template page
        $template_id = 'template';
        //rid of selected value
        $lookup_rid = $this->lookup[0]->getRid();
        //selected value
        $lookup_value = $this->lookup[0]->getData()['lookup_first']->getValue();

        //create template
        saveWikiText($template_id, 'Value:@@bureaucracy.lookup_select@@', 'summary');

        //build form
        $fields = array();

        $lookup_field = plugin_load('helper', 'struct_field');
        $lookup_field->opt['cmd'] = '';
        $lookup_field->opt['label'] = 'bureaucracy.lookup_select';

        $lookup_field->initialize([$lookup_field->opt['cmd'], $lookup_field->opt['label']]);
        $lookup_field->opt['value'] = json_encode(['', $lookup_rid]);

        $fields[] = $lookup_field;

        /* @var \helper_plugin_bureaucracy_actiontemplate $actiontemplate */
        $actiontemplate = plugin_load('helper', 'bureaucracy_actiontemplate');
        $actiontemplate->run($fields, '', array($template_id, $id, '_'));

        $page_content = io_readWikiPage(wikiFN($id), $id);

        $this->assertEquals('Value:' . $lookup_value, $page_content);
    }

    public function test_bureaucracy_multi_field()
    {
        $this->loadSchemaJSON('schema1');

        $formSyntax = [
            'struct_field "schema1.first"',
            'struct_field "schema1.second"',
        ];
        $templateSyntax = "staticPrefix @@schema1.first@@ staticPostfix\nmulti: @@schema1.second@@ multipost";
        $values = ['foo', ['bar', 'baz']];

        $errors = [];
        $bTest = new \dokuwiki\plugin\bureaucracy\test\BureaucracyTest();
        $actualWikitext = $this->callInaccessibleMethod(
            $bTest,
            'send_form_action_template',
            array_merge(
                [
                    $formSyntax,
                    $templateSyntax,
                    &$errors
                ],
                $values

            )
        );

        $expectedSyntax = "staticPrefix foo staticPostfix\nmulti: bar, baz multipost";
        $this->assertEquals($expectedSyntax, $actualWikitext);
        $this->assertEmpty($errors);
    }
}
