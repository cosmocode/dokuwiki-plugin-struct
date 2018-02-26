<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Class InlineConfigParser
 *
 * Wrapper to convert inline syntax to full before instantiating ConfigParser
 *
 * @package dokuwiki\plugin\struct\meta
 */
class InlineConfigParser extends ConfigParser {

    /**
     * Parser constructor.
     *
     * parses the given inline configuration
     *
     * @param  string  $inline
     */
    public function __construct($inline) {
        // To support a fuller syntax, such as user-specified filters, tokenise here

        // Split into components
        $components = str_getcsv($inline, '.');

        // Protect against using single quotes
        foreach ($components as $component) {
            if ( substr($component, 0, 1) == "'" ) {
                // Used single quotes rather than double - will need to rerun CSV extraction
                $enclosure = "'";
            }
        }
        if ($enclosure == "'") $components = str_getcsv($inline, '.', $enclosure);

        // Start to build the main config array
        $lines = array();

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
            $lines[] = 'filter: %pageid% = ' . $GLOBALS['ID'];
        } 

        // Call original ConfigParser's constructor
        parent::__construct($lines);
    }
}
