<?php

namespace dokuwiki\plugin\struct\test\mock;

class helper_plugin_struct_db extends \helper_plugin_struct_db {

    public function IS_PUBLISHER() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return 0;
    }
}
