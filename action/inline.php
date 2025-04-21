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
use dokuwiki\plugin\struct\meta\AccessTablePage;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\ValueValidator;

/**
 * Class action_plugin_struct_inline
 *
 * Handle inline editing
 */
class action_plugin_struct_inline extends ActionPlugin
{
    /** @var  AccessTablePage */
    protected $schemadata;

    /** @var  Column */
    protected $column;

    /** @var String */
    protected $pid = '';

    /** @var int */
    protected $rid = 0;

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
    }

    /**
     * @param Event $event
     * @param $param
     */
    public function handleAjax(Event $event, $param)
    {
        $len = strlen('plugin_struct_inline_');
        if (substr($event->data, 0, $len) != 'plugin_struct_inline_') return;
        $event->preventDefault();
        $event->stopPropagation();

        if (substr($event->data, $len) == 'editor') {
            $this->inlineEditor();
        }

        if (substr($event->data, $len) == 'save') {
            try {
                $this->inlineSave();
            } catch (StructException $e) {
                http_status(500);
                header('Content-Type: text/plain; charset=utf-8');
                echo $e->getMessage();
            }
        }

        if (substr($event->data, $len) == 'cancel') {
            $this->inlineCancel();
        }
    }

    /**
     * Creates the inline editor
     */
    protected function inlineEditor()
    {
        // silently fail when editing not possible
        if (!$this->initFromInput()) return;
        if (!$this->schemadata->getSchema()->isEditable()) return;
        // only check page permissions for data with pid, skip for global data
        if ($this->pid && auth_quickaclcheck($this->pid) < AUTH_EDIT) return;
        if (checklock($this->pid)) return;

        // lock page
        lock($this->pid);

        // output the editor
        $value = $this->schemadata->getDataColumn($this->column);
        $id = uniqid('struct__', false);
        echo '<div class="field">';
        echo '<label data-column="' . hsc($this->column->getFullQualifiedLabel()) . '" for="' . $id . '">';
        echo '</label>';
        echo '<span class="input">';
        echo $value->getValueEditor('entry', $id);
        echo '</span>';
        $hint = $this->column->getType()->getTranslatedHint();
        if ($hint) {
            echo '<p class="hint">';
            echo hsc($hint);
            echo '</p>';
        }
        echo '</div>';

        // csrf protection
        formSecurityToken();
    }

    /**
     * Save the data posted by the inline editor
     */
    protected function inlineSave()
    {
        global $INPUT;

        // check preconditions
        if (!$this->initFromInput()) {
            throw new StructException('inline save error: init');
        }
        self::checkCSRF();
        if (!$this->schemadata->getRid()) {
            $this->checkPage();
            $assignments = Assignments::getInstance();
            $tables = $assignments->getPageAssignments($this->pid, true);
            if (!in_array($this->schemadata->getSchema()->getTable(), $tables)) {
                throw new StructException('inline save error: schema not assigned to page');
            }
        }
        if (!$this->schemadata->getSchema()->isEditable()) {
            throw new StructException('inline save error: no permission for schema');
        }

        // validate
        $value = $INPUT->param('entry');
        $validator = new ValueValidator();
        if (!$validator->validateValue($this->column, $value)) {
            throw new StructException(implode("\n", $validator->getErrors()));
        }

        // current data
        $tosave = $this->schemadata->getDataArray();
        $tosave[$this->column->getLabel()] = $value;

        // save
        if ($this->schemadata->getRid()) {
            $revision = 0;
        } else {
            $revision = helper_plugin_struct::createPageRevision($this->pid, 'inline edit');
            p_get_metadata($this->pid); // reparse the metadata of the page top update the titles/rev/lasteditor table
        }
        $this->schemadata->setTimestamp($revision);
        try {
            if (!$this->schemadata->saveData($tosave)) {
                throw new StructException('saving failed');
            }
            if (!$this->schemadata->getRid()) {
                // make sure this schema is assigned
                /** @noinspection PhpUndefinedVariableInspection */
                $assignments->assignPageSchema(
                    $this->pid,
                    $this->schemadata->getSchema()->getTable()
                );
            }
        } catch (\Exception $e) {
            // PHP <7 needs a catch block
            throw $e;
        } finally {
            // unlock (unlocking a non-existing file is okay,
            // so we don't check if it's a lookup here
            unlock($this->pid);
        }

        // reinit then render
        $this->initFromInput($this->schemadata->getTimestamp());
        $value = $this->schemadata->getDataColumn($this->column);
        $R = new Doku_Renderer_xhtml();
        $value->render($R, 'xhtml'); // FIXME use configured default renderer
        $data = json_encode(['value' => $R->doc, 'rev' => $this->schemadata->getTimestamp()], JSON_THROW_ON_ERROR);
        echo $data;
    }

    /**
     * Unlock a page (on cancel action)
     */
    protected function inlineCancel()
    {
        global $INPUT;
        $pid = $INPUT->str('pid');
        unlock($pid);
    }

    /**
     * Initialize internal state based on input variables
     *
     * @param int $updatedRev timestamp of currently created revision, might be newer than input variable
     * @return bool if initialization was successful
     */
    protected function initFromInput($updatedRev = 0)
    {
        global $INPUT;

        $this->schemadata = null;
        $this->column = null;

        $pid = $INPUT->str('pid');
        $rid = $INPUT->int('rid');
        $rev = $updatedRev ?: $INPUT->int('rev');

        [$table, $field] = explode('.', $INPUT->str('field'), 2);
        if (blank($pid) && blank($rid)) return false;
        if (blank($table)) return false;
        if (blank($field)) return false;

        $this->pid = $pid;
        try {
            if (AccessTable::isTypePage($pid, $rev)) {
                $this->schemadata = AccessTable::getPageAccess($table, $pid);
            } elseif (AccessTable::isTypeSerial($pid, $rev)) {
                $this->schemadata = AccessTable::getSerialAccess($table, $pid, $rid);
            } else {
                $this->schemadata = AccessTable::getGlobalAccess($table, $rid);
            }
        } catch (StructException $ignore) {
            return false;
        }

        $this->column = $this->schemadata->getSchema()->findColumn($field);
        if (!$this->column || !$this->column->isVisibleInEditor()) {
            $this->schemadata = null;
            $this->column = null;
            return false;
        }

        return true;
    }

    /**
     * Checks if a page can be edited
     *
     * @throws StructException when check fails
     */
    protected function checkPage()
    {
        if (!page_exists($this->pid)) {
            throw new StructException('inline save error: no such page');
        }
        if (auth_quickaclcheck($this->pid) < AUTH_EDIT) {
            throw new StructException('inline save error: acl');
        }
        if (checklock($this->pid)) {
            throw new StructException('inline save error: lock');
        }
    }

    /**
     * Our own implementation of checkSecurityToken because we don't want the msg() call
     *
     * @throws StructException when check fails
     */
    public static function checkCSRF()
    {
        global $INPUT;
        if (
            $INPUT->server->str('REMOTE_USER') &&
            getSecurityToken() != $INPUT->str('sectok')
        ) {
            throw new StructException('CSRF check failed');
        }
    }
}
