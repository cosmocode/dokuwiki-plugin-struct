<?php
/**
 * DokuWiki Plugin struct (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\AccessTableData;
use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\SerialTable;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\SearchConfig;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\Value;

/**
 * Class action_plugin_struct_serial
 *
 * Handle serial editing
 */
class action_plugin_struct_serial extends DokuWiki_Action_Plugin
{

    /** @var  AccessTableData */
    protected $schemadata = null;

    /** @var  Column */
    protected $column = null;

    /** @var String */
    protected $pid = '';

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
     * @param Doku_Event $event
     * @param $param
     */
    public function handleAjax(Doku_Event $event, $param)
    {
        $len = strlen('plugin_struct_serial_');
        if (substr($event->data, 0, $len) != 'plugin_struct_serial_') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        try {

            if (substr($event->data, $len) == 'new') {
                $this->serialNew();
            }

            if (substr($event->data, $len) == 'save') {
                $this->serialSave();
            }

            if (substr($event->data, $len) == 'delete') {
                $this->serialDelete();
            }

        } catch (StructException $e) {
            http_status(500);
            header('Content-Type: text/plain');
            echo $e->getMessage();
        }
    }

    /**
     * Deletes a serial row
     */
    protected function serialDelete()
    {
        global $INPUT;
        $tablename = $INPUT->str('schema');
        $pid = $INPUT->str('pid');
        $rid = $INPUT->int('rid');
        if (!$rid || !$pid) {
            throw new StructException('Page or row id is missing');
        }
        if (!$tablename) {
            throw new StructException('No schema given');
        }
        action_plugin_struct_inline::checkCSRF();

        $schemadata = AccessTable::byTableName($tablename, $this->pid, 0, $rid);
        if (!$schemadata->getSchema()->isEditable()) {
            throw new StructException('serial delete error: no permission for schema');
        }
        $schemadata->clearData();
    }

    /**
     * Save one new serial row
     */
    protected function serialSave()
    {
        global $INPUT;
        $tablename = $INPUT->str('schema');
        $pid = $INPUT->str('pid');
        $data = $INPUT->arr('entry');
        action_plugin_struct_inline::checkCSRF();

        // create a new row based on the original aggregation config for the new pid
        $access = AccessTable::byTableName($tablename, $pid, 0, 0);

        /** @var helper_plugin_struct $helper */
        $helper = plugin_load('helper', 'struct');
        $helper->saveSerialData($access, $data);

        $rid = $access->getRid();

        $config = json_decode($INPUT->str('searchconf'), true);
        $config['filter'] = [['%rowid%', '=', $rid, 'AND']];
        $config['filter'] = [['%pageid%', '=', $pid, 'AND']];
        $serial = new SerialTable(
            '', // current page doesn't matter
            'xhtml',
            new Doku_Renderer_xhtml(),
            new SearchConfig($config)
        );

        echo $serial->getFirstRow();
    }

    /**
     * Create the Editor for a new serial row
     */
    protected function serialNew()
    {
        global $INPUT;
        global $lang;
        $tablename = $INPUT->str('schema');

        $schema = new Schema($tablename);
        if (!$schema->isEditable()) {
            return;
        } // no permissions, no editor

        echo '<div class="struct_entry_form">';
        echo '<fieldset>';
        echo '<legend>' . $this->getLang('lookup new entry') . '</legend>';
        /** @var action_plugin_struct_edit $edit */
        $edit = plugin_load('action', 'struct_edit');
        foreach ($schema->getColumns(false) as $column) {
            $label = $column->getLabel();
            $field = new Value($column, '');
            echo $edit->makeField($field, "entry[$label]");
        }
        formSecurityToken(); // csrf protection
        echo '<input type="hidden" name="call" value="plugin_struct_serial_save" />';
        echo '<input type="hidden" name="schema" value="' . hsc($tablename) . '" />';

        echo '<button type="submit">' . $lang['btn_save'] . '</button>';

        echo '<div class="err"></div>';
        echo '</fieldset>';
        echo '</div>';

    }

}
