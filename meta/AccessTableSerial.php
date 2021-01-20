<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class AccessTableSerial
 *
 * Load and save serial data
 *
 * @package dokuwiki\plugin\struct\meta
 */
class AccessTableSerial extends AccessTableGlobal
{
    public function __construct($table, $pid, $ts = 0, $rid = 0)
    {
        if ($ts) {
            throw new StructException('Requesting wrong data type! Serial data has no timestamp');
        }
        parent::__construct($table, $pid, $ts, $rid);
    }
}
