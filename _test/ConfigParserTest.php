<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta;

/**
 * Tests for parsing the aggregation config for the struct plugin
 *
 * @group plugin_struct
 * @group plugins
 *
 */
class ConfigParserTest extends StructTest
{

    public function test_simple()
    {
        $lines = [
            "schema    : testtable, another, foo bar",
            "cols      : %pageid%, count",
            "sort      : ^count",
            "sort      : %pageid%, ^bam",
            "align     : r,l,center,foo",
            "class     : foo, bar",
        ];

        $configParser = new meta\ConfigParser($lines);
        $actual_config = $configParser->getConfig();

        $expected_config = [
            'limit' => 0,
            'dynfilters' => false,
            'summarize' => false,
            'rownumbers' => false,
            'sepbyheaders' => false,
            'headers' =>
                [
                    0 => NULL,
                    1 => NULL,
                ],
            'widths' =>
                [],
            'filter' =>
                [],
            'schemas' =>
                [
                    0 =>
                        [
                            0 => 'testtable',
                            1 => '',
                        ],
                    1 =>
                        [
                            0 => 'another',
                            1 => '',
                        ],
                    2 =>
                        [
                            0 => 'foo',
                            1 => 'bar',
                        ],
                ],
            'cols' =>
                [
                    0 => '%pageid%',
                    1 => 'count',
                ],
            'sort' =>
                [
                    [
                        0 => 'count',
                        1 => false,
                    ],
                    [
                        0 => '%pageid%',
                        1 => true,
                    ],
                    [
                        0 => 'bam',
                        1 => false,
                    ]
                ],
            'csv' => true,
            'target' => '',
            'align' => ['right', 'left', 'center', null],
            'nesting' => 0,
            'index' => 0,
            'classes' => ['struct-custom-foo', 'struct-custom-bar'],
        ];

        $this->assertEquals($expected_config, $actual_config);
    }

    public function test_width()
    {
        $lines = ['width: 5, 15px, 23.4em, meh, 10em'];

        $configParser = new meta\ConfigParser($lines);

        $config = $configParser->getConfig();

        $this->assertEquals(
            ['5px', '15px', '23.4em', '', '10em'],
            $config['widths']
        );
    }

    /**
     * @see test_splitLine
     */
    public function provide_splitLine()
    {
        return [
            ['', ['', '']],
            ['   ', ['', '']],
            ['foo', ['foo', '']],
            ['foo:bar', ['foo', 'bar']],
            ['foo: bar', ['foo', 'bar']],
            ['fOO: bar', ['foo', 'bar']],
            ['  fOO: bar  ', ['foo', 'bar']],
        ];
    }

    /**
     * @dataProvider provide_splitLine
     */
    public function test_splitLine($line, $expected)
    {
        $configParser = new meta\ConfigParser(array());
        $actual = $this->callInaccessibleMethod($configParser, 'splitLine', [$line]);
        $this->assertEquals($expected, $actual);
    }
}
