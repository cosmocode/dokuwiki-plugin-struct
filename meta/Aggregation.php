<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Base contract for Aggregations
 *
 * @package dokuwiki\plugin\struct\meta
 */
abstract class Aggregation {

    /** @var string the page id of the page this is rendered to */
    protected $id;

    /** @var string the Type of renderer used */
    protected $mode;

    /** @var \Doku_Renderer the DokuWiki renderer used to create the output */
    protected $renderer;

    /** @var SearchConfig the configured search - gives access to columns etc. */
    protected $searchConfig;

    /** @var Column[] the list of columns to be displayed */
    protected $columns;

    /** @var  Value[][] the search result */
    protected $result;

    /** @var int number of all results */
    protected $resultCount;

    /**
     * @todo we might be able to get rid of this helper and move this to SearchConfig
     * @var \helper_plugin_struct_config
     */
    protected $helper;

    /**
     * @var array the original configuration data
     */
    protected $data;

    /**
     * Initialize the Aggregation renderer and executes the search
     *
     * You need to call @param string $id
     * @param string $mode
     * @param \Doku_Renderer $renderer
     * @param SearchConfig $searchConfig
     * @see render() on the resulting object.
     *
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
        $this->helper = plugin_load('helper', 'struct_config');
    }

    /**
     * Returns the page id the aggregation is used on
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Create the table on the renderer
     * 
     * @param bool $showNotFound show a not found message when no data available?
     */
    abstract public function render($showNotFound = false);

}
