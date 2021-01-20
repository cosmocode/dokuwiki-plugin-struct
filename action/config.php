<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\types\AbstractBaseType;

class action_plugin_struct_config extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'addJsinfo');
    }

    /**
     * Reconfigure config for a given type
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     */
    public function handleAjax(Doku_Event $event, $param)
    {
        if ($event->data != 'plugin_struct_config') return;
        $event->preventDefault();
        $event->stopPropagation();
        global $INPUT;

        $conf = json_decode($INPUT->str('conf'), true);
        $typeclasses = Column::allTypes();
        $class = $typeclasses[$INPUT->str('type', 'Text')];
        /** @var AbstractBaseType $type */
        $type = new $class($conf);

        header('Content-Type: text/plain'); // we need the encoded string, not decoded by jQuery
        echo json_encode($type->getConfig());
    }

    /**
     * Add config options to JSINFO
     *
     * @param Doku_Event $event
     */
    public function addJsinfo(Doku_Event $event)
    {
        global $JSINFO;
        $JSINFO['plugins']['struct']['disableDeleteSerial'] = $this->getConf('disableDeleteSerial');
    }
}
