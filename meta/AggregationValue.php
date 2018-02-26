<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AggregationValue
 *
 * @package dokuwiki\plugin\struct\meta
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Iain Hallam <iain@nineworlds.net>
 */
class AggregationValue {

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
     * @var Column the column to be displayed
     */
    protected $column;
    
    /**
     * @var  Value[][] the search result
     */
    protected $result;

    /**
     * @var int number of all results
     */
    protected $resultCount;

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
    public function __construct($id, $mode, \Doku_Renderer $renderer, SearchConfig $searchConfig) {
        // Parameters
        $this->id = $id;
        $this->mode = $mode;
        $this->renderer = $renderer;
        $this->searchConfig = $searchConfig;

        // Search info
        $this->data = $this->searchConfig->getConf();
        $columns = $this->searchConfig->getColumns();
        $this->column = $columns[0];

        // limit to first result
        $this->searchConfig->setLimit(1);
        $this->searchConfig->setOffset(0);

        // Run the search
        $result = $this->searchConfig->execute();
        $this->resultCount = $this->searchConfig->getCount();

        // Change from two-dimensional array with one entry to one-dimensional array
        $this->result = $result[0];

        // Load helper
        $this->helper = plugin_load('helper', 'struct_config');
    }

    /**
     * Create the output on the renderer
     * 
     * @param  int  $show_not_found  Whether to display the default text for no records
     */
    public function render($show_not_found = 0) {
        $this->startScope();

        // Check that we actually got a result
        if ($this->resultCount) {
            $this->renderValue($this->result);
        } else {
            if ($show_not_found) {
                $this->renderer->cdata($this->helper->getLang('none'));
            }
        }

        $this->finishScope();

        return;
    }

    /**
     * Adds additional info to document and renderer in XHTML mode
     *
     * @see finishScope()
     */
    protected function startScope() {
        // wrapping span
        if($this->mode != 'xhtml') return;
        $this->renderer->doc .= "<span class=\"structaggregation valueaggregation\">";
    }

    /**
     * Closes anything opened in startScope()
     *
     * @see startScope()
     */
    protected function finishScope() {
        // wrapping span
        if($this->mode != 'xhtml') return;
        $this->renderer->doc .= '</span>';
    }

    /**
     * @param $resultrow
     */
    protected function renderValue($resultrow) {
        // @var  Value  $value
        foreach ($resultrow as $column => $value) {
            if ($value->isEmpty()) {
                continue;
            }
            if ($this->mode == 'xhtml') {
                $type = 'struct_' . strtolower($value->getColumn()->getType()->getClass());
                $this->renderer->doc .= '<span class="' . $type . '">';
            }
            $value->render($this->renderer, $this->mode);
            if ($this->mode == 'xhtml') {
                $this->renderer->doc .= '</span>';
            }
        }

    }
}
