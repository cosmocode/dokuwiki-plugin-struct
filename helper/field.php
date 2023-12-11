<?php

use dokuwiki\plugin\struct\meta\Column;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\Value;
use dokuwiki\plugin\struct\meta\ValueValidator;
use dokuwiki\plugin\struct\types\Lookup;
use dokuwiki\plugin\struct\types\Page;
use dokuwiki\plugin\struct\types\User;

/**
 * Allows adding a single struct field as a bureaucracy field
 *
 * This class is used when a field of the type struct_field is encountered in the
 * bureaucracy syntax.
 */
class helper_plugin_struct_field extends helper_plugin_bureaucracy_field
{
    /** @var  Column */
    public $column;

    /**
     * Initialize the appropriate column
     *
     * @param array $args
     */
    public function initialize($args)
    {
        $this->init($args);

        // find the column
        try {
            $this->column = $this->findColumn($this->opt['label']);
        } catch (StructException $e) {
            msg(hsc($e->getMessage()), -1);
        }

        $this->standardArgs($args);
    }

    /**
     * Sets the value and validates it
     *
     * @param mixed $value
     * @return bool value was set successfully validated
     */
    protected function setVal($value)
    {
        if (!$this->column) {
            $value = '';
            //don't validate placeholders here
        } elseif ($this->replace($value) == $value) {
            $validator = new ValueValidator();
            $this->error = !$validator->validateValue($this->column, $value);
            if ($this->error) {
                foreach ($validator->getErrors() as $error) {
                    msg(hsc($error), -1);
                }
            }
        }

        if ($value === [] || $value === '') {
            if (!isset($this->opt['optional'])) {
                $this->error = true;
                if ($this->column) {
                    $label = $this->column->getTranslatedLabel();
                } else {
                    $label = $this->opt['label'];
                }
                msg(sprintf($this->getLang('e_required'), hsc($label)), -1);
            }
        }

        $this->opt['value'] = $value;
        return !$this->error;
    }

    /**
     * Creates the HTML for the field
     *
     * @param array $params
     * @param Doku_Form $form
     * @param int $formid
     */
    public function renderfield($params, Doku_Form $form, $formid)
    {
        if (!$this->column) return;

        // this is what parent does
        $this->_handlePreload();
        if (!$form->_infieldset) {
            $form->startFieldset('');
        }
        if ($this->error) {
            $params['class'] = 'bureaucracy_error';
        }

        // output the field
        $value = $this->createValue();
        $field = $this->makeField($value, $params['name']);
        $form->addElement($field);
    }

    /**
     * Adds replacement for type user to the parent method
     *
     * @return array|mixed|string
     */
    public function getReplacementValue()
    {
        $value = $this->getParam('value');

        if (is_array($value)) {
            return [$this, 'replacementMultiValueCallback'];
        }

        if (!empty($value) && $this->column->getType() instanceof User) {
            return userlink($value, true);
        }

        return parent::getReplacementValue();
    }

    /**
     * Adds handling of type user to the parent method
     *
     * @param $matches
     * @return string
     */
    public function replacementMultiValueCallback($matches)
    {
        $value = $this->opt['value'];

        //default value
        if (is_null($value) || $value === false) {
            if (isset($matches['default']) && $matches['default'] != '') {
                return $matches['default'];
            }
            return $matches[0];
        }

        if (!empty($value) && $this->column->getType() instanceof User) {
            $value = array_map(static fn($user) => userlink($user, true), $value);
        }

        //check if matched string containts a pair of brackets
        $delimiter = preg_match('/\(.*\)/s', $matches[0]) ? $matches['delimiter'] : ', ';

        return implode($delimiter, $value);
    }

    /**
     * Returns a Value object for the current column.
     * Special handling for Page and Lookup literal form values.
     *
     * @return Value
     */
    protected function createValue()
    {
        $preparedValue = $this->opt['value'] ?? '';

        // page fields might need to be JSON encoded depending on usetitles config
        if (
            $this->column->getType() instanceof Page
            && $this->column->getType()->getConfig()['usetitles']
        ) {
            $preparedValue = json_encode([$preparedValue, null], JSON_THROW_ON_ERROR);
        }

        $value = new Value($this->column, $preparedValue);

        // no way to pass $israw parameter to constructor, so re-set the Lookup value
        if ($this->column->getType() instanceof Lookup) {
            $value->setValue($preparedValue, true);
        }

        return $value;
    }

    /**
     * Create the input field
     *
     * @param Value $field
     * @param String $name field's name
     * @return string
     */
    protected function makeField(Value $field, $name)
    {
        $trans = hsc($field->getColumn()->getTranslatedLabel());
        $hint = hsc($field->getColumn()->getTranslatedHint());
        $class = $hint ? 'hashint' : '';
        $lclass = $this->error ? 'bureaucracy_error' : '';
        $colname = $field->getColumn()->getFullQualifiedLabel();
        $required = empty($this->opt['optional']) ? ' <sup>*</sup>' : '';

        $id = uniqid('struct__', true);
        $input = $field->getValueEditor($name, $id);

        $html = '<div class="field">';
        $html .= "<label class=\"$lclass\" data-column=\"$colname\" for=\"$id\">";
        $html .= "<span class=\"label $class\" title=\"$hint\">$trans$required</span>";
        $html .= '</label>';
        $html .= "<span class=\"input\">$input</span>";
        $html .= '</div>';

        return $html;
    }

    /**
     * Tries to find the correct column and schema
     *
     * @param string $colname
     * @return Column
     * @throws StructException
     */
    protected function findColumn($colname)
    {
        [$table, $label] = explode('.', $colname, 2);
        if (!$table || !$label) {
            throw new StructException('Field \'%s\' not given in schema.field form', $colname);
        }
        $schema = new Schema($table);
        return $schema->findColumn($label);
    }

    /**
     * This ensures all language strings are still working
     *
     * @return string always 'bureaucracy'
     */
    public function getPluginName()
    {
        return 'bureaucracy';
    }
}
