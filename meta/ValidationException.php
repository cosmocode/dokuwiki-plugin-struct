<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class ValidationException
 *
 * Used to signal validation exceptions
 *
 * @package dokuwiki\plugin\struct\meta
 */
class ValidationException extends StructException
{
    protected $trans_prefix = 'Validation Exception ';

    /**
     * No version postfix on validation errors
     */
    protected function getVersionPostfix()
    {
        return '';
    }
}
