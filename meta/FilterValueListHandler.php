<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Handler for the row value parser
 */
class FilterValueListHandler {

    protected $row = array();
    protected $current_row = 0;
    protected $token = '';

    /**
     * @return array
     */
    public function get_row() {
        return $this->row;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function row($match, $state, $pos) {
        switch ($state) {
            // The start of the list...
            case DOKU_LEXER_ENTER:
                break;

            // The end of the list
            case DOKU_LEXER_EXIT:
                $this->row[$this->current_row] = $this->token;
                break;

            case DOKU_LEXER_MATCHED:
                $this->row[$this->current_row] = $this->token;
                $this->token = '';
                $this->current_row++;
                break;
        }
        return true;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function single_quote_string($match, $state, $pos) {
        switch ($state) {
            case DOKU_LEXER_UNMATCHED:
                $this->token .= $match;
                break;
        }
        return true;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function escape_sequence($match, $state, $pos) {
        //add escape character to the token
        $this->token .= $match[1];
        return true;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function number($match, $state, $pos) {
        $this->token = $match;
        return true;
    }
}