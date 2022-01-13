<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;

/**
 * Tests for parsing the inline aggregation config for the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
// phpcs:ignore Squiz.Classes.ValidClassName
class InlineConfigParser_struct_test extends StructTest
{
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_simple()
    {
        // Same initial setup as ConfigParser.test
        $inline = '"testtable, another, foo bar"."%pageid%, count" ';
        $inline .= '?sort: ^count sort: "%pageid%, ^bam" align: "r,l,center,foo"';
        // Add InlineConfigParser-specific tests:
        $inline .= ' & "%pageid% != start" | "count = 1"';

        $configParser = new meta\InlineConfigParser($inline);
        $actual_config = $configParser->getConfig();

        $expected_config = [
            'align' => ['right', 'left', 'center', null],
            'cols' => ['%pageid%', 'count'],
            'csv' => true,
            'dynfilters' => false,
            'filter' => [
                ['%pageid%', '!=', 'start', 'AND'],
                ['count', '=', '1', 'OR'],
            ],
            'headers' => [null, null],
            'limit' => 0,
            'rownumbers' => false,
            'schemas' => [
                ['testtable', ''],
                ['another', ''],
                ['foo', 'bar'],
            ],
            'sepbyheaders' => false,
            'sort' => [
                ['count', false],
                ['%pageid%', true],
                ['bam', false],
            ],
            'summarize' => false,
            'target' => '',
            'widths' => [],
        ];

        $this->assertEquals($expected_config, $actual_config);
    }
}
