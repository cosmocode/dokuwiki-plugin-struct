<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\PageMeta;

/**
 * Class action_plugin_struct_title
 *
 * Saves the page title when meta data is saved
 */
class action_plugin_struct_title extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'handleMeta');
    }

    /**
     * Store the page's title
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handleMeta(Doku_Event $event, $param)
    {
        $id = $event->data['page'];

        try {
            $page = new PageMeta($id);

            // check if we already have data for the latest revision, or we risk redundant db writes
            $latest = $page->getPageData();
            if ($latest && (int) $latest['lastrev'] === $event->data['current']['last_change']['date']) {
                return;
            }

            if (!blank($event->data['current']['title'])) {
                $page->setTitle($event->data['current']['title']);
            } else {
                $page->setTitle(null);
            }

            if (!blank($event->data['current']['last_change']['date'])) {
                $page->setLastRevision($event->data['current']['last_change']['date']);
            } else {
                $page->setLastRevision(null);
            }

            if (!blank($event->data['current']['last_change']['user'])) {
                $page->setLastEditor($event->data['current']['last_change']['user']);
            } elseif (!blank($event->data['current']['last_change']['ip'])) {
                $page->setLastEditor($event->data['current']['last_change']['ip']);
            } else {
                $page->setLastEditor(null);
            }

            if (!blank($event->data['current']['last_change']['sum'])) {
                $page->setLastSummary($event->data['current']['last_change']['sum']);
            } else {
                $page->setLastSummary(null);
            }

            $page->savePageData();
        } catch (StructException $e) {
            msg($e->getMessage(), -1);
        }
    }
}
