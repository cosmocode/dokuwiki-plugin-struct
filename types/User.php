<?php

namespace dokuwiki\plugin\struct\types;

use dokuwiki\Extension\AuthPlugin;
use dokuwiki\plugin\struct\meta\QueryBuilder;
use dokuwiki\plugin\struct\meta\QueryBuilderWhere;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\ValidationException;
use dokuwiki\Utf8\PhpString;

class User extends AbstractMultiBaseType
{
    protected $config = [
        'existingonly' => true,
        'autocomplete' => [
            'fullname' => true,
            'mininput' => 2,
            'maxresult' => 5
        ]
    ];

    /**
     * @param string $rawvalue the user to validate
     * @return int|string
     */
    public function validate($rawvalue)
    {
        $rawvalue = parent::validate($rawvalue);

        if ($this->config['existingonly']) {
            /** @var AuthPlugin $auth */
            global $auth;
            $info = $auth->getUserData($rawvalue, false);
            if ($info === false) throw new ValidationException('User not found', $rawvalue);
        }

        return $rawvalue;
    }

    /**
     * @param string $value the user to display
     * @param \Doku_Renderer $R
     * @param string $mode
     * @return bool
     */
    public function renderValue($value, \Doku_Renderer $R, $mode)
    {
        if ($mode == 'xhtml') {
            $name = userlink($value);
            $R->doc .= $name;
        } else {
            $name = userlink($value, true);
            $R->cdata($name);
        }
        return true;
    }

    /**
     * Autocompletion for user names
     *
     * @return array
     * @todo should we have any security mechanism? Currently everybody can look up users
     */
    public function handleAjax()
    {
        /** @var AuthPlugin $auth */
        global $auth;
        global $INPUT;

        if (!$auth->canDo('getUsers')) {
            return [];
        }

        // check minimum length
        $lookup = trim($INPUT->str('search'));
        if (PhpString::strlen($lookup) < $this->config['autocomplete']['mininput']) return [];

        // results wanted?
        $max = $this->config['autocomplete']['maxresult'];
        if ($max <= 0) return [];

        // find users by login, fill up with names if wanted
        $logins = (array)$auth->retrieveUsers(0, $max, ['user' => $lookup]);
        if ((count($logins) < $max) && $this->config['autocomplete']['fullname']) {
            $logins = array_merge($logins, (array)$auth->retrieveUsers(0, $max, ['name' => $lookup]));
        }

        // reformat result for jQuery UI Autocomplete
        $users = [];
        foreach ($logins as $login => $info) {
            $users[] = [
                'label' => $info['name'] . ' [' . $login . ']',
                'value' => $login
            ];
        }

        return $users;
    }

    /**
     * When handling `%lasteditor%` get the data from the `titles` table instead the `data_` table.
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $alias
     */
    public function selectCol(QueryBuilder $QB, $tablealias, $colname, $alias)
    {
        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\UserColumn')) {
            $rightalias = $QB->generateTableAlias();
            $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
            $QB->addSelectStatement("$rightalias.lasteditor", $alias);
            return;
        }

        parent::selectCol($QB, $tablealias, $colname, $alias);
    }

    /**
     * When sorting `%lasteditor%`, then sort the data from the `titles` table instead the `data_` table.
     *
     * @param QueryBuilder $QB
     * @param string $tablealias
     * @param string $colname
     * @param string $order
     */
    public function sort(QueryBuilder $QB, $tablealias, $colname, $order)
    {
        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\UserColumn')) {
            $rightalias = $QB->generateTableAlias();
            $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
            $QB->addOrderBy("$rightalias.lasteditor $order");
            return;
        }

        $QB->addOrderBy("$tablealias.$colname $order");
    }

    /**
     * @param QueryBuilderWhere &$add The WHERE or ON clause to contain the conditional this comparator will be used in
     * @param string $tablealias The table the values are stored in
     * @param string $colname The column name on the above table
     * @param string &$op the logical operator this filter should use
     * @return string The SQL expression to be used on one side of the comparison operator
     */
    protected function getSqlCompareValue(QueryBuilderWhere &$add, $tablealias, $colname, &$op)
    {
        if (is_a($this->context, 'dokuwiki\plugin\struct\meta\UserColumn')) {
            $QB = $add->getQB();
            $rightalias = $QB->generateTableAlias();
            $QB->addLeftJoin($tablealias, 'titles', $rightalias, "$tablealias.pid = $rightalias.pid");
            return "$rightalias.lasteditor";
        }

        return parent::getSqlCompareValue($add, $tablealias, $colname, $comp, $value, $op);
    }
}
