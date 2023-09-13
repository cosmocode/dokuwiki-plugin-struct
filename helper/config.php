<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\struct\meta\StructException;
use dokuwiki\plugin\struct\meta\Search;

/**
 * DokuWiki Plugin struct (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

class helper_plugin_struct_config extends Plugin
{
    /**
     * @param string $val
     *
     * @return array
     */
    public function parseSort($val)
    {
        if (substr($val, 0, 1) == '^') {
            return [substr($val, 1), false];
        }
        return [$val, true];
    }

    /**
     * @param $logic
     * @param $val
     *
     * @return array|bool
     */
    public function parseFilterLine($logic, $val)
    {
        $flt = $this->parseFilter($val);
        if ($flt) {
            $flt[] = $logic;
            return $flt;
        }
        return false;
    }

    /**
     * Parse a filter
     *
     * @param string $val
     *
     * @return array ($col, $comp, $value)
     * @throws StructException
     */
    protected function parseFilter($val)
    {

        $comps = Search::$COMPARATORS;
        $comps[] = '*~';
        array_unshift($comps, '<>');
        $comps = array_map('preg_quote_cb', $comps);
        $comps = implode('|', $comps);

        if (!preg_match('/^(.*?)(' . $comps . ')(.*)$/', $val, $match)) {
            throw new StructException('Invalid search filter %s', hsc($val));
        }
        array_shift($match); // we don't need the zeroth match
        $match[0] = trim($match[0]);
        $match[2] = trim($match[2]);
        return $match;
    }
}

// vim:ts=4:sw=4:et:
