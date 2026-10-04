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
use dokuwiki\Form\Form;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\Assignments;
use dokuwiki\plugin\struct\meta\Value;

/**
 * Class action_plugin_struct_entry
 *
 * Handles adding struct forms to the default editor
 */
class action_plugin_struct_edit extends ActionPlugin
{
    /**
     * @var string The form name we use to transfer schema data
     */
    protected static $VAR = 'struct_schema_data';

    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(EventHandler $controller)
    {
        // add the struct editor to the edit form;
        $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handleEditform');
        $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'addFromData');
    }

    /**
     * Adds the html for the struct editors to the edit from
     *
     * Handles the FORM_EDIT_OUTPUT event
     *
     * @return bool
     */
    public function addFromData(Event $event, $_param)
    {
        $html = $this->getEditorHtml();

        /** @var Form $form */
        $form = $event->data;
        $pos = $form->findPositionByAttribute('id', 'wiki__editbar'); // insert the form before the main buttons
        $form->addHTML($html, $pos);

        return true;
    }

    /**
     * Enhance the editing form with structural data editing
     *
     * TODO: Remove this after HTML_EDITFORM_OUTPUT is no longer released in DokuWiki stable
     *
     * @param Event $event event object by reference
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return bool
     */
    public function handleEditform(Event $event, $param)
    {
        $html = $this->getEditorHtml();

        /** @var Doku_Form $form */
        $form = $event->data;
        $pos = $form->findElementById('wiki__editbar'); // insert the form before the main buttons
        $form->insertElement($pos, $html);

        return true;
    }

    /**
     * @return string
     */
    private function getEditorHtml()
    {
        global $ID;

        $assignments = Assignments::getInstance();
        $tables = $assignments->getPageAssignments($ID);

        $html = '';
        foreach ($tables as $table) {
            $html .= $this->createForm($table);
        }

        return "<div class=\"struct_entry_form\">$html</div>";
    }

    /**
     * Create the form to edit schemadata
     *
     * @param string $tablename
     * @return string The HTML for this schema's form
     */
    protected function createForm($tablename)
    {
        global $ID;
        global $REV;
        global $INPUT;
        if (auth_quickaclcheck($ID) == AUTH_READ) return '';
        if (checklock($ID)) return '';
        $ts = $REV ?: time();
        $schema = AccessTable::getPageAccess($tablename, $ID, $ts);
        if (!$schema->getSchema()->isEditable()) {
            return '';
        }
        $schemadata = $schema->getData();

        $structdata = $INPUT->arr(self::$VAR);
        if (isset($structdata[$tablename])) {
            $postdata = $structdata[$tablename];
        } else {
            $postdata = [];
        }

        // we need a short, unique identifier to use in the cookie. this should be good enough
        $schemaid = 'SRCT' . substr(str_replace(['+', '/'], '', base64_encode(sha1($tablename, true))), 0, 5);
        $html = '<fieldset data-schema="' . $schemaid . '">';
        $html .= '<legend>' . hsc($schema->getSchema()->getTranslatedLabel()) . '</legend>';
        foreach ($schemadata as $field) {
            $label = $field->getColumn()->getLabel();
            if (isset($postdata[$label])) {
                // posted data trumps stored data
                $data = $postdata[$label];
                if (is_array($data)) {
                    $data = array_map("cleanText", $data);
                } else {
                    $data = cleanText($data);
                }
                $field->setValue($data, true);
            }
            $html .= $this->makeField($field, self::$VAR . "[$tablename][$label]");
        }
        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Create the input field
     *
     * @param Value $field
     * @param String $name field's name
     * @return string
     */
    public function makeField(Value $field, $name)
    {
        $trans = hsc($field->getColumn()->getTranslatedLabel());
        $hint = hsc($field->getColumn()->getTranslatedHint());
        $class = $hint ? 'hashint' : '';
        $colname = $field->getColumn()->getFullQualifiedLabel();

        $id = uniqid('struct__', false);
        $input = $field->getValueEditor($name, $id);

        // we keep all the custom form stuff the field might produce, but hide it
        if (!$field->getColumn()->isVisibleInEditor()) {
            $hide = 'style="display:none"';
        } else {
            $hide = '';
        }

        $html = '<div class="field">';
        $html .= "<label $hide data-column=\"$colname\" for=\"$id\">";
        $html .= "<span class=\"label $class\" title=\"$hint\">$trans</span>";
        $html .= '</label>';
        $html .= "<span class=\"input\">$input</span>";
        $html .= '</div>';

        return $html;
    }
}

// vim:ts=4:sw=4:et:
