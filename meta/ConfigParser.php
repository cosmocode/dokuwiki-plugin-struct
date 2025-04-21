<?php

namespace dokuwiki\plugin\struct\meta;

use dokuwiki\Extension\Event;

/**
 * Class ConfigParser
 *
 * Utilities to parse the configuration syntax into an array
 *
 * @package dokuwiki\plugin\struct\meta
 */
class ConfigParser
{
    protected $config = [
        'limit' => 0,
        'dynfilters' => false,
        'summarize' => false,
        'rownumbers' => false,
        'sepbyheaders' => false,
        'target' => '',
        'align' => [],
        'headers' => [],
        'cols' => [],
        'widths' => [],
        'filter' => [],
        'schemas' => [],
        'sort' => [],
        'csv' => true,
        'nesting' => 0,
        'index' => 0,
        'classes' => []
    ];

    /**
     * Parser constructor.
     *
     * parses the given configuration lines
     *
     * @param $lines
     */
    public function __construct($lines)
    {
        /** @var \helper_plugin_struct_config $helper */
        $helper = plugin_load('helper', 'struct_config');
        // parse info
        foreach ($lines as $line) {
            [$key, $val] = $this->splitLine($line);
            if (!$key) continue;

            $logic = 'OR';
            // handle line commands (we allow various aliases here)
            switch ($key) {
                case 'from':
                case 'schema':
                case 'tables':
                    $this->config['schemas'] = array_merge($this->config['schemas'], $this->parseSchema($val));
                    break;
                case 'select':
                case 'cols':
                case 'field':
                case 'col':
                    $this->config['cols'] = $this->parseValues($val);
                    break;
                case 'sepbyheaders':
                    $this->config['sepbyheaders'] = (bool)$val;
                    break;
                case 'head':
                case 'header':
                case 'headers':
                    $this->config['headers'] = $this->parseValues($val);
                    break;
                case 'align':
                    $this->config['align'] = $this->parseAlignments($val);
                    break;
                case 'width':
                case 'widths':
                    $this->config['widths'] = $this->parseWidths($val);
                    break;
                case 'min':
                    $this->config['min'] = abs((int)$val);
                    break;
                case 'limit':
                case 'max':
                    $this->config['limit'] = abs((int)$val);
                    break;
                case 'order':
                case 'sort':
                    $sorts = $this->parseValues($val);
                    $sorts = array_map([$helper, 'parseSort'], $sorts);
                    $this->config['sort'] = array_merge($this->config['sort'], $sorts);
                    break;
                case 'where':
                case 'filter':
                case 'filterand': // phpcs:ignore PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
                    /** @noinspection PhpMissingBreakStatementInspection */
                case 'and': // phpcs:ignore PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
                    $logic = 'AND';
                case 'filteror':
                case 'or':
                    $flt = $helper->parseFilterLine($logic, $val);
                    if ($flt) {
                        $this->config['filter'][] = $flt;
                    }
                    break;
                case 'dynfilters':
                    $this->config['dynfilters'] = (bool)$val;
                    break;
                case 'rownumbers':
                    $this->config['rownumbers'] = (bool)$val;
                    break;
                case 'summarize':
                    $this->config['summarize'] = (bool)$val;
                    break;
                case 'csv':
                    $this->config['csv'] = (bool)$val;
                    break;
                case 'target':
                case 'page':
                    $this->config['target'] = cleanID($val);
                    break;
                case 'nesting':
                case 'nest':
                    $this->config['nesting'] = (int) $val;
                    break;
                case 'index':
                    $this->config['index'] = (int) $val;
                    break;
                case 'class':
                case 'classes':
                    $this->config['classes'] = $this->parseClasses($val);
                    break;
                default:
                    $data = ['config' => &$this->config, 'key' => $key, 'val' => $val];
                    $ev = new Event('PLUGIN_STRUCT_CONFIGPARSER_UNKNOWNKEY', $data);
                    if ($ev->advise_before()) {
                        throw new StructException("unknown option '%s'", hsc($key));
                    }
                    $ev->advise_after();
            }
        }

        // fill up headers - a NULL signifies that the column label is wanted
        $this->config['headers'] = (array)$this->config['headers'];
        $cnth = count($this->config['headers']);
        $cntf = count($this->config['cols']);
        for ($i = $cnth; $i < $cntf; $i++) {
            $this->config['headers'][] = null;
        }
        // fill up alignments
        $cnta = count($this->config['align']);
        for ($i = $cnta; $i < $cntf; $i++) {
            $this->config['align'][] = null;
        }
    }

