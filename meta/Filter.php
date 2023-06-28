<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\Form\Form;

/**
 * Struct filter class
 */
class Filter
{
    protected $renderer;

    /** @var \dokuwiki\plugin\struct\meta\Search */
    protected $search;

    /** @var Value[][] */
    protected $result;

    /**
     * @param \Doku_Renderer $renderer
     * @param \dokuwiki\plugin\struct\meta\Search $search
     */
    public function __construct($renderer, $search)
    {
        $this->renderer = $renderer;
        $this->search = $search;
        $this->result = $search->execute();
    }

    /**
     * Render the filter form.
     * Reuses the structure of advanced search tools to leverage
     * the core grouping styles and scripts.
     *
     * @param array $lang Language strings "title", "intro" and "button"
     * @return void
     */
    public function render($lang)
    {
        $schemas = $this->search->getSchemas();
        $schema = $schemas[0]->getTable();

        $colValues = $this->getAllColumnValues();

        $form = new Form(['method' => 'get'], true);
        $form->addClass('struct-filter-form search-results-form');
        $form->setHiddenField('id', getID());

        $form->addFieldsetOpen()->addClass('struct-filter-form search-form');
        $form->addHTML('<legend>' . $lang['title'] . '</legend>');
        $form->addHTML('<p>' . $lang['intro'] . '</p>');

        $form->addTagOpen('div')
            ->addClass('advancedOptions');

        // column dropdowns
        foreach ($colValues as $colName => $values) {
            $form->addTagOpen('div')
                ->addClass('toggle')
                ->id("__filter-$colName")
                ->attr('aria-haspopup', 'true');

            // popup toggler
            $form->addTagOpen('div')->addClass('current');
            $form->addHTML($colName);
            $form->addTagClose('div');

            $form->addTagOpen('ul')->attr('aria-expanded', 'false');

            $values = array_filter(array_unique($values));

            $i = 0;
            foreach ($values as $value) {
                $form->addTagOpen('li');
                $form->addCheckbox(SearchConfigParameters::$PARAM_FILTER . "[$schema.$colName*~]")
                    ->val($value)
                    ->id("__$schema.$colName-" . $i);
                $form->addLabel($value, "$schema.__$colName-" . $i)
                    ->attr('title', $value);
                $form->addTagClose('li');
                $i++;
            }

            $form->addTagClose('ul');
            $form->addTagClose('div'); // close div.toggle
        }

        $form->addButton('struct-filter-submit', $lang['button'])
            ->attr('type', 'submit')
            ->addClass('struct-filter-submit');

        $form->addTagClose('div'); // close div.advancedOptions
        $form->addFieldsetClose();

        $this->renderer->doc .= $form->toHTML();
    }

    /**
     * Get all values from current search result grouped by column
     * FIXME handle special columns like %pageid%
     *
     * @return array
     */
    protected function getAllColumnValues(): array
    {
        $colValues = [];

        foreach ($this->result as $row) {
            foreach ($row as $value) {
                $opt = $value->getDisplayValue();
                // FIXME handle multiple values better
                if (is_array($opt)) {
                    $opt = implode(', ', $opt);
                }
                $colValues[$value->getColumn()->getLabel()][] = $opt;
            }
        }

        return $colValues;
    }
}
