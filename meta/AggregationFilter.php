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
        $schemas = $this->searchConfig->getSchemas();
        $schema = $schemas[0]->getTable();

        $colValues = $this->getAllColumnValues($this->result);

        $form = new Form(['method' => 'get'], true);
        $form->addClass('struct-filter-form search-results-form');
        $form->setHiddenField('id', getID());

        $form->addFieldsetOpen()->addClass('struct-filter-form search-form');
        $form->addHTML('<legend>' . $this->helper->getLang('filter_title') . '</legend>');

        $form->addTagOpen('div')
            ->addClass('advancedOptions');

        // column dropdowns
        $num = 0;
        foreach ($colValues as $colName => $colData) {
            $qualifiedColName = $colName[0] !== '%' ? "$schema.$colName" : $colName;

            $form->addTagOpen('div')
                ->addClass('toggle')
                ->id("__filter-$colName")
                ->attr('aria-haspopup', 'true');

            // popup toggler uses header if defined in syntax, otherwise label
            $header = $colData['label'];
            if (!empty($this->data['headers'][$num])) {
                $header = $this->data['headers'][$num];
            }
            $form->addTagOpen('div')->addClass('current');
            $form->addHTML($header);
            $form->addTagClose('div');

            $form->addTagOpen('ul')->attr('aria-expanded', 'false');

            $i = 0;
            foreach ($colData['values'] as $value) {
                $form->addTagOpen('li');
                $form->addRadioButton(SearchConfigParameters::$PARAM_FILTER . "[$qualifiedColName*~]")
                    ->val($value)
                    ->id("__$schema.$colName-" . $i);
                $form->addLabel($value, "__$schema.$colName-" . $i)
                    ->attr('title', $value);
                $form->addTagClose('li');
                $i++;
            }

            $form->addTagClose('ul');
            $form->addTagClose('div'); // close div.toggle
            $num++;
        }

        $form->addButton('struct-filter-submit', $this->helper->getLang('filter_button'))
            ->attr('type', 'submit')
            ->addClass('struct-filter-submit');

        $form->addTagClose('div'); // close div.advancedOptions
        $form->addFieldsetClose();

        $this->renderer->doc .= $form->toHTML();
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
                $colValues[$colName]['label'] = $value->getColumn()->getTranslatedLabel();
                $colValues[$colName]['values'] = $colValues[$colName]['values'] ?? [];

                $opt = $value->getDisplayValue();

                if (empty($opt)) continue;

                // handle multiple values
                if (is_array($opt)) {
                    $colValues[$colName]['values'] = array_merge($colValues[$colName]['values'], $opt);
                } else {
                    $colValues[$colName]['values'][] = $opt;
                }
            }
        }

        array_walk($colValues, function (&$col) {
            $unique = array_unique($col['values']);
            Sort::sort($unique);
            $col['values'] = $unique;
        });

        return $colValues;
    }
}
