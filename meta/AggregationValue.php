<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AggregationValue
 *
 * @package dokuwiki\plugin\struct\meta
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Iain Hallam <iain@nineworlds.net>
 */
class AggregationValue extends Aggregation
{
    /**
     * @var Column the column to be displayed
     */
    protected $column;

    /** @inheritdoc */
    public function __construct($id, $mode, \Doku_Renderer $renderer, SearchConfig $searchConfig)
    {
        // limit to first result
        $searchConfig->setLimit(1);
        $searchConfig->setOffset(0);

        parent::__construct($id, $mode, $renderer, $searchConfig);
    }

    /**
     * Create the output on the renderer
     *
     * @param int $show_not_found Whether to display the default text for no records
     */
    public function render($show_not_found = 0)
    {
        $this->startScope();

        // Check that we actually got a result
        if ($this->resultCount) {
            $this->renderValue($this->result[0]); // only one result
        } else {
            if ($show_not_found) {
                $this->renderer->cdata($this->helper->getLang('none'));
            }
        }

        $this->finishScope();
    }

    /**
     * Adds additional info to document and renderer in XHTML mode
     *
     * @see finishScope()
     */
    protected function startScope()
    {
        // wrapping span
        if ($this->mode != 'xhtml') {
            return;
        }
        $this->renderer->doc .= "<span class=\"structaggregation valueaggregation\">";
    }

    /**
     * Closes anything opened in startScope()
     *
     * @see startScope()
     */
    protected function finishScope()
    {
        // wrapping span
        if ($this->mode != 'xhtml') {
            return;
        }
        $this->renderer->doc .= '</span>';
    }

    /**
     * @param Value[] $resultrow
     */
    protected function renderValue($resultrow)
    {
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
