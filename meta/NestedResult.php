<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\plugin\struct\types\Text;
use dokuwiki\Utf8\PhpString;

/**
 * This class builds a nested tree from a search result
 *
 * This is used to create the nested output in the AggregationList
 */
class NestedResult
{
    /** @var NestedValue[] */
    protected $nodes = [];

    /** @var NestedValue[] */
    protected $indexNodes = [];

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
     * @param int $index the number of characters to use for indexing
     * @return NestedValue the root node of the nested tree
     */
    public function getRoot($nesting, $index = 0)
    {
        $this->nodes = [];
        $root = new NestedValue(null, -1);

        if (!$this->result) return $root;

        foreach ($this->result as $row) {
            $this->nestBranch($root, $row, $nesting);
        }

        $root = $this->createIndex($root, $index);
        return $root;
    }

    /**
     * Add a top level index to the tree
     *
     * @param NestedValue $root Root node of the tree
     * @param int $index Number of characters to use for indexing
     * @return NestedValue new root node
     */
    protected function createIndex(NestedValue $root, $index)
    {
        if (!$index) return $root;
        $this->indexNodes = [];

        $children = $root->getChildren();
        $resultRows = $root->getResultRows();
        if ($children) {
            // there are children, so we are a nested result
            foreach ($children as $child) {
                $indexValue = $child->getValueObject();
                $indexNode = $this->getIndexNode($indexValue, $index);
                $indexNode->addChild($child);
                $child->setDepth(1); // increase child's depth from 0 to 1
            }
        } elseif ($resultRows) {
            // no children, so we are currently a flat result
            foreach ($resultRows as $row) {
                $indexValue = $row[0];
                $indexNode = $this->getIndexNode($indexValue, $index);
                $indexNode->addResultRow($row);
            }
        }

        // now all results are added to index nodes - use them as children
        $newRoot = new NestedValue(null, -1);
        foreach ($this->indexNodes as $node) {
            $newRoot->addChild($node);
        }
        return $newRoot;
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
        if ($depth >= $nesting) {
            $parent->addResultRow($row);
            return;
        }

        $valObj = array_shift($row);
        if (!$valObj instanceof Value) return; // no more values to nest, usually shouldn't happen

        $parentPath = (string) $parent;

        if ($valObj->getColumn()->isMulti() && $valObj->getValue()) {
            // split up multi values into separate nodes
            $values = $valObj->getValue();
            if ($values) {
                foreach ($values as $value) {
                    $newValue = new Value($valObj->getColumn(), $value);
                    $node = $this->getNodeForValue($newValue, $parentPath, $depth);
                    $parent->addChild($node);
                    $this->nestBranch($node, $row, $nesting, $depth + 1);
                }
            } else {
                $newValue = new Value($valObj->getColumn(), ''); // add empty node
                $node = $this->getNodeForValue($newValue, $parentPath, $depth);
                $parent->addChild($node);
                $this->nestBranch($node, $row, $nesting, $depth + 1);
            }
        } else {
            $node = $this->getNodeForValue($valObj, $parentPath, $depth);
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
    protected function getNodeForValue(Value $value, $parentPath, $depth)
    {
        $node = new NestedValue($value, $parentPath, $depth);
        $key = (string)$node;
        if (!isset($this->nodes[$key])) {
            $this->nodes[$key] = $node;
        }
        return $this->nodes[$key];
    }

    /**
     * Create or get an existing Node for indexing
     *
     * @param Value $value
     * @param int $index
     * @return NestedValue
     */
    protected function getIndexNode(Value $value, $index)
    {
        $compare = $value->getDisplayValue();
        if (is_array($compare)) $compare = $compare[0];
        $key = PhpString::strtoupper(PhpString::substr($compare, 0, $index));

        if (!isset($this->indexNodes[$key])) {
            $col = new Column(
                0,
                new Text([], '%index%', false),
                -1,
                true,
                $value->getColumn()->getTable()
            );
            $this->indexNodes[$key] = new NestedValue(new Value($col, $key), 0);
        }

        return $this->indexNodes[$key];
    }
}
