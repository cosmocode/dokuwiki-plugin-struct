<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Base contract for Aggregations
 *
 * @package dokuwiki\plugin\struct\meta
 */
abstract class Aggregation
{
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

    /** @var string usually a div, but AggregationValue needs to be wrapped in a span */
    protected $tagName = 'div';

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
     * You need to call startScope(), render() and finishScope() on the resulting object.
     *
     * @param string $id The page this is rendered to
     * @param string $mode The renderer format
     * @param \Doku_Renderer $renderer The renderer to use for output
     * @param SearchConfig $searchConfig The configured search object to use for displaying the data
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
     * Return the list of classes that should be added to the scope when rendering XHTML
     *
     * @return string[]
     */
    public function getScopeClasses()
    {
        // we're all aggregations
        $classes = ['structaggregation'];

        // which type of aggregation are we?
        $class = get_class($this);
        $class = substr($class, strrpos($class, "\\") + 1);
        $class = strtolower($class);
        $classes[] = 'struct' . $class;

        // config options
        if ($this->data['nesting']) {
            $classes[] = 'is-nested';
        }
        if ($this->data['index']) {
            $classes[] = 'is-indexed';
        }

        // custom classes
        $classes = array_merge($classes, $this->data['classes']);
        return $classes;
    }

    /**
     * Render the actual output to the renderer
     *
     * @param bool $showNotFound show a not found message when no data available?
     */
    abstract public function render($showNotFound = false);

    /**
     * Adds additional info to document and renderer in XHTML mode
     *
     * Called before render()
     *
     * @see finishScope()
     */
    public function startScope()
    {
        if ($this->mode == 'xhtml') {
            $classes = $this->getScopeClasses();

            $hash = $this->renderer->info['struct_table_hash'] ?? '';
            $id = $hash ? " id=\"$hash\" " : '';

            $this->renderer->doc .= '<' . $this->tagName .  $id . ' class="' . implode(' ', $classes) . '">';
        }
    }

    /**
     * Closes anything opened in startScope()
     *
     * Called after render()
     *
     * @see startScope()
     */
    public function finishScope()
    {
        if ($this->mode == 'xhtml') {
            $this->renderer->doc .= '</' . $this->tagName . '>';
        }
    }
}