    /**
     * Get the parsed configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Splits the given line into key and value
     *
     * @param $line
     * @return array returns ['',''] if the line is empty
     */
    protected function splitLine($line)
    {
        // ignore comments
        $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
        $line = str_replace('\\#', '#', $line);
        $line = trim($line);
        if (empty($line)) return ['', ''];

        $line = preg_split('/\s*:\s*/', $line, 2);
        $line[0] = strtolower($line[0]);
        if (!isset($line[1])) $line[1] = '';

        return $line;
    }

    /**
     * parses schema config and aliases
     *
     * @param $val
     * @return array
     */
    protected function parseSchema($val)
    {
        $schemas = [];
        $parts = explode(',', $val);
        foreach ($parts as $part) {
            [$table, $alias] = sexplode(' ', trim($part), 2, '');
            $table = trim($table);
            $alias = trim($alias);
            if (!$table) continue;

            $schemas[] = [$table, $alias];
        }
        return $schemas;
    }

    /**
     * Parse alignment data
     *
     * @param string $val
     * @return string[]
     */
    protected function parseAlignments($val)
    {
        $cols = explode(',', $val);
        $data = [];
        foreach ($cols as $col) {
            $col = trim(strtolower($col));
            if ($col[0] == 'c') {
                $align = 'center';
            } elseif ($col[0] == 'r') {
                $align = 'right';
            } elseif ($col[0] == 'l') {
                $align = 'left';
            } else {
                $align = null;
            }
            $data[] = $align;
        }

        return $data;
    }

    /**
     * Parse width data
     *
     * @param $val
     * @return array
     */
    protected function parseWidths($val)
    {
        $vals = explode(',', $val);
        $vals = array_map('trim', $vals);

        $len = count($vals);
        for ($i = 0; $i < $len; $i++) {
            $val = trim(strtolower($vals[$i]));

            if (preg_match('/^\d+.?(\d+)?(px|em|ex|ch|rem|%|in|cm|mm|q|pt|pc)$/', $val)) {
                // proper CSS unit?
                $vals[$i] = $val;
            } elseif (preg_match('/^\d+$/', $val)) {
                // decimal only?
                $vals[$i] = $val . 'px';
            } else {
                // invalid
                $vals[$i] = '';
            }
        }
        return $vals;
    }

    /**
     * Split values at the commas,
     * - Wrap with quotes to escape comma, quotes escaped by two quotes
     * - Within quotes spaces are stored.
     *
     * @param string $line
     * @return array
     */
    protected function parseValues($line)
    {
        $values = [];
        $inQuote = false;
        $escapedQuote = false;
        $value = '';
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            if ($line[$i] === '"') {
                if ($inQuote) {
                    if ($escapedQuote) {
                        $value .= '"';
                        $escapedQuote = false;
                        continue;
                    }
                    if (isset($line[$i + 1]) && $line[$i + 1] === '"') {
                        $escapedQuote = true;
                        continue;
                    }
                    $values[] = $value;
                    $inQuote = false;
                    $value = '';
                    continue;
                } else {
                    $inQuote = true;
                    $value = ''; //don't store stuff before the opening quote
                    continue;
                }
            } elseif ($line[$i] === ',') {
                if ($inQuote) {
                    $value .= ',';
                    continue;
                } else {
                    if (strlen($value) < 1) {
                        continue;
                    }
                    $values[] = trim($value);
                    $value = '';
                    continue;
                }
            }
            $value .= $line[$i];
        }
        if (strlen($value) > 0) {
            $values[] = trim($value);
        }
        return $values;
    }

    /**
     * Ensure custom classes are valid and don't clash
     *
     * @param string $line
     * @return string[]
     */
    protected function parseClasses($line)
    {
        $classes = $this->parseValues($line);
        $classes = array_map(function ($class) {
            $class = str_replace(' ', '_', $class);
            $class = preg_replace('/[^a-zA-Z0-9_]/', '', $class);
            return 'struct-custom-' . $class;
        }, $classes);
        return $classes;
    }
}
