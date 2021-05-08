<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Creates the table aggregation output
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AggregationTable
{

    /**
     * @var string the page id of the page this is rendered to
     */
    protected $id;
    /**
     * @var string the Type of renderer used
     */
    protected $mode;
    /**
     * @var \Doku_Renderer the DokuWiki renderer used to create the output
     */
    protected $renderer;
    /**
     * @var SearchConfig the configured search - gives access to columns etc.
     */
    protected $searchConfig;

    /**
     * @var Column[] the list of columns to be displayed
     */
    protected $columns;

    /**
     * @var  Value[][] the search result
     */
    protected $result;

    /**
     * @var int number of all results
     */
    protected $resultCount;

    /**
     * @var string[] the result PIDs for each row
     */
    protected $resultPIDs;
    protected $resultRids;
    protected $resultRevs;

    /**
     * @var array for summing up columns
     */
    protected $sums;

    /**
     * @var bool skip full table when no results found
     */
    protected $simplenone = true;

    /**
     * @todo we might be able to get rid of this helper and move this to SearchConfig
     * @var \helper_plugin_struct_config
     */
    protected $helper;

    /**
     * Initialize the Aggregation renderer and executes the search
     *
     * You need to call @see render() on the resulting object.
     *
     * @param string $id
     * @param string $mode
     * @param \Doku_Renderer $renderer
     * @param SearchConfig $searchConfig
     */
    public function __construct($id, $mode, \Doku_Renderer $renderer, SearchConfig $searchConfig)
    {
        $this->id = $id;
        $this->mode = $mode;
        $this->renderer = $renderer;
        $this->searchConfig = $searchConfig;
        $this->data = $searchConfig->getConf();
        $this->columns = $searchConfig->getColumns();

        $this->result = $this->searchConfig->execute();
        $this->resultCount = $this->searchConfig->getCount();
        $this->resultPIDs = $this->searchConfig->getPids();
        $this->resultRids = $this->searchConfig->getRids();
        $this->resultRevs = $this->searchConfig->getRevs();
        $this->helper = plugin_load('helper', 'struct_config');
    }

    /**
     * Returns the page id for the table
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Create the table on the renderer
     */
    public function render()
    {

        // abort early if there are no results at all (not filtered)
        if (!$this->resultCount && !$this->isDynamicallyFiltered() && $this->simplenone) {
            $this->startScope();
            $this->renderer->cdata($this->helper->getLang('none'));
            $this->finishScope();
            return;
        }

        $this->startScope();
        $this->renderActiveFilters();

        $rendercontext = array(
            'table' => $this,
            'renderer' => $this->renderer,
            'format' => $this->mode,
            'search' => $this->searchConfig,
            'columns' => $this->columns,
            'data' => $this->result
        );

        $event = new \Doku_Event(
            'PLUGIN_STRUCT_RENDER_AGGREGATION_TABLE',
            $rendercontext
        );
        $event->trigger([$this, 'renderTable']);

        // export handle
        $this->renderExportControls();
        $this->finishScope();
    }

    /**
     * Render the default aggregation table
     */
    public function renderTable($rendercontext)
    {
        $this->renderer->table_open();

        // header
        $this->renderer->tablethead_open();
        $this->renderColumnHeaders();
        $this->renderDynamicFilters();
        $this->renderer->tablethead_close();

        if ($this->resultCount) {
            // actual data
            $this->renderer->tabletbody_open();
            $this->renderResult();
            $this->renderer->tabletbody_close();

            // footer (tfoot is develonly currently)
            if (method_exists($this->renderer, 'tabletfoot_open')) $this->renderer->tabletfoot_open();
            $this->renderSums();
            $this->renderPagingControls();
            if (method_exists($this->renderer, 'tabletfoot_close')) $this->renderer->tabletfoot_close();
        } else {
            // nothing found
            $this->renderEmptyResult();
        }

        // table close
        $this->renderer->table_close();
    }

    /**
     * Adds additional info to document and renderer in XHTML mode
     *
     * @see finishScope()
     */
    protected function startScope()
    {
        // unique identifier for this aggregation
        $this->renderer->info['struct_table_hash'] = md5(var_export($this->data, true));

        // wrapping div
        if ($this->mode != 'xhtml') return;
        $this->renderer->doc .= "<div class=\"structaggregation\">";
    }

    /**
     * Closes the table and anything opened in startScope()
     *
     * @see startScope()
     */
    protected function finishScope()
    {
        // remove identifier from renderer again
        if (isset($this->renderer->info['struct_table_hash'])) {
            unset($this->renderer->info['struct_table_hash']);
        }

        // wrapping div
        if ($this->mode != 'xhtml') return;
        $this->renderer->doc .= '</div>';
    }

    /**
     * Displays info about the currently applied filters
     */
    protected function renderActiveFilters()
    {
        if ($this->mode != 'xhtml') return;
        $dynamic = $this->searchConfig->getDynamicParameters();
        $filters = $dynamic->getFilters();
        if (!$filters) return;

        $fltrs = array();
        foreach ($filters as $column => $filter) {
            list($comp, $value) = $filter;

            // display the filters in a human readable format
            foreach ($this->columns as $col) {
                if ($column === $col->getFullQualifiedLabel()) {
                    $column = $col->getTranslatedLabel();
                }
            }
            $fltrs[] = sprintf('"%s" %s "%s"', $column, $this->helper->getLang("comparator $comp"), $value);
        }

        $this->renderer->doc .= '<div class="filter">';
        $this->renderer->doc .= '<h4>' . sprintf($this->helper->getLang('tablefilteredby'), hsc(implode(' & ', $fltrs))) . '</h4>';
        $this->renderer->doc .= '<div class="resetfilter">';
        $this->renderer->internallink($this->id, $this->helper->getLang('tableresetfilter'));
        $this->renderer->doc .= '</div>';
        $this->renderer->doc .= '</div>';
    }

    /**
     * Shows the column headers with links to sort by column
     */
    protected function renderColumnHeaders()
    {
        $this->renderer->tablerow_open();

        // additional column for row numbers
        if (!empty($this->data['rownumbers'])) {
            $this->renderer->tableheader_open();
            $this->renderer->cdata('#');
            $this->renderer->tableheader_close();
        }

        // show all headers
        foreach ($this->columns as $num => $column) {
            $header = '';
            if (isset($this->data['headers'][$num])) {
                $header = $this->data['headers'][$num];
            }

            // use field label if no header was set
            if (blank($header)) {
                if (is_a($column, 'dokuwiki\plugin\struct\meta\Column')) {
                    $header = $column->getTranslatedLabel();
                } else {
                    $header = 'column ' . $num; // this should never happen
                }
            }

            // simple mode first
            if ($this->mode != 'xhtml') {
                $this->renderer->tableheader_open();
                $this->renderer->cdata($header);
                $this->renderer->tableheader_close();
                continue;
            }

            // still here? create custom header for more flexibility

            // width setting, widths are prevalidated, no escape needed
            $width = '';
            if (isset($this->data['widths'][$num]) && $this->data['widths'][$num] != '-') {
                $width = ' style="min-width: ' . $this->data['widths'][$num] . ';' .
                         'max-width: ' . $this->data['widths'][$num] . ';"';
            }

            // prepare data attribute for inline edits
            if (
                !is_a($column, '\dokuwiki\plugin\struct\meta\PageColumn') &&
                !is_a($column, '\dokuwiki\plugin\struct\meta\RevisionColumn')
            ) {
                $data = 'data-field="' . hsc($column->getFullQualifiedLabel()) . '"';
            } else {
                $data = '';
            }

            // sort indicator and link
            $sortclass = '';
            $sorts = $this->searchConfig->getSorts();
            $dynamic = $this->searchConfig->getDynamicParameters();
            $dynamic->setSort($column, true);
            if (isset($sorts[$column->getFullQualifiedLabel()])) {
                list(/*colname*/, $currentSort) = $sorts[$column->getFullQualifiedLabel()];
                if ($currentSort) {
                    $sortclass = 'sort-down';
                    $dynamic->setSort($column, false);
                } else {
                    $sortclass = 'sort-up';
                }
            }
            $link = wl($this->id, $dynamic->getURLParameters());

            // output XHTML header
            $this->renderer->doc .= "<th $width $data>";
            $this->renderer->doc .= '<a href="' . $link . '" class="' . $sortclass . '" title="' . $this->helper->getLang('sort') . '">' . hsc($header) . '</a>';
            $this->renderer->doc .= '</th>';
        }

        $this->renderer->tablerow_close();
    }

    /**
     * Is the result set currently dynamically filtered?
     * @return bool
     */
    protected function isDynamicallyFiltered()
    {
        if ($this->mode != 'xhtml') return false;
        if (!$this->data['dynfilters']) return false;

        $dynamic = $this->searchConfig->getDynamicParameters();
        return (bool) $dynamic->getFilters();
    }

    /**
     * Add input fields for dynamic filtering
     */
    protected function renderDynamicFilters()
    {
        if ($this->mode != 'xhtml') return;
        if (!$this->data['dynfilters']) return;
        if (is_a($this->renderer, 'renderer_plugin_dw2pdf')) {
            return;
        }
        global $conf;

        $this->renderer->doc .= '<tr class="dataflt">';

        // add extra column for row numbers
        if ($this->data['rownumbers']) {
            $this->renderer->doc .= '<th></th>';
        }

        // each column gets a form
        foreach ($this->columns as $column) {
            $this->renderer->doc .= '<th>';
            {
                $form = new \Doku_Form(array('method' => 'GET', 'action' => wl($this->id)));
                unset($form->_hidden['sectok']); // we don't need it here
                if (!$conf['userewrite']) $form->addHidden('id', $this->id);

                // current value
                $dynamic = $this->searchConfig->getDynamicParameters();
                $filters = $dynamic->getFilters();
            if (isset($filters[$column->getFullQualifiedLabel()])) {
                list(, $current) = $filters[$column->getFullQualifiedLabel()];
                $dynamic->removeFilter($column);
            } else {
                $current = '';
            }

                // Add current request params
                $params = $dynamic->getURLParameters();
            foreach ($params as $key => $val) {
                $form->addHidden($key, $val);
            }

                // add input field
                $key = $column->getFullQualifiedLabel() . $column->getType()->getDefaultComparator();
                $form->addElement(form_makeField('text', SearchConfigParameters::$PARAM_FILTER . '[' . $key . ']', $current, ''));
                $this->renderer->doc .= $form->getForm();
            }
            $this->renderer->doc .= '</th>';
        }
        $this->renderer->doc .= '</tr>';
    }

    /**
     * Display the actual table data
     */
    protected function renderResult()
    {
        foreach ($this->result as $rownum => $row) {
            $data = array(
                'id' => $this->id,
                'mode' => $this->mode,
                'renderer' => $this->renderer,
                'searchConfig' => $this->searchConfig,
                'data' => $this->data,
                'rownum' => &$rownum,
                'row' => &$row,
            );
            $evt = new \Doku_Event('PLUGIN_STRUCT_AGGREGATIONTABLE_RENDERRESULTROW', $data);
            if ($evt->advise_before()) {
                $this->renderResultRow($rownum, $row);
            }
            $evt->advise_after();
        }
    }

    /**
     * Render a single result row
     *
     * @param int $rownum
     * @param array $row
     */
    protected function renderResultRow($rownum, $row)
    {
        $this->renderer->tablerow_open();

        // add data attribute for inline edit
        if ($this->mode == 'xhtml') {
            $pid = $this->resultPIDs[$rownum];
            $rid = $this->resultRids[$rownum];
            $rev = $this->resultRevs[$rownum];
            $this->renderer->doc = substr(rtrim($this->renderer->doc), 0, -1); // remove closing '>'
            $this->renderer->doc .= ' data-pid="' . hsc($pid) . '" data-rev="' . $rev . '" data-rid="' . $rid . '">';
        }

        // row number column
        if (!empty($this->data['rownumbers'])) {
            $this->renderer->tablecell_open();
            $searchConfigConf = $this->searchConfig->getConf();
            $this->renderer->cdata($rownum + $searchConfigConf['offset'] + 1);
            $this->renderer->tablecell_close();
        }

        /** @var Value $value */
        foreach ($row as $colnum => $value) {
            $align = isset($this->data['align'][$colnum]) ?  $this->data['align'][$colnum] : null;
            $this->renderer->tablecell_open(1, $align);
            $value->render($this->renderer, $this->mode);
            $this->renderer->tablecell_close();

            // summarize
            if ($this->data['summarize'] && is_numeric($value->getValue())) {
                if (!isset($this->sums[$colnum])) {
                    $this->sums[$colnum] = 0;
                }
                $this->sums[$colnum] += $value->getValue();
            }
        }
        $this->renderer->tablerow_close();
    }

    /**
     * Renders an information row for when no results were found
     */
    protected function renderEmptyResult()
    {
        $this->renderer->tablerow_open();
        $this->renderer->tablecell_open(count($this->columns) + $this->data['rownumbers'], 'center');
        $this->renderer->cdata($this->helper->getLang('none'));
        $this->renderer->tablecell_close();
        $this->renderer->tablerow_close();
    }

    /**
     * Add sums if wanted
     */
    protected function renderSums()
    {
        if (empty($this->data['summarize'])) return;

        $this->renderer->info['struct_table_meta'] = true;
        if ($this->mode == 'xhtml') {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            $this->renderer->tablerow_open('summarize');
        } else {
            $this->renderer->tablerow_open();
        }

        if ($this->data['rownumbers']) {
            $this->renderer->tableheader_open();
            $this->renderer->tableheader_close();
        }

        $len = count($this->columns);
        for ($i = 0; $i < $len; $i++) {
            $this->renderer->tableheader_open(1, $this->data['align'][$i]);
            if (!empty($this->sums[$i])) {
                $this->renderer->cdata('∑ ');
                $this->columns[$i]->getType()->renderValue($this->sums[$i], $this->renderer, $this->mode);
            } else {
                if ($this->mode == 'xhtml') {
                    $this->renderer->doc .= '&nbsp;';
                }
            }
            $this->renderer->tableheader_close();
        }
        $this->renderer->tablerow_close();
        $this->renderer->info['struct_table_meta'] = false;
    }

    /**
     * Adds paging controls to the table
     */
    protected function renderPagingControls()
    {
        if (empty($this->data['limit'])) return;
        if ($this->mode != 'xhtml') return;

        $this->renderer->info['struct_table_meta'] = true;
        $this->renderer->tablerow_open();
        $this->renderer->tableheader_open((count($this->columns) + ($this->data['rownumbers'] ? 1 : 0)));
        $offset = $this->data['offset'];

        // prev link
        if ($offset) {
            $prev = $offset - $this->data['limit'];
            if ($prev < 0) {
                $prev = 0;
            }

            $dynamic = $this->searchConfig->getDynamicParameters();
            $dynamic->setOffset($prev);
            $link = wl($this->id, $dynamic->getURLParameters());
            $this->renderer->doc .= '<a href="' . $link . '" class="prev">' . $this->helper->getLang('prev') . '</a>';
        }

        // next link
        if ($this->resultCount > $offset + $this->data['limit']) {
            $next = $offset + $this->data['limit'];
            $dynamic = $this->searchConfig->getDynamicParameters();
            $dynamic->setOffset($next);
            $link = wl($this->id, $dynamic->getURLParameters());
            $this->renderer->doc .= '<a href="' . $link . '" class="next">' . $this->helper->getLang('next') . '</a>';
        }

        $this->renderer->tableheader_close();
        $this->renderer->tablerow_close();
        $this->renderer->info['struct_table_meta'] = true;
    }

    /**
     * Adds CSV export controls
     */
    protected function renderExportControls()
    {
        if ($this->mode != 'xhtml') return;
        if (empty($this->data['csv'])) return;
        if (!$this->resultCount) return;

        $dynamic = $this->searchConfig->getDynamicParameters();
        $params = $dynamic->getURLParameters();
        $params['hash'] = $this->renderer->info['struct_table_hash'];

        // FIXME apply dynamic filters
        $link = exportlink($this->id, 'struct_csv', $params);

        $this->renderer->doc .= '<a href="' . $link . '" class="export mediafile mf_csv">' . $this->helper->getLang('csvexport') . '</a>';
    }
}
