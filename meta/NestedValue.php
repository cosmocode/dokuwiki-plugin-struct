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
     * @var mixed|string
     */
    protected $parentPath;

    /**
     * Create a nested version of the given value
     *
     * @param Value|null $value The value to store, null for root node
     * @param int $depth The depth of this node (avoids collision where the same values are selected on multiple levels)
     */
    public function __construct(?Value $value, $parentPath = '', $depth = 0)
    {
        $this->value = $value;
        $this->parentPath = $parentPath;
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
     * @param bool $sort should children be sorted alphabetically?
     * @return NestedValue[]
     */
    public function getChildren($sort = false)
    {
        $children = $this->children;

        if ($sort) {
            usort($children, [$this, 'sortChildren']);
        } elseif (isset($children[''])) {
            // even when not sorting, make sure the n/a entries are last
            $naKids = $children[''];
            unset($children['']);
            $children[''] = $naKids;
        }
        return array_values($children);
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
        $ident = md5(array_reduce($row, static fn($carry, $value) => $carry . $value, ''));

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
        if (!$this->value instanceof Value) return ''; // root node
        return $this->parentPath . '/' . $this->value->__toString();
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
        $compA = implode('-', (array)$a->getValueObject()->getCompareValue());
        $compB = implode('-', (array)$b->getValueObject()->getCompareValue());

        // sort empty values to the end
        if ($compA === $compB) {
            return 0;
        }
        if ($compA === '') {
            return 1;
        }
        if ($compB === '') {
            return -1;
        }

        // note: the way NestedResults build the NestedValues, the value object should
        // always contain a single value only. But since the associated column is still
        // a multi-value column, getCompareValue() will still return an array.
        // So here we treat all returns as array and join them with a dash (even though
        // there should never be more than one value in there)
        return Sort::strcmp($compA, $compB);
    }

    /**
     * print the tree for debugging
     *
     * @param bool $sort use sorted children?
     * @return string
     */
    public function dump($sort = true)
    {
        $return = '';

        if ($this->value) {
            $val = implode(', ', (array)$this->value->getDisplayValue());
            if ($val === '') $val = '{n/a}';
            $return .= str_pad('', $this->getDepth() * 4, ' ');
            $return .= $val;
            $return .= "\n";
        } else {
            $return .= "*\n";
        }

        foreach ($this->getResultRows() as $row) {
            $return .= str_pad('', $this->getDepth() * 4, ' ');
            foreach ($row as $value) {
                $val = implode(', ', (array)$value->getDisplayValue());
                if ($val === '') $val = '{n/a}';
                $return .= ' ' . $val;
            }
            $return .= "\n";
        }

        foreach ($this->getChildren($sort) as $child) {
            $return .= $child->dump();
        }

        return $return;
    }
}
