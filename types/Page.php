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
    public function selectCol(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        if (!$this->config['usetitles']) {
            parent::selectCol($QB, $tablealias, $colname, $alias);
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
        $QB->addOrderBy("$rightalias.title COLLATE NOCASE $order");
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
     * When using titles, we need to compare against the title table, too.
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string|null $oldalias A previous alias used for this table (only used by Page)
     * @param string $colname The column name on the above table
     * @param string &$op the logical operator this filter should use
     * @return array The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias, $oldalias, $colname, &$op)
    {
        if (!$this->config['usetitles']) {
            return parent::getSqlCompareValue($add, $tablealias, $oldalias, $colname, $op);
        }
        if (is_null($oldalias)) {
            throw new StructException('Table name for Page column not specified.');
        }
        return ["$oldalias.$colname", "$tablealias.title"];
    }

    /**
     * This function provides arguments for an additional JOIN operation needed
     * to perform a comparison (e.g., for a JOIN or FILTER), or null if no
     * additional JOIN is needed.
     *
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string $colname The column name on the above table
     * @return null|array [$leftalias, $righttable, $rightalias, $onclause]
     */
    protected function getAdditionalJoinForComparison(QueryBuilderWhere &$add, $tablealias, $colname)
    {
        if (!$this->config['usetitles']) {
            return parent::getAdditionalJoinForComparison($add, $tablealias, $colname);
        }
        $QB = $add->getQB();
        $rightalias = $QB->generateTableAlias();
        return [$tablealias, 'titles', $rightalias, "$tablealias.$colname = $rightalias.pid"];
    }

    /**
     * Returns a SQL expression on which to join two tables, when the
     * column of the right table being joined on is of this data
     * type. This should only be called if joining on this data type
     * requires introducing an additional join (i.e., if
     * getAdditionalJoinForComparison returns an array).
     *
     * @param QueryBuilder $QB
     * @param string $lhs Left hand side of the ON clause (for left table)
     * @param string $rhs Right hand side of the ON clause (for right table)
     * @param string $additional_join_condition The ON clause of the additional join
     * @return string SQL expression to be returned by joinCondition
     */
    protected function joinConditionIfAdditionalJoin($lhs, &$rhs, $additional_join_condition)
    {
        [$rhs_id, $rhs] = $rhs;
        return $additional_join_condition . ' OR ' . $this->equalityComparison($lhs, $rhs_id);
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
