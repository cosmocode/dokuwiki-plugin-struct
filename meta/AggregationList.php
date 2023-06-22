<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AggregationList
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AggregationList
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
    protected $resultColumnCount;

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
        $this->resultColumnCount = count($this->columns);
        $this->resultPIDs = $this->searchConfig->getPids();
    }

    /**
     * Create the list on the renderer
     */
    public function render()
    {
        $nestedResult = new NestedResult($this->result);
        $root = $nestedResult->getRoot($this->data['nesting']);

        $this->startScope();
        $this->renderNode($root);
        $this->finishScope();
    }

    /**
     * Recursively render the result tree
     *
     * @param NestedValue $node
     * @return void
     */
    protected function renderNode(NestedValue $node)
    {
        $self = $node->getValueObject(); // null for root node
        $children = $node->getChildren();
        $results = $node->getResultRows();

        // all our content is in a listitem, unless we are the root node
        if ($self) {
            $this->renderer->listitem_open($node->getDepth() + 1); // levels are 1 based
        }

        // render own value if available
        if ($self) {
            $this->renderer->listcontent_open();
            $this->renderListItem([$self], $node->getDepth()); // zero based depth
            $this->renderer->listcontent_close();
        }

        // render children or results as sub-list
        if ($children || $results) {
            $this->renderer->listu_open();

            foreach ($children as $child) {
                $this->renderNode($child);
            }

            foreach ($results as $result) {
                $this->renderer->listitem_open($node->getDepth() + 2); // levels are 1 based, this is one deeper
                $this->renderer->listcontent_open();
                $this->renderListItem($result, $node->getDepth() + 1); // zero based depth, one deeper
                $this->renderer->listcontent_close();
                $this->renderer->listitem_close();
            }

            $this->renderer->listu_close();
        }

        // close listitem if opened
        if ($self) {
            $this->renderer->listitem_close();
        }
    }

    /**
     * Adds additional info to document and renderer in XHTML mode
     *
     * @see finishScope()
     */
    protected function startScope()
    {
        // wrapping div
        if ($this->mode != 'xhtml') return;
        $this->renderer->doc .= "<div class=\"structaggregation listaggregation\">";
    }

    /**
     * Closes anything opened in startScope()
     *
     * @see startScope()
     */
    protected function finishScope()
    {
        // wrapping div
        if ($this->mode != 'xhtml') return;
        $this->renderer->doc .= '</div>';
    }


    /**
     * Render the content of a single list item
     *
     * @param Value[] $resultrow
     * @param int $depth The current nesting depth (zero based)
     */
    protected function renderListItem($resultrow, $depth)
    {
        $sepbyheaders = $this->searchConfig->getConf()['sepbyheaders'];
        $headers = $this->searchConfig->getConf()['headers'];

        foreach ($resultrow as $index => $value) {
            if ($value->isEmpty()) continue;
            $column = $index + $depth; // the resultrow is shifted by the nesting depth
            if ($sepbyheaders && !empty($headers[$column])) {
                $header = $headers[$column];
            } else {
                $header = '';
            }

            if ($this->mode === 'xhtml') {
                $this->renderValueXHTML($value, $header);
            } else {
                $this->renderValueGeneric($value, $header);
            }
        }
    }

    /**
     * Render the given Value in a XHTML renderer
     * @param Value $value
     * @param string $header
     * @return void
     */
    protected function renderValueXHTML($value, $header)
    {
        $attributes = [
            'data-struct-column' => strtolower($value->getColumn()->getFullQualifiedLabel()),
            'data-struct-type' => strtolower($value->getColumn()->getType()->getClass()),
            'class' => 'li', // default dokuwiki content wrapper
        ];

        $this->renderer->doc .= sprintf('<div %s>', buildAttributes($attributes)); // wrapper
        if ($header !== '') {
            $this->renderer->doc .= sprintf('<span class="struct_header">%s</span> ', hsc($header));
        }
        $this->renderer->doc .= '<div class="struct_value">';
        $value->render($this->renderer, $this->mode);
        $this->renderer->doc .= '</div>';
        $this->renderer->doc .= '</div> '; // wrapper
    }

    /**
     * Render the given Value in any non-XHTML renderer
     * @param Value $value
     * @param string $header
     * @return void
     */
    protected function renderValueGeneric($value, $header)
    {
        $this->renderer->listcontent_open();
        if ($header !== '') $this->renderer->cdata($header . ' ');
        $value->render($this->renderer, $this->mode);
        $this->renderer->listcontent_close();
    }
}
