<?php

namespace dokuwiki\plugin\struct\test;


class AggregationFilterTest extends StructTest
{
    protected $items = [
        [['green', 'yellow'], 'car', 'audi', 'a80'],
        [[], 'car', 'audi', 'a4'],
        [['black', 'green'], '', 'audi', 'quattro'],
        [['red', 'black'], 'car', 'bmw', 'i3'],
        [['blue', 'gray'], 'car', 'bmw', 'mini'],
        [['red', 'black'], 'car', 'bmw', 'z1'],
        [['green', 'blue'], 'laptop', 'apple', 'pro 16'],
        [['red', 'blue'], 'laptop', 'apple', 'air'],
        [['black', 'red'], 'laptop', 'apple', 'm1'],
        [[], 'laptop', 'dell', 'xps'],
        [['blue', 'yellow'], '', 'dell', 'inspiron'],
        [['gray', 'yellow'], 'laptop', 'dell', 'latitude'],
    ];

    public function testGetAllColumnValues()
    {
        $result = $this->createAggregationResult($this->items);
        $filter = new mock\AggregationFilter();
        $values = $filter->getAllColumnValues($result);

        $this->assertCount(4, $values);

        $this->assertEquals(
            ['black', 'blue', 'gray', 'green', 'red', 'yellow'],
            $values['test.field1']['values']
        );

        $this->assertEquals(
            'Label 1',
            $values['test.field1']['label']
        );

        $this->assertEquals(
            ['car', 'laptop'],
            $values['test.field2']['values']
        );

        $this->assertEquals(
            'Label 2',
            $values['test.field2']['label']
        );
    }
}
