<?php

/**
 * CSV export of tabular data generated in Aggregations
 *
 * Note: this is different from meta\CSVExporter
 *
 * @link https://tools.ietf.org/html/rfc4180
 * @link http://csvlint.io/
 */
class renderer_plugin_struct_csv extends Doku_Renderer
{

    protected $first = false;

    /**
     * Determine if out put is wanted right now
     *
     * @return bool
     */
    protected function doOutput()
    {
        global $INPUT;

        if (
            !isset($this->info['struct_table_hash']) or
            $this->info['struct_table_hash'] != $INPUT->str('hash')
        ) {
            return false;
        }

        if (!empty($this->info['struct_table_meta'])) {
            return false;
        }

        return true;
    }

    /**
     * Our own format
     *
     * @return string
     */
    public function getFormat()
    {
        return 'struct_csv';
    }

    /**
     * Set proper headers
     */
    public function document_start() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        global $ID;
        $filename = noNS($ID) . '.csv';
        $headers = array(
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '";'
        );
        p_set_metadata($ID, array('format' => array('struct_csv' => $headers)));
        // don't cache
        $this->nocache();
    }

    /**
     * Opening a table row prevents the separator for the first following cell
     */
    public function tablerow_open() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (!$this->doOutput()) return;
        $this->first = true;
    }

    /**
     * Output the delimiter (unless it's the first cell of this row) and the text wrapper
     *
     * @param int $colspan ignored
     * @param null $align ignored
     * @param int $rowspan ignored
     */
    public function tablecell_open($colspan = 1, $align = null, $rowspan = 1) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (!$this->doOutput()) return;
        if (!$this->first) {
            $this->doc .= ",";
        }
        $this->first = false;

        $this->doc .= '"';
    }

    /**
     * Close the text wrapper
     */
    public function tablecell_close() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (!$this->doOutput()) return;
        $this->doc .= '"';
    }

    /**
     * Alias for tablecell_open
     *
     * @param int $colspan ignored
     * @param null $align ignored
     * @param int $rowspan ignored
     */
    public function tableheader_open($colspan = 1, $align = null, $rowspan = 1) // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $this->tablecell_open($colspan, $align, $rowspan);
    }

    /**
     * Alias for tablecell_close
     */
    public function tableheader_close() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        $this->tablecell_close();
    }

    /**
     * Add CRLF newline at the end of one line
     */
    public function tablerow_close() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (!$this->doOutput()) return;
        $this->doc .= "\r\n";
    }

    /**
     * Outputs cell content
     *
     * @param string $text
     */
    public function cdata($text)
    {
        if (!$this->doOutput()) return;
        if ($text === '') return;

        $this->doc .= str_replace('"', '""', $text);
    }


    #region overrides using cdata for output

    public function internallink($link, $title = null)
    {
        if (is_null($title) or is_array($title) or $title == '') {
            $title = $this->_simpleTitle($link);
        }
        $this->cdata($title);
    }

    public function externallink($link, $title = null)
    {
        if (is_null($title) or is_array($title) or $title == '') {
            $title = $link;
        }
        $this->cdata($title);
    }

    public function emaillink($address, $name = null)
    {
        $this->cdata($address);
    }

    public function plugin($name, $args, $state = '', $match = '')
    {
        if (substr($name, 0, 7) == 'struct_') {
            parent::plugin($name, $args, $state, $match);
        } else {
            $this->cdata($match);
        }
    }

    public function acronym($acronym)
    {
        $this->cdata($acronym);
    }

    public function code($text, $lang = null, $file = null)
    {
        $this->cdata($text);
    }

    public function header($text, $level, $pos)
    {
        $this->cdata($text);
    }

    public function linebreak()
    {
        $this->cdata("\r\n");
    }

    public function unformatted($text)
    {
        $this->cdata($text);
    }

    public function php($text)
    {
        $this->cdata($text);
    }

    public function phpblock($text)
    {
        $this->cdata($text);
    }

    public function html($text)
    {
        $this->cdata($text);
    }

    public function htmlblock($text)
    {
        $this->cdata($text);
    }

    public function preformatted($text)
    {
        $this->cdata($text);
    }

    public function file($text, $lang = null, $file = null)
    {
        $this->cdata($text);
    }

    public function smiley($smiley)
    {
        $this->cdata($smiley);
    }

    public function entity($entity)
    {
        $this->cdata($entity);
    }

    public function multiplyentity($x, $y)
    {
        $this->cdata($x . 'x' . $y);
    }

    public function locallink($hash, $name = null)
    {
        if (is_null($name) or is_array($name) or $name == '') {
            $name = $hash;
        }
        $this->cdata($name);
    }

    public function interwikilink($link, $title, $wikiName, $wikiUri)
    {
        if (is_array($title) or $title == '') {
            $title = $wikiName . '>' . $link;
        }
        $this->cdata($title);
    }

    public function filelink($link, $title = null)
    {
        if (is_null($title) or is_array($title) or $title == '') {
            $title = $link;
        }
        $this->cdata($title);
    }

    public function windowssharelink($link, $title = null)
    {
        if (is_null($title) or is_array($title) or $title == '') {
            $title = $link;
        }
        $this->cdata($title);
    }

    public function internalmedia(
        $src,
        $title = null,
        $align = null,
        $width = null,
        $height = null,
        $cache = null,
        $linking = null
    ) {
        $this->cdata($src);
    }

    public function externalmedia(
        $src,
        $title = null,
        $align = null,
        $width = null,
        $height = null,
        $cache = null,
        $linking = null
    ) {
        $this->cdata($src);
    }

    public function internalmedialink(
        $src,
        $title = null,
        $align = null,
        $width = null,
        $height = null,
        $cache = null
    ) {
        $this->cdata($src);
    }

    public function externalmedialink(
        $src,
        $title = null,
        $align = null,
        $width = null,
        $height = null,
        $cache = null
    ) {
        $this->cdata($src);
    }

    #endregion
}
