<?php

namespace dokuwiki\plugin\struct\test;

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\NestedResult;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\types\Text;

class NestedResultTest extends StructTest
{
    protected $simpleItems = [
        ['car', 'audi', 'a80'],
        ['car', 'audi', 'a4'],
        ['car', 'audi', 'quattro'],
        ['car', 'bmw', 'i3'],
        ['car', 'bmw', 'mini'],
        ['car', 'bmw', 'z1'],
        ['laptop', 'apple', 'pro 16'],
        ['laptop', 'apple', 'air'],
        ['laptop', 'apple', 'm1'],
        ['laptop', 'dell', 'xps'],
        ['laptop', 'dell', 'inspiron'],
        ['laptop', 'dell', 'latitude'],
    ];

    protected $multiItems = [
        [['green', 'yellow'], 'car', 'audi', 'a80'],
        [['yellow', 'blue'], 'car', 'audi', 'a4'],
        [['black', 'green'], 'car', 'audi', 'quattro'],
        [['red', 'black'], 'car', 'bmw', 'i3'],
        [['blue', 'gray'], 'car', 'bmw', 'mini'],
        [['red', 'black'], 'car', 'bmw', 'z1'],
        [['green', 'blue'], 'laptop', 'apple', 'pro 16'],
        [['red', 'blue'], 'laptop', 'apple', 'air'],
        [['black', 'red'], 'laptop', 'apple', 'm1'],
        [['gray', 'green'], 'laptop', 'dell', 'xps'],
        [['blue', 'yellow'], 'laptop', 'dell', 'inspiron'],
        [['gray', 'yellow'], 'laptop', 'dell', 'latitude'],
    ];


    /**
     * Create a result set from a given flat array
     * @param array $rows
     * @return array
     */
    protected function makeResult($rows)
    {
        $result = [];

        foreach ($rows as $row) {
            $resultRow = [];
            foreach ($row as $cell) {
                $resultRow[] = new Value(
                    new Column(
                        10,
                        new Text(null, '', is_array($cell)),
                        0,
                        true,
                        'test'
                    ),
                    $cell
                );
            }
            $result[] = $resultRow;
        }

        return $result;
    }

    /**
     * Don't nest at all
     */
    public function testSimpleZeroLevel()
    {
        $result = $this->makeResult($this->simpleItems);
        $nestedResult = new NestedResult($result);
        $root = $nestedResult->getRoot(0);

        $this->assertCount(0, $root->getChildren(), 'no children expected');
        $this->assertCount(12, $root->getResultRows(), '12 result rows expected');
    }


    /**
     * Nest by the first level, no multi values
     */
    public function testSimpleOneLevel()
    {
        $result = $this->makeResult($this->simpleItems);
        $nestedResult = new NestedResult($result);
        $tree = $nestedResult->getRoot(1)->getChildren();

        $this->assertCount(2, $tree, '2 root nodes expected');
        $this->assertEquals('car', $tree[0]->getValueObject()->getValue());
        $this->assertEquals('laptop', $tree[1]->getValueObject()->getValue());

        $this->assertCount(0, $tree[0]->getChildren(), 'no children expected');
        $this->assertCount(0, $tree[1]->getChildren(), 'no children expected');

        $this->assertCount(6, $tree[0]->getResultRows(), 'result rows');
        $this->assertCount(6, $tree[1]->getResultRows(), 'result rows');

        $this->assertEquals('a80', $tree[0]->getResultRows()[0][1]->getValue(), 'Audi 80 expected');
        $this->assertEquals('pro 16', $tree[1]->getResultRows()[0][1]->getValue(), 'Mac Pro 16 expected');
    }


    /**
     * Nest by two levels, no multi values
     */
    public function testSimpleTwoLevels()
    {
        $result = $this->makeResult($this->simpleItems);
        $nestedResult = new NestedResult($result);
        $tree = $nestedResult->getRoot(2)->getChildren();

        $this->assertCount(2, $tree, '2 root nodes expected');
        $this->assertEquals('car', $tree[0]->getValueObject()->getValue());
        $this->assertEquals('laptop', $tree[1]->getValueObject()->getValue());

        $this->assertCount(2, $tree[0]->getChildren(), '2 second level nodes expected');
        $this->assertCount(2, $tree[1]->getChildren(), '2 second level nodes expected');

        $this->assertCount(3, $tree[0]->getChildren()[0]->getResultRows(), 'result rows');
        $this->assertCount(3, $tree[0]->getChildren()[1]->getResultRows(), 'result rows');
        $this->assertCount(3, $tree[1]->getChildren()[0]->getResultRows(), 'result rows');
        $this->assertCount(3, $tree[1]->getChildren()[1]->getResultRows(), 'result rows');


        $this->assertEquals('a80', $tree[0]->getChildren()[0]->getResultRows()[0][0]->getValue(), 'Audi 80 expected');
        $this->assertEquals('pro 16', $tree[1]->getChildren()[0]->getResultRows()[0][0]->getValue(), 'Mac Pro 16 expected');
    }

    public function testMultiTwoLevels()
    {
        $result = $this->makeResult($this->multiItems);
        $nestedResult = new NestedResult($result);
        $tree = $nestedResult->getRoot(3)->getChildren(); // nest: color, type, brand -> model

        $this->assertCount(6, $tree, '6 root nodes of colors expected');

        // Values on the first level will be multi-values, thus returning arrays
        $this->assertEquals('black', $tree[0]->getValueObject()->getValue()[0]);
        $this->assertEquals('blue', $tree[1]->getValueObject()->getValue()[0]);
        $this->assertEquals('gray', $tree[2]->getValueObject()->getValue()[0]);
        $this->assertEquals('green', $tree[3]->getValueObject()->getValue()[0]);
        $this->assertEquals('red', $tree[4]->getValueObject()->getValue()[0]);
        $this->assertEquals('yellow', $tree[5]->getValueObject()->getValue()[0]);

        // Results should now show up under multiple top-level nodes
        $this->assertEquals('a80',
            $tree[3] // green
            ->getChildren()[0] // car
            ->getChildren()[0] // audi
            ->getResultRows()[0][0] // a80
            ->getValue(),
            'green car audi a80 expected'
        );
        $this->assertEquals('a80',
            $tree[5] // yellow
            ->getChildren()[0] // car
            ->getChildren()[0] // audi
            ->getResultRows()[0][0] // a80
            ->getValue(),
            'yellow car audi a80 expected'
        );
    }

}
