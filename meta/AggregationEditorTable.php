<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AggregationEditorTable
 *
 * An AggregationTable for editing global and serial tables
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AggregationEditorTable extends AggregationTable
{
    /** @inheritdoc */
    public function render($showNotFound = false)
    {
        parent::render(); // never show not found
    }


    /**
     * Adds additional info to document and renderer in XHTML mode
     *
     * We add the schema name as data attribute
     *
     * @see finishScope()
     */
    public function startScope()
    {
        // unique identifier for this aggregation
        $hash = md5(var_export($this->data, true));
        $this->renderer->info['struct_table_hash'] = $hash;

        if ($this->mode != 'xhtml') return;

        $table = $this->columns[0]->getTable();

        $config = $this->searchConfig->getConf();
        if (isset($config['filter'])) unset($config['filter']);
        $config = hsc(json_encode($config, JSON_THROW_ON_ERROR));

        // wrapping div
        $classes = $this->getScopeClasses();
        $classes[] = 'structaggregationeditor';
        $classes = implode(' ', $classes);
        $this->renderer->doc .= "<div id=\"$hash\"
                                      class=\"$classes\"
                                      data-schema=\"$table\" data-searchconf=\"$config\">";
    }

    /**
     * We do not output a row for empty tables
     */
    protected function renderEmptyResult()
    {
    }

    /**
     * Renders the first result row and returns it
     *
     * Only used for rendering new rows via JS (where the first row is the only one)
     *
     * @return string
     */
    public function getFirstRow()
    {
        // XHTML renderer doesn't like calling ->tablerow_open() without
        // ->table_open() first, since it leaves some internal variables unset.
        // Therefore, call ->table_open() and throw away the generated HTML.
        $this->renderer->table_open();
        $this->renderer->doc = '';

        $this->renderResultRow(0, $this->result[0]);
        return $this->renderer->doc;
    }
}
