<?php

/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\StructException;

class action_plugin_struct_ajax extends DokuWiki_Action_Plugin
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
    }

    /**
     * Pass Ajax call to a type
     *
     * @param Doku_Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     */
    public function handleAjax(Doku_Event $event, $param)
    {
        if ($event->data != 'plugin_struct') return;
        $event->preventDefault();
        $event->stopPropagation();
        global $conf;

        header('Content-Type: application/json');
        try {
            $result = $this->executeTypeAjax();
        } catch (StructException $e) {
            $result = array(
                'error' => $e->getMessage() . ' ' . basename($e->getFile()) . ':' . $e->getLine()
            );
            if ($conf['allowdebug']) {
                $result['stacktrace'] = $e->getTraceAsString();
            }
            http_status(500);
        }

        $json = new JSON();
        echo $json->encode($result);
    }

    /**
     * Check the input variables and run the AJAX call
     *
     * @throws StructException
     * @return mixed
     */
    protected function executeTypeAjax()
    {
        global $INPUT;

        $col = $INPUT->str('column');
        if (blank($col)) throw new StructException('No column provided');
        list($schema, $colname) = explode('.', $col, 2);
        if (blank($schema) || blank($colname)) throw new StructException('Column format is wrong');

        $schema = new Schema($schema);
        if (!$schema->getId()) throw new StructException('Unknown Schema');

        $column = $schema->findColumn($colname);
        if ($column === false) throw new StructException('Column not found');

        return $column->getType()->handleAjax();
    }
}
