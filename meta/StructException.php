<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class StructException
 *
 * A translatable exception
 *
 * @package dokuwiki\plugin\struct\meta
 */
class StructException extends \RuntimeException
{
    protected $trans_prefix = 'Exception ';

    /**
     * StructException constructor.
     *
     * @param string $message
     * @param ...string $vars
     */
    public function __construct($message)
    {
        /** @var \helper_plugin_struct $plugin */
        $plugin = plugin_load('helper', 'struct');
        $trans = $plugin->getLang($this->trans_prefix . $message);
        if (!$trans) $trans = $message;

        $args = func_get_args();
        array_shift($args);

        $trans = vsprintf($trans, $args);
        $trans .= $this->getVersionPostfix();

        parent::__construct($trans, -1, null);
    }

    /**
     * Get the plugin version to add as postfix to the exception message
     * @return string
     */
    protected function getVersionPostfix()
    {
        /** @var \helper_plugin_struct $plugin */
        $plugin = plugin_load('helper', 'struct');
        $info = $plugin->getInfo();
        return ' [struct ' . $info['date'] . ']';
    }
}
