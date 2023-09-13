<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\ValidationException;

class Url extends Text
{
    protected $config = [
        'autoscheme' => 'https',
        'prefix' => '',
        'postfix' => '',
        'fixedtitle' => '',
        'autoshorten' => true
    ];

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
        $regex = '^(' . implode('|', $schemes) . '):\/\/.+';
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
        $title = $this->generateTitle($url);
        $R->externallink($url, $title);
        return true;
    }

    /**
     * Make a label for the link
     *
     * @param $url
     * @return string
     */
    protected function generateTitle($url)
    {
        if ($this->config['fixedtitle']) return $this->config['fixedtitle'];
        if (!$this->config['autoshorten']) return $url;

        $parsed = parse_url($url);

        $title = $parsed['host'];
        $title = preg_replace('/^www\./i', '', $title);
        if (isset($parsed['path']) && $parsed['path'] === '/') {
            unset($parsed['path']);
        }
        if (
            isset($parsed['path']) ||
            isset($parsed['query']) ||
            isset($parsed['fragment'])
        ) {
            $title .= '/…';
        }
        return $title;
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
