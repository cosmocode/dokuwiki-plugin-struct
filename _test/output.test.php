<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;
use Doku_Event;

/**
 * @group plugin_struct
 * @group plugins
 *
 * @covers \action_plugin_struct_output
 */
class output_struct_test extends StructTest {

    /** @var array add the extra plugins */
    protected $pluginsEnabled = array('struct', 'sqlite', 'log', 'include');

    public function setUp() {
        parent::setUp();

        $this->loadSchemaJSON('schema1');
        $this->loadSchemaJSON('schema_3');

        $page = 'page01';
        $includedPage = 'foo';
        $this->saveData(
            $page,
            'schema1',
            array(
                'first' => 'first data',
                'second' => array('second data', 'more data', 'even more'),
                'third' => 'third data',
                'fourth' => 'fourth data'
            )
        );
        $this->saveData(
            $page,
            'schema_3',
            array(
                'first_field' => 'first data',
                'second field' => array('second data', 'more data', 'even more'),
                'third%field' => 'third data',
                'fourth.field?' => 'fourth data'
            )
        );
        $this->saveData(
            $includedPage,
            'schema1',
            array(
                'first' => 'first data',
                'second' => array('second data', 'more data', 'even more'),
                'third' => 'third data',
                'fourth' => 'fourth data'
            )
        );
    }

    public function test_output() {
        global $ID;
        $page = 'page01';
        $ID = $page;

        saveWikiText($page, "====== abc ======\ndef",'');
        $instructions = p_cached_instructions(wikiFN($page), false, $page);
        $this->assertEquals('document_start', $instructions[0][0]);
        $this->assertEquals('header', $instructions[1][0]);
        $this->assertEquals('plugin', $instructions[2][0]);
        $this->assertEquals('struct_output', $instructions[2][1][0]);
    }

    public function test_include_missing_output() {
        global $ID;
        $page = 'page01';
        $includedPage = 'foo';

        saveWikiText($page, "====== abc ======\n{{page>foo}}\n", '');
        saveWikiText($includedPage, "====== included page ======\nqwe\n",'');


        plugin_load('action', 'struct_output', true);
        $ID = $page;
        $insMainPage = p_cached_instructions(wikiFN($page), false, $page);
        $this->assertEquals('document_start', $insMainPage[0][0]);
        $this->assertEquals('header', $insMainPage[1][0]);
        $this->assertEquals('plugin', $insMainPage[2][0]);
        $this->assertEquals('struct_output', $insMainPage[2][1][0]);

        plugin_load('action', 'struct_output', true);
        $ID = $includedPage;
        $insIncludedPage = p_cached_instructions(wikiFN($includedPage), false, $includedPage);
        $this->assertEquals('document_start', $insIncludedPage[0][0]);
        $this->assertEquals('header', $insIncludedPage[1][0]);
        $this->assertEquals('plugin', $insIncludedPage[2][0], 'The struct data of a page that has been included should still be displayed when viewed on its own.');
        $this->assertEquals('struct_output', $insIncludedPage[2][1][0]);

    }

    public function test_log_conflict() {
        global $ID;
        $page = 'page01';
        $ID = $page;

        saveWikiText($page, "====== abc ======\n{{log}}\n", '');
        saveWikiText($page.':log', '====== abc log ======
Log for [[page01]]:

  * 2017-02-24 10:54:13 //Example User//: foo bar','');
        $instructions = p_cached_instructions(wikiFN($page), false, $page);
        $this->assertEquals('document_start', $instructions[0][0]);
        $this->assertEquals('header', $instructions[1][0]);
        $this->assertEquals('plugin', $instructions[2][0], 'The struct data should be rendererd after the first headline');
        $this->assertEquals('struct_output', $instructions[2][1][0]);
    }

    /**
     * Replace and clean up template placeholders
     * provided by a dw2pdf event
     */
    public function test_dw2pdf_replacements()
    {
        $page = 'page01';
        $replace = ['@ID@' => $page];
        $content = <<< HTML
<table class="pdffooter">
    <tr>
        <td style="text-align: left">ID: @ID@</td>
        <td style="text-align: center">Version: @ID@@@PLUGIN_STRUCT_schema_3_version@~@PLUGIN_STRUCT_schema_3_first_field@</td>
        <td style="text-align: right">Second data: @PLUGIN_STRUCT_schema_3_second field@</td>
    </tr>
</table>
HTML;
        $processed = <<< HTML
<table class="pdffooter">
    <tr>
        <td style="text-align: left">ID: page01</td>
        <td style="text-align: center">Version: page01@~first data</td>
        <td style="text-align: right">Second data: second data, more data, even more</td>
    </tr>
</table>
HTML;

        $evdata = ['id' => $page, 'replace' => &$replace, 'content' => &$content];
        $event = new Doku_Event('PLUGIN_DW2PDF_REPLACE', $evdata);
        if ($event->advise_before()) {
            $content = str_replace(array_keys($replace), array_values($replace), $content);
        }
        $event->advise_after();

        $this->assertEquals($processed, $content);
    }
}
