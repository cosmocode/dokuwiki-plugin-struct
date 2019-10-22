<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\Search;
use dokuwiki\plugin\struct\meta\ValidationException;

/**
 * Class Increment
 *
 * An autoincrementing field
 *
 * @package dokuwiki\plugin\struct\types
 */
class Increment extends Decimal
{

    protected $config = [];

    /**
     * Return the editor to edit a single value
     *
     * @param string $name the form name where this has to be stored
     * @param string $rawvalue the current value
     * @param string $htmlID a unique id to be referenced by the label
     *
     * @return string html
     */
    public function valueEditor($name, $rawvalue, $htmlID)
    {
        $class = 'struct_' . strtolower($this->getClass());

        if (!$rawvalue) {
            $rawvalue = $this->getMaxValue() + 1;
        }

        $params = [
            'name' => $name,
            'value' => $rawvalue,
            'class' => $class,
            'id' => $htmlID,
            'readonly' => true
        ];
        $attributes = buildAttributes($params, true);
        return "<input $attributes>";
    }

    /**
     * Discard most parts of Decimal validation because
     * this type does not use the parent class config
     *
     * @param int|string $rawvalue
     * @return int|string
     * @throws ValidationException
     */
    public function validate($rawvalue)
    {
        $rawvalue = rtrim($rawvalue);

        if ((string)$rawvalue != (string)floatval($rawvalue)) {
            throw new ValidationException('Decimal needed');
        }

        return $rawvalue;
    }

    /**
     * Get the max value found, otherwise 0 to increment upon
     *
     * @return int
     **/
    public function getMaxValue()
    {
        $search = new Search();
        $search->addSchema($this->context->getTable());
        $search->addColumn($this->getLabel());
        $search->addSort($this->getLabel(), false);
        $search->setLimit(1);
        $results = $search->execute();

        $value = $results ? ($results[0][0]->getValue()) : 0;

        return $value;
    }
}
