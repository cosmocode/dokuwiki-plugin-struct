<?php

namespace dokuwiki\plugin\struct\test;


class AggregationFilterTest extends StructTest
{
    protected $items = [
        [['green', 'yellow'], 'car', 'audi', 'a80'],
        [[], 'car', 'audi', 'a4'],
        [['red', 'black'], 'car', 'bmw', 'i3'],
        [['green', 'blue'], 'laptop', 'apple', 'pro 16'],
        [['blue', 'gray'], 'car', 'bmw', 'mini'],
        [['red', 'black'], 'car', 'bmw', 'z1'],
        [['red', 'blue'], 'laptop', 'apple', 'air'],
        [['black', 'red'], 'laptop', 'apple', 'm1'],
        [[], 'laptop', 'dell', 'xps'],
        [['black', 'green'], '', 'audi', 'quattro'],
        [['blue', 'yellow'], '', 'dell', 'inspiron'],
        [['gray', 'yellow'], 'laptop', 'dell', 'latitude'],
    ];

    public function testGetAllColumnValues()
    {
        $result = $this->createAggregationResult($this->items);
        $filter = new mock\AggregationFilter();
        $values = $filter->getAllColumnValues($result);

        $this->assertCount(4, $values);

        // we expect value => displayValue pairs, sorted by displayValue
        $this->assertSame(
            [
                'black' => 'black',
                'blue' => 'blue',
                'gray' => 'gray',
                'green' => 'green',
                'red' => 'red',
                'yellow' => 'yellow'
            ],
            $values[0]['values']
        );

        $this->assertEquals(
            'Label 1',
            $values[0]['label']
        );

        $this->assertSame(
            [
                'car' => 'car',
                'laptop' => 'laptop'
            ],
            $values[1]['values']
        );

        $this->assertEquals(
            'Label 2',
            $values[1]['label']
        );
    }
}
