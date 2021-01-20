<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;

/**
 * Class Page
 *
 * Represents a single page in the wiki. Will be linked in output.
 *
 * @package dokuwiki\plugin\struct\types
 */
class Page extends AbstractMultiBaseType
{

    protected $config = array(
        'usetitles' => false,
        'autocomplete' => array(
            'mininput' => 2,
            'maxresult' => 5,
            'namespace' => '',
            'postfix' => '',
        ),
    );

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
            list($id, $title) = \helper_plugin_struct::decodeJson($value);
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
        list($page, $fragment) = explode('#', $rawvalue, 2);
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
        if (utf8_strlen($lookup) < $this->config['autocomplete']['mininput']) return array();

        // results wanted?
        $max = $this->config['autocomplete']['maxresult'];
        if ($max <= 0) return array();

        // lookup with namespace and postfix applied
        $namespace = $this->config['autocomplete']['namespace'];
        if ($namespace) {
            // namespace may be relative, resolve in current context
            $namespace .= ':foo'; // resolve expects pageID
            resolve_pageid($INPUT->str('ns'), $namespace, $exists);
            $namespace = getNS($namespace);
        }
        $postfix = $this->config['autocomplete']['postfix'];
        if ($namespace) $lookup .= ' @' . $namespace;

        $data = ft_pageLookup($lookup, true, $this->config['usetitles']);
        if (!count($data)) return array();

        // this basically duplicates what we do in ajax_qsearch()
        $result = array();
        $counter = 0;
        foreach ($data as $id => $title) {
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

            // check suffix
            if ($postfix && substr($id, -1 * strlen($postfix)) != $postfix) {
                continue; // page does not end in postfix, don't suggest it
            }

            $result[] = array(
                'label' => $name,
                'value' => $id
            );

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
            list($value) = \helper_plugin_struct::decodeJson($value);
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
            list($pageid, $value) = \helper_plugin_struct::decodeJson($value);
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
}
