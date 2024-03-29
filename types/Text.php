<?php

namespace dokuwiki\plugin\struct\types;

class Text extends AbstractMultiBaseType
{
    use TraitFilterPrefix;

    protected $config = [
        'prefix' => '',
        'postfix' => ''
    ];

    /**
     * Output the stored data
     *
     * @param string|int $value the value stored in the database
     * @param \Doku_Renderer $R the renderer currently used to render the data
     * @param string $mode The mode the output is rendered in (eg. XHTML)
     * @return bool true if $mode could be satisfied
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        $R->cdata($this->config['prefix'] . $value . $this->config['postfix']);
        return true;
    }
}
