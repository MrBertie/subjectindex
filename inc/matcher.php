<?php
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');


class MatchEntry implements IteratorAggregate {
    public $all = array();
    public $first = array();
    private $types = array('Tag', 'Verse', 'Plain');    // NOTE: Add additional Matchers to this list in matching order
    private $matchers = array();

    function __construct() {
        foreach ($this->types as $type) {
            $class = 'Entry' . $type;
            $matcher = new $class();
            $this->matchers[] = $matcher;
        }
    }

    function match($text) {
        $matches = array();
        $matched = false;
        foreach ($this->matchers as $matcher) {
            if ($matcher->match($text) === true) {
                $matches = array_merge($matches, $matcher->items);
                $matched = true;
            }
        }
        $this->all= $matches;
        $this->first = $matches[0];
        return $matched;
    }

    function getIterator() {
        return new ArrayIterator($this->matchers);
    }
}


class Entry {

    public $items = array();    // the different elements of the match: section, display, entry, type
    public $type = 'plain';     // type of entry matched
    public $section = 0;        // section this entry will be added to

    public $regex = '';

    function match() {
    }
}


class EntryPlain extends Entry {
    // first is for Dokuwiki syntax parser matching, second for internal lexing
    public $regex = '\{\{entry>.+?\}\}';
    private $_regex = '/\\{\{entry>((\d+)(\/))*([^|]+)((\|)(.*?))?\}\}/';
    // [2] = section; [4] = entry; [7] = display text

    function __construct() {
        $this->type = 'plain';
    }

    function match($text) {
        $this->items = array();
        $matches = array();
        $hits = preg_match_all($this->_regex, $text, $matches, PREG_SET_ORDER);
        if ($hits > 0) {
            foreach ($matches as $match) {
                $item = &$this->items[];
                $item['section'] = ( ! empty($match[2])) ? $match[2] : '0';
                $item['display'] = (isset($match[7])) ? $match[7] : '';
                $item['entry'] = $match[4];
                $item['type'] = $this->type;
            }
            return true;
        } else {
            return false;
        }
    }
}


/**
 * Matches web-style hash tags: #tags #mutli_word_tag
 * No spaces allowed, but all UTF-8 chars recognised
 * Underscores are displayed as spaces
 */
class EntryTag extends Entry {
    // first is for Dokuwiki syntax parser matching, second for internal lexing
    public $regex = '(?<=\s|^)\#[^\s]+';
    private $_regex = '/(?<=\s|^)\#([^\s]+)/';

    function __construct() {
        $this->type = 'tag';
        $this->section = 1;
    }

    function match($text) {
        $this->items = array();
        $matches = array();
        $hits = preg_match_all($this->_regex, $text, $matches, PREG_SET_ORDER);
        if ($hits > 0) {
            foreach ($matches as $match) {
                $item = &$this->items[];
                $tag = utf8_trim($match[1], '#');  // remove any '#''s
                $item['entry'] = $tag;
                $item['display'] = str_replace('_', ' ', $tag);  // swap '_' for spaces for display
                $item['section'] = $this->section;
                $item['type'] = $this->type;
            }
            return true;
        } else {
            return false;
        }
    }
}


/**
 * Matches bible verse references
 * No allowance for multiple verse references with only one book name!
 */
class EntryVerse extends Entry {
    // first is for Dokuwiki syntax parser matching, second for internal lexing
    public $regex = '(?:[123]\h?)?(?:[A-Z][a-zA-Z]+|Song of Solomon)\s1?[0-9]?[0-9]:\d{1,3}(?:[,-]\d{1,3})*';
    private $_regex = '/([123]\s*)?([A-Z][a-zA-Z]+|Song of Solomon)\s*(1?[0-9]?[0-9]):\s*(\d{1,3}([,-]\s*\d{1,3})*)/';

    function __construct() {
        $this->section = 2;
        $this->type = 'verse';
        // array of possible abbreviations (space separated)
        $this->abbrs = file(DOKU_PLUGIN . 'subjectindex/conf/bible_abbr.txt', FILE_IGNORE_NEW_LINES | FILE_TEXT);
        // array of proper book names
        $this->books = file(DOKU_PLUGIN . 'subjectindex/conf/bible_books.txt', FILE_IGNORE_NEW_LINES | FILE_TEXT);
    }

    function match($text) {
        $this->items = array();
        $matches = array();
        $matched = false;
        $hits = preg_match_all($this->_regex, $text, $matches, PREG_SET_ORDER);
        if ($hits > 0) {
            foreach ($matches as $match) {
                $abbr = $match[1] . strtolower($match[2]);
                $book = (empty($match[1])) ? $match[2] : $match[1] . ' ' . $match[2];
                $chp = $match[3];
                $verse = $match[4];
                // abbreviation match test
                $hit = (preg_grep('/(^|\s)' . $abbr . '($|\s)/', $this->abbrs));
                // try for a full book name match also if abbr fails
                if (empty($hit)) {
                    $hit = preg_grep('/(^|\s)' . $book . '($|\s)/', $this->books);
                }
                if ( ! empty($hit)) {
                    $num = key($hit);   // line number in abbr list, used to keep books in correct order
                    $book = $this->books[$num];
                    $item = &$this->items[];

                    $item['display'] = $book . ' ' . $chp . ':' . $verse;
                    // add an ordinal to keep the book names in correct order
                    $item['entry'] = $num . '.' . $book . '/' . $book . ' ' . $chp . ':/' . $verse;
                    $item['section'] = $this->section;
                    $item['type'] = $this->type;
                    $matched = true;
                }
            }
            return $matched;
        }
        return false;
    }
}