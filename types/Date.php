<?php
namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\ValidationException;

class Date extends AbstractBaseType {

    protected $config = array(
        'format' => 'Y/m/d',
        'prefilltoday' => false
    );

    /**
     * Output the stored data
     *
     * @param string|int $value the value stored in the database
     * @param \Doku_Renderer $R the renderer currently used to render the data
     * @param string $mode The mode the output is rendered in (eg. XHTML)
     * @return bool true if $mode could be satisfied
     */
    public function renderValue($value, \Doku_Renderer $R, $mode) {
        $date = date_create($value);
        if($date !== false) {
            $out = date_format($date, $this->config['format']);
        } else {
            $out = '';
        }

        $R->cdata($out);
        return true;
    }

    /**
     * Return the editor to edit a single value
     *
     * @param string $name     the form name where this has to be stored
     * @param string $rawvalue the current value
     * @param string $htmlID
     *
     * @return string html
     */
    public function valueEditor($name, $rawvalue, $htmlID) {
        if($this->config['prefilltoday'] && !$rawvalue) {
            $rawvalue = date('Y-m-d');
        }

        $params = array(
            'name' => $name,
            'value' => $rawvalue,
            'class' => 'struct_date',
            'id' => $htmlID,
        );
        $attributes = buildAttributes($params, true);
        return "<input $attributes />";
    }

    /**
     * Validate a single value
     *
     * This function needs to throw a validation exception when validation fails.
     * The exception message will be prefixed by the appropriate field on output
     *
     * @param string|int $rawvalue
     * @return int|string
     * @throws ValidationException
     */
    public function validate($rawvalue) {
        $rawvalue = parent::validate($rawvalue);
        list($rawvalue) = explode(' ', $rawvalue, 2); // strip off time if there is any

        list($year, $month, $day) = explode('-', $rawvalue, 3);
        if(!checkdate((int) $month, (int) $day, (int) $year)) {
            throw new ValidationException('invalid date format');
        }
        return sprintf('%d-%02d-%02d', $year, $month, $day);
    }

}
