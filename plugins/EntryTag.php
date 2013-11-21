<?php


/**
 * Matches web-style hash tags: #tags #mutli_word_tag
 * No spaces allowed, but all UTF-8 chars recognised
 * Underscores are displayed as spaces
 * Also honours the * at the end like normal entries
 */
class SI_EntryTag extends SI_Entry {

    public $order   = 10;
    public $type    = 'tag';
    public $section = 1;

    // first is for Dokuwiki syntax parser matching, second for internal lexing
    public $regex = '(?<=\s|^)\#[^\s]+';
    private $_regex = '(?:\s|^)\#([^#*\s]+)(\*?)';


    function __construct() {
        $this->_regex = parent::add_ignore_syntax($this->_regex);
    }

    function match($text) {
        $this->items = array();
        $matches = array();
        $hits = preg_match_all($this->_regex, $text, $matches, PREG_SET_ORDER);
        if ($hits > 0) {
            foreach ($matches as $match) {
                $item = &$this->items[];
                $tag = $match[1];
                $item['entry'] = $tag;
                $item['display'] = str_replace('_', ' ', $tag);  // swap '_' for spaces for display
                $item['section'] = $this->section;
                $item['type'] = $this->type;
                $item['star'] = $match[2] == '*' ? true : false;
            }
            return true;
        } else {
            return false;
        }
    }
}