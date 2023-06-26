<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\Utf8\Sort;

/**
 * Object to create a tree of values
 *
 * You should not create these yourself, but use the NestedResult class instead
 */
class NestedValue
{

    /** @var Value */
    protected $value;

    /** @var NestedValue[] */
    protected $children = [];

    /** @var Value[][] */
    protected $resultRows = [];

    /** @var int the nesting depth */
    protected $depth;

    /**
     * Create a nested version of the given value
     *
     * @param Value|null $value The value to store, null for root node
     * @param int $depth The depth of this node (avoids collision where the same values are selected on multiple levels)
     */
    public function __construct(?Value $value, $depth = 0)
    {
        $this->value = $value;
        $this->depth = $depth;
    }

    /**
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
    }

    /**
     * @param int $depth
     */
    public function setDepth($depth)
    {
        $this->depth = $depth;
        foreach ($this->children as $child) {
            $child->setDepth($depth + 1);
        }
    }

    /**
     * Access the stored value
     *
     * @return Value|null the value stored in this node, null for root node
     */
    public function getValueObject()
    {
        return $this->value;
    }

    /**
     * Add a child node
     *
     * Nodes with the same key (__toString()) will be overwritten
     *
     * @param NestedValue $child
     * @return void
     */
    public function addChild(NestedValue $child)
    {
        $this->children[(string)$child] = $child; // ensures uniqueness
    }

    /**
     * Get all child nodes
     *
     * @return NestedValue[]
     */
    public function getChildren()
    {
        $children = $this->children;
        usort($children, [$this, 'sortChildren']);
        return $children;
    }

    /**
     * Add a result row to this node
     *
     * Only unique rows will be stored, duplicates are detected by hashing the row values' toString result
     *
     * @param Value[] $row
     * @return void
     */
    public function addResultRow($row)
    {
        // only add unique rows
        $ident = md5(array_reduce($row, function ($carry, $value) {
            return $carry . $value;
        }, ''));

        $this->resultRows[$ident] = $row;
    }

    /**
     * Get all result rows stored in this node
     *
     * @return Value[][]
     */
    public function getResultRows()
    {
        return array_values($this->resultRows);
    }

    /**
     * Get a unique key for this node
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->value === null) return ''; // root node
        return $this->value->__toString() . '-' . $this->depth;
    }

    /**
     * Custom comparator to sort the children of this node
     *
     * @param NestedValue $a
     * @param NestedValue $b
     * @return int
     */
    public function sortChildren(NestedValue $a, NestedValue $b)
    {
        // note: the way NestedResults build the NestedValues, the value object should
        // always contain a single value only. But since the associated column is still
        // a multi-value column, getCompareValue() will still return an array.
        // So here we treat all returns as array and join them with a dash (even though
        // there should never be more than one value in there)
        return Sort::strcmp(
            join('-', (array)$a->getValueObject()->getCompareValue()),
            join('-', (array)$b->getValueObject()->getCompareValue())
        );
    }

    /**
     * print the tree for debugging
     *
     * @return string
     */
    public function dump()
    {
        $return = '';

        if ($this->value) {
            $return .= str_pad('', $this->getDepth() * 4, ' ');
            $return .= join(', ', (array)$this->value->getDisplayValue());
            $return .= "\n";
        } else {
            $return .= "*\n";
        }

        foreach ($this->getResultRows() as $row) {
            $return .= str_pad('', $this->getDepth() * 4, ' ');
            foreach ($row as $value) {
                $return .= ' ' . join(', ', (array)$value->getDisplayValue());
            }
            $return .= "\n";
        }

        foreach ($this->getChildren() as $child) {
            $return .= $child->dump();
        }

        return $return;
    }
}
