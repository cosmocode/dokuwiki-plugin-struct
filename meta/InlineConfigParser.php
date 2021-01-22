<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class InlineConfigParser
 *
 * Wrapper to convert inline syntax to full before instantiating ConfigParser
 *
 * @package dokuwiki\plugin\struct\meta
 */
class InlineConfigParser extends ConfigParser
{

    /**
     * Parser constructor.
     *
     * parses the given inline configuration
     *
     * @param  string  $inline
     */
    public function __construct($inline)
    {
        /*
         * {{$schema.field}}
         * {{$pageid.schema.field}}
         * {{$... ? filter: ... and: ... or: ...}} or {{$... ? & ... | ...}}
         * TODO: {{$... ? sum}} or {{$... ? +}}
         * TODO: {{$... ? default: ...}} or {{$... ? ! ...}}
         * If no page ID or filter is supplied, filter: %pageid% = $ID$ is added.
         * Any component can be placed in double quotes (needed to allow space, dot or question mark in components).
         */

        // Start to build the main config array
        $lines     = array();  // Config lines to pass to full parser

        // Extract options
        $parts        = explode('?', $inline, 2);
        $n_parts      = count($parts);
        $components   = str_getcsv(trim($parts[0]), '.');
        $n_components = count($components);

        if ($n_parts == 2) {
            $filtering    = false;  // Whether to filter result to current page
            $parameters   = str_getcsv(trim($parts[1]), ' ');
            $n_parameters = count($parameters);

            for ($i = 0; $i < $n_parameters; $i++) {
                $p = trim($parameters[$i]);
                switch ($p) {
                    case '':
                        continue 2;
                        break;
                    case $p[-1] == ':' ? $p : '':
                        if (in_array($p, ['filter', 'where', 'filterand', 'and', 'filteror','or'])) {
                            $filtering = true;
                        }
                        $lines[] = $p . ' ' . trim($parameters[$i + 1]);
                        $i++;
                        break;
                    case '&':
                        $filtering = true;
                        $lines[] = 'filterand: ' . trim($parameters[$i + 1]);
                        $i++;
                        break;
                    case '|':
                        $filtering = true;
                        $lines[] = 'filteror: ' . trim($parameters[$i + 1]);
                        $i++;
                        break;
                    default:
                        break;
                }
            }
        }

        // Check whether a page was specified
        if (count($components) == 3) {
            // At least page, schema and field supplied
            $lines[] = 'schema: ' . trim($components[1]);
            $lines[] = 'field: ' . trim($components[2]);
            $lines[] = 'filter: %pageid% = ' . trim($components[0]);
        } elseif (count($components) == 2) {
            // At least schema and field supplied
            $lines[] = 'schema: ' . trim($components[0]);
            $lines[] = 'field: ' . trim($components[1]);
            if (! $filtering) {
                $lines[] = 'filter: %pageid% = $ID$';
            }
        }

        // Call original ConfigParser's constructor
        parent::__construct($lines);
    }
}
