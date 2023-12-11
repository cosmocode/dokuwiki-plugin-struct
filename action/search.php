<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;

/**
 * Inject struct data into indexed pages and search result snippets
 */
class action_plugin_struct_search extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, 'handleIndexing');
        $controller->register_hook('FULLTEXT_SNIPPET_CREATE', 'BEFORE', $this, 'handleSnippets');
    }

    /**
     * Adds the structured data to the page body to be indexed
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleIndexing(Event $event, $param)
    {
        $id = $event->data['page'];
        $assignments = Assignments::getInstance();
        $tables = $assignments->getPageAssignments($id);
        if (!$tables) return;

        foreach ($tables as $table) {
            $schemadata = AccessTable::getPageAccess($table, $id);
            $event->data['body'] .= $schemadata->getDataPseudoSyntax();
        }
    }

    /**
     * Adds the structured data to the page body to be snippeted
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleSnippets(Event $event, $param)
    {
        $id = $event->data['id'];
        $assignments = Assignments::getInstance();
        $tables = $assignments->getPageAssignments($id);
        if (!$tables) return;

        foreach ($tables as $table) {
            $schemadata = AccessTable::getPageAccess($table, $id);
            $event->data['text'] .= $schemadata->getDataPseudoSyntax();
        }
    }
}

// vim:ts=4:sw=4:et:
