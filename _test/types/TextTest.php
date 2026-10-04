<?php

namespace dokuwiki\plugin\struct\test\types;

use dokuwiki\plugin\struct\test\mock\QueryBuilder;
use dokuwiki\plugin\struct\test\StructTest;
use dokuwiki\plugin\struct\types\Text;

/**
 * Testing the Text Type
 *
 * @group plugin_struct
 * @group plugins
 */
class TextTest extends StructTest
{

    public function data()
    {
        return [
            // simple
            [
                '', // prefix
                '', // postfix
                '=', // comp
                'val', // value
                '(T.col = ?)', // expect sql
                ['val'], // expect opts
            ],
            [
                'before', // prefix
                '', // postfix
                '=', // comp
                'val', // value
                '(? || T.col = ?)', // expect sql
                ['before', 'val'], // expect opts
            ],
            [
                '', // prefix
                'after', // postfix
                '=', // comp
                'val', // value
                '(T.col || ? = ?)', // expect sql
                ['after', 'val'], // expect opts
            ],
            [
                'before', // prefix
                'after', // postfix
                '=', // comp
                'val', // value
                '(? || T.col || ? = ?)', // expect sql
                ['before', 'after', 'val'], // expect opts
            ],
            // LIKE
            [
                '', // prefix
                '', // postfix
                'LIKE', // comp
                '%val%', // value
                '(T.col LIKE ?)', // expect sql
                ['%val%'], // expect opts
            ],
            [
                'before', // prefix
                '', // postfix
                'LIKE', // comp
                '%val%', // value
                '(? || T.col LIKE ?)', // expect sql
                ['before', '%val%'], // expect opts
            ],
            [
                '', // prefix
                'after', // postfix
                'LIKE', // comp
                '%val%', // value
                '(T.col || ? LIKE ?)', // expect sql
                ['after', '%val%'], // expect opts
            ],
            [
                'before', // prefix
                'after', // postfix
                'LIKE', // comp
                '%val%', // value
                '(? || T.col || ? LIKE ?)', // expect sql
                ['before', 'after', '%val%'], // expect opts
            ],
            // NOT LIKE
            [
                '', // prefix
                '', // postfix
                'NOT LIKE', // comp
                '%val%', // value
                '(T.col NOT LIKE ?)', // expect sql
                ['%val%'], // expect opts
            ],
            [
                'before', // prefix
                '', // postfix
                'NOT LIKE', // comp
                '%val%', // value
                '(? || T.col NOT LIKE ?)', // expect sql
                ['before', '%val%'], // expect opts
            ],
            [
                '', // prefix
                'after', // postfix
                'NOT LIKE', // comp
                '%val%', // value
                '(T.col || ? NOT LIKE ?)', // expect sql
                ['after', '%val%'], // expect opts
            ],
            [
                'before', // prefix
                'after', // postfix
                'NOT LIKE', // comp
                '%val%', // value
                '(? || T.col || ? NOT LIKE ?)', // expect sql
                ['before', 'after', '%val%'], // expect opts
            ],

            // complex multi-value
            [
                'before', // prefix
                'after', // postfix
                'NOT LIKE', // comp
                ['%val1%', '%val2%'], // multiple values
                '((? || T.col || ? NOT LIKE ? OR ? || T.col || ? NOT LIKE ?))', // expect sql
                ['before', 'after', '%val1%', 'before', 'after', '%val2%',], // expect opts
            ],
        ];

    }

    /**
     * @dataProvider data
     */
    public function test_filter($prefix, $postfix, $comp, $val, $e_sql, $e_opt)
    {
        $QB = new QueryBuilder();

        $text = new Text(['prefix' => $prefix, 'postfix' => $postfix]);
        $text->filter($QB->filters(), 'T', 'col', $comp, $val, 'AND');

        list($sql, $opt) = $QB->getWhereSQL();
        $this->assertEquals($this->cleanWS($e_sql), $this->cleanWS($sql));
        $this->assertEquals($e_opt, $opt);
    }
}
