<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * This class builds a nested tree from a search result
 *
 * This is used to create the nested output in the AggregationList
 */
class NestedResult
{

    /** @var NestedValue[] */
    protected $nodes = [];

    /** @var Value[][] */
    protected $result;

    /**
     * @param Value[][] $result the original search result
     * @return void
     */
    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * Get the nested result
     *
     * @param int $nesting the nesting level to use
     * @return NestedValue the root node of the nested tree
     */
    public function getRoot($nesting) {
        $this->nodes = [];
        $root = new NestedValue(null, -1);

        if(!$this->result) return $root;
        foreach ($this->result as $row) {
            $this->nestBranch($root, $row, $nesting);
        }

        return $root;
    }

    /**
     * Creates nested nodes for a given result row
     *
     * Splits up multi Values into separate nodes, when used in nesting
     *
     * @param Value[] $row current result row to work on
     * @param int $nesting number of wanted nesting levels
     * @param int $depth current nesting depth (used in recursion)
     */
    protected function nestBranch(NestedValue $parent, $row, $nesting, $depth = 0)
    {
        // nesting level reached, add row and return
        if($depth >= $nesting) {
            $parent->addResultRow($row);
            return;
        }

        $valObj = array_shift($row);
        if (!$valObj) return; // no more values to nest, usually shouldn't happen

        if ($valObj->getColumn()->isMulti()) {
            // split up multi values into separate nodes
            $values = $valObj->getValue();
            foreach ($values as $value) {
                $newValue = new Value($valObj->getColumn(), $value);
                $node = $this->getNodeForValue($newValue, $depth);
                $parent->addChild($node);
                $this->nestBranch($node, $row, $nesting, $depth + 1);
            }
        } else {
            $node = $this->getNodeForValue($valObj, $depth);
            $parent->addChild($node);
            $this->nestBranch($node, $row, $nesting, $depth + 1);

        }
    }

    /**
     * Create or get existing Node from the tree
     *
     * @param Value $value
     * @param int $depth
     * @return NestedValue
     */
    protected function getNodeForValue(Value $value, $depth)
    {
        $node = new NestedValue($value, $depth);
        $key = (string) $node;
        if (!isset($this->nodes[$key])) {
            $this->nodes[$key] = $node;
        }
        return $this->nodes[$key];
    }
}


