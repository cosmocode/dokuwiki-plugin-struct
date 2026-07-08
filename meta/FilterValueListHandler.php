<?php

namespace dokuwiki\plugin\struct\meta;

/**
 * Handler for the row value parser
 */
class FilterValueListHandler
{
    protected $row = [];
    protected $current_row = 0;
    protected $token = '';

    /**
     * @return array
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * Dispatch a lexer token to the matching mode handler.
     *
     * Required by the token based Lexer contract used by current DokuWiki.
     * Older releases call the mode named methods directly, so both dispatch
     * styles are supported.
     *
     * @param string $modeName resolved mode name
     * @param string $match matched text
     * @param int $state one of the DOKU_LEXER_* constants
     * @param int $pos byte index where the match was made
     * @param string $originalModeName mode name before mapHandler() remapping
     * @return bool
     */
    public function handleToken($modeName, $match, $state, $pos, $originalModeName = '')
    {
        switch ($modeName) {
            case 'row':
                return $this->row($match, $state, $pos);
            case 'singleQuoteString':
                return $this->singleQuoteString($match, $state, $pos);
            case 'escapeSequence':
                return $this->escapeSequence($match, $state, $pos);
            case 'number':
                return $this->number($match, $state, $pos);
        }
        return true;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function row($match, $state, $pos)
    {
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
    public function singleQuoteString($match, $state, $pos)
    {
        if ($state === DOKU_LEXER_UNMATCHED) {
            $this->token .= $match;
        }
        return true;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function escapeSequence($match, $state, $pos)
    {
        //add escape character to the token
        $this->token .= $match[1];
        return true;
    }

    /**
     * @param string match contains the text that was matched
     * @param int state - the type of match made (see below)
     * @param int pos - byte index where match was made
     */
    public function number($match, $state, $pos)
    {
        $this->token = $match;
        return true;
    }
}
