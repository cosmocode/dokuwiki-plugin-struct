<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\File\PageResolver;
use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\Utf8\PhpString;

/**
 * Class Page
 *
 * Represents a single page in the wiki. Will be linked in output.
 *
 * @package dokuwiki\plugin\struct\types
 */
class Page extends AbstractMultiBaseType
{
    protected $config = [
        'usetitles' => false,
        'autocomplete' => [
            'mininput' => 2,
            'maxresult' => 5,
            'filter' => '',
        ]
    ];

    /**
     * Output the stored data
     *
     * @param string $value the value stored in the database - JSON when titles are used
     * @param \Doku_Renderer $R the renderer currently used to render the data
     * @param string $mode The mode the output is rendered in (eg. XHTML)
     * @return bool true if $mode could be satisfied
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        if ($this->config['usetitles']) {
            [$id, $title] = \helper_plugin_struct::decodeJson($value);
        } else {
            $id = $value;
            $title = $id; // cannot be empty or internallink() might hijack %pageid% and use headings
        }

        if (!$id) return true;

        $R->internallink(":$id", $title);
        return true;
    }

    /**
     * Cleans the link
     *
     * @param string $rawvalue
     * @return string
     */
    public function validate($rawvalue)
    {
        [$page, $fragment] = array_pad(explode('#', $rawvalue, 2), 2, '');
        return cleanID($page) . (strlen(cleanID($fragment)) > 0 ? '#' . cleanID($fragment) : '');
    }

    /**
     * Autocompletion support for pages
     *
     * @return array
     */
    public function handleAjax()
    {
        global $INPUT;

        // check minimum length
        $lookup = trim($INPUT->str('search'));
        if (PhpString::strlen($lookup) < $this->config['autocomplete']['mininput']) return [];

        // results wanted?
        $max = $this->config['autocomplete']['maxresult'];
        if ($max <= 0) return [];

        $data = ft_pageLookup($lookup, true, $this->config['usetitles']);
        if ($data === []) return [];

        $filter = $this->config['autocomplete']['filter'];

        // this basically duplicates what we do in ajax_qsearch() but with a filter
        $result = [];
        $counter = 0;
        foreach ($data as $id => $title) {
            if (!empty($filter) && !$this->filterMatch($id, $filter)) {
                continue;
            }
            if ($this->config['usetitles']) {
                $name = $title . ' (' . $id . ')';
            } else {
                $ns = getNS($id);
                if ($ns) {
                    $name = noNS($id) . ' (' . $ns . ')';
                } else {
                    $name = $id;
                }
            }

            $result[] = [
                'label' => $name,
                'value' => $id
            ];

            $counter++;
            if ($counter > $max) break;
        }

        return $result;
    }

    /**
     * When using titles, we need ot join the titles table
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     */
    public function select(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        if (!$this->config['usetitles']) {
            parent::select($QB, $tablealias, $colname, $alias);
            return;
        }
        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.$colname = $rightalias.pid");
        $QB->addSelectStatement("STRUCT_JSON($tablealias.$colname, $rightalias.title)", $alias);
    }

    /**
     * When using titles, sort by them first
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        if (!$this->config['usetitles']) {
            parent::sort($QB, $tablealias, $colname, $order);
            return;
        }

        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.$colname = $rightalias.pid");
        $QB->addOrderBy("$rightalias.title $order");
        $QB->addOrderBy("$tablealias.$colname $order");
    }

    /**
     * Return the pageid only
     *
     * @param string $value
     * @return string
     */
    public function rawValue($value)
    {
        if ($this->config['usetitles']) {
            [$value] = \helper_plugin_struct::decodeJson($value);
        }
        return $value;
    }

    /**
     * Return the title only
     *
     * @param string $value
     * @return string
     */
    public function displayValue($value)
    {
        if ($this->config['usetitles']) {
            [$pageid, $value] = \helper_plugin_struct::decodeJson($value);
            if (blank($value)) {
                $value = $pageid;
            }
        }
        return $value;
    }

    /**
     * When using titles, we need to compare against the title table, too
     *
     * @param QueryBuilderWhere $add
     * @param string $tablealias
     * @param string $colname
     * @param string $comp
     * @param string $value
     * @param string $op
     */
    public function filter(QueryBuilderWhere $add, $tablealias, $colname, $comp, $value, $op)
    {
        if (!$this->config['usetitles']) {
            parent::filter($add, $tablealias, $colname, $comp, $value, $op);
            return;
        }

        $QB = $add->getQB();
        $rightalias = $QB->generateTableAlias();
        $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.$colname = $rightalias.pid");

        // compare against page and title
        $sub = $add->where($op);
        $pl = $QB->addValue($value);
        $sub->whereOr("$tablealias.$colname $comp $pl");
        $pl = $QB->addValue($value);
        $sub->whereOr("$rightalias.title $comp $pl");
    }

    /**
     * Check if the given id matches a configured filter pattern
     *
     * @param string $id
     * @param string $filter
     * @return bool
     */
    public function filterMatch($id, $filter)
    {
        // absolute namespace?
        if (PhpString::substr($filter, 0, 1) === ':') {
            $filter = '^' . $filter;
        }

        try {
            $check = preg_match('/' . $filter . '/', ':' . $id, $matches);
        } catch (\Exception $e) {
            throw new StructException("Error processing regular expression '$filter'");
        }
        return (bool)$check;
    }

    /**
     * Merge the current config with the base config of the type.
     *
     * In contrast to parent, this method does not throw away unknown keys.
     * Required to migrate deprecated / obsolete options, no longer part of type config.
     *
     * @param array $current Current configuration
     * @param array $config Base Type configuration
     */
    protected function mergeConfig($current, &$config)
    {
        foreach ($current as $key => $value) {
            if (isset($config[$key]) && is_array($config[$key])) {
                $this->mergeConfig($value, $config[$key]);
            } else {
                $config[$key] = $value;
            }
        }

        // migrate autocomplete options 'namespace' and 'postfix' to 'filter'
        if (empty($config['autocomplete']['filter'])) {
            if (!empty($config['autocomplete']['namespace'])) {
                $config['autocomplete']['filter'] = $config['autocomplete']['namespace'];
                unset($config['autocomplete']['namespace']);
            }
            if (!empty($config['autocomplete']['postfix'])) {
                $config['autocomplete']['filter'] .= '.+?' . $config['autocomplete']['postfix'] . '$';
                unset($config['autocomplete']['postfix']);
            }
        }
    }
}
