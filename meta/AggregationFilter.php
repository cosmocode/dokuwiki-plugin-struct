<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\Form\Form;
use dokuwiki\Utf8\Sort;

/**
 * Struct filter class
 */
class AggregationFilter extends Aggregation
{
    /**
     * Render the filter form.
     * Reuses the structure of advanced search tools to leverage
     * the core grouping styles and scripts.
     *
     * @param bool $showNotFound Inherited from parent method
     * @return void
     */
    public function render($showNotFound = false)
    {
        $colValues = $this->getAllColumnValues($this->result);

        // column dropdowns
        foreach ($colValues as $num => $colData) {
            /** @var Column $column */
            $column = $colData['column'];
            $label = $this->data['headers'][$num] ?? $colData['label'];

            $this->renderer->doc .= '<details>';
            $this->renderer->doc .= '<summary>' . hsc($label) . '</summary>';
            $this->renderer->doc .= '<ul>';
            foreach ($colData['values'] as $value => $displayValue) {
                $current = false;
                $dyn = $this->searchConfig->getDynamicParameters();
                $allFilters = $dyn->getFilters();
                if (isset($allFilters[$column->getFullQualifiedLabel()])) {
                    if ($allFilters[$column->getFullQualifiedLabel()][1] == $displayValue) {
                        $current = true;
                    }
                    $dyn->removeFilter($column); // remove previous filter for this column
                }
                if (!$current) {
                    // add new filter unless it's the current item
                    $dyn->addFilter($column, '=', $displayValue);
                }
                $params = $dyn->getURLParameters();
                $filter = buildURLparams($params);

                $this->renderer->doc .= '<li ' . ($current ? 'class="active"' : '') . '><div class="li">';
                $column->getType()->renderTagCloudLink($value, $this->renderer, $this->mode, $this->id, $filter, 100);
                $this->renderer->doc .= '</div></li>';
            }
            $this->renderer->doc .= '</ul>';
            $this->renderer->doc .= '</details>';
        }
    }

    /**
     * Get all values from given search result grouped by column
     *
     * @return array
     */
    protected function getAllColumnValues($result)
    {
        $colValues = [];

        foreach ($result as $row) {
            foreach ($row as $value) {
                /** @var Value $value */
                $colName = $value->getColumn()->getFullQualifiedLabel();
                $colValues[$colName]['column'] = $value->getColumn();
                $colValues[$colName]['label'] = $value->getColumn()->getTranslatedLabel();
                $colValues[$colName]['values'] ??= [];

                if (empty($value->getDisplayValue())) continue;

                // create an array with [value => displayValue] pairs
                // the cast to array will handle single and multi-value fields the same
                // using the full value as key will make sure we don't have duplicates
                //
                // because a value might be interpreted as integer in the array key, we pad
                // each key with a space at the end to enforce string keys. The space will
                // be ignored when parsing JSON values and trimmed for all other types.
                // This is a work around for #665
                $pairs = array_combine(
                    array_map(
                        static fn($v) => "$v ",
                        (array)$value->getValue()
                    ),
                    (array)$value->getDisplayValue()
                );
                $colValues[$colName]['values'] = array_merge($colValues[$colName]['values'], $pairs);
            }
        }

        // sort by display value
        array_walk($colValues, function (&$col) {
            Sort::asort($col['values']);
        });

        return array_values($colValues); // reindex
    }
}
