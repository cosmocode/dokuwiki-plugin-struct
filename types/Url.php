<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\ValidationException;

class Url extends Text
{

    protected $config = array(
        'autoscheme' => 'https',
        'prefix' => '',
        'postfix' => '',
    );

    /**
     * The final string should be an URL
     *
     * @param string $rawvalue
     * @return int|string|void
     */
    public function validate($rawvalue)
    {
        $rawvalue = parent::validate($rawvalue);

        $url = $this->buildURL($rawvalue);

        $schemes = getSchemes();
        $regex = '^(' . join('|', $schemes) . '):\/\/.+';
        if (!preg_match("/$regex/i", $url)) {
            throw new ValidationException('Url invalid', $url);
        }

        return $rawvalue;
    }

    /**
     * @param string $value
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        $url = $this->buildURL($value);
        $R->externallink($url);
        return true;
    }

    /**
     * Creates the full URL and applies the autoscheme if needed
     *
     * @param string $value
     * @return string
     */
    protected function buildURL($value)
    {
        $url = $this->config['prefix'] . trim($value) . $this->config['postfix'];

        if (!preg_match('/\w+:\/\//', $url)) {
            $url = $this->config['autoscheme'] . '://' . $url;
        }

        return $url;
    }
}
