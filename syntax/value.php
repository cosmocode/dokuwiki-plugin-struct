<?php

/**
 * DokuWiki Plugin struct (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Iain Hallam <iain@nineworlds.net>
 */

// phpcs:disable PSR1.Files.SideEffects

use dokuwiki\plugin\struct\meta\AggregationValue;
use dokuwiki\plugin\struct\meta\InlineConfigParser;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;

// phpcs:ignore PSR1.Classes.ClassDeclaration, Squiz.Classes.ValidClassName
class syntax_plugin_struct_value extends DokuWiki_Syntax_Plugin
{
    /**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        /* 315 to place above Doku_Parser_Mode_media, which would
         * otherwise take precedence. See
         * https://www.dokuwiki.org/devel:parser:getsort_list
         */
        return 315;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
        // {{$...}}
        $this->Lexer->addSpecialPattern('\{\{\$[^}]+\}\}', $mode, 'plugin_struct_value');
    }

    /**
     * Handle matches of the struct syntax
     *
     * @param string $match The match of the syntax
     * @param int $state The state of the handler
     * @param int $pos The position in the document
     * @param Doku_Handler $handler The handler
     * @return  array                   Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $conf;

        try {
            // strip {{$ and }} markers
            $inline = substr($match, 3, -2);

            // Parse inline syntax
            $parser = new InlineConfigParser($inline);
            $config = $parser->getConfig();

            return $config;
        } catch (StructException $e) {
            msg($e->getMessage(), -1, $e->getLine(), $e->getFile());
            if ($conf['allowdebug']) {
                msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
            }
            return null;
        }
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     * @return  bool                      If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if (!$data) {
            return false;
        }
        global $INFO;
        global $conf;

        // Get configuration
        $show_not_found = $this->getConf('show_not_found');

        try {
            /** @var SearchConfig $search */
            $search = new SearchConfig($data);

            /** @var AggregationValue $value */
            $value = new AggregationValue($INFO['id'], $mode, $renderer, $search);
            $value->render($show_not_found);

            if ($mode == 'metadata') {
                /** @var Doku_Renderer_metadata $renderer */
                $renderer->meta['plugin']['struct']['hasaggregation'] = $search->getCacheFlag();
            }
        } catch (StructException $e) {
            msg($e->getMessage(), -1, $e->getLine(), $e->getFile());
            if ($conf['allowdebug']) {
                msg('<pre>' . hsc($e->getTraceAsString()) . '</pre>', -1);
            }
        }

        return true;
    }
}
