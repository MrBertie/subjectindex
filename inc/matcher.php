<?php


// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');



class SI_MatchEntry implements IteratorAggregate {

    public $all = array();
    public $first = array();

    private $matchers = array();


    function __construct() {
        $default = null;
        $types = array();
        $files = glob(SUBJ_IDX_PLUGINS . 'Entry*.php');
        foreach ($files as $file) {
            $matched = preg_match('/Entry(.+?)\.php/', $file, $match);
            if ($matched) {
                require_once($file);
                $class = 'SI_Entry' . $match[1];
                $matcher = new $class();
                $this->matchers[$matcher->order] = $matcher;
                $types[$matcher->section] = $matcher->type;
                if ($matcher->type == 'default') $default = $matcher;
            }
        }
        $default->types = $types;
        ksort($this->matchers);
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



/**
 * Base class for all Matchers
 */
class SI_Entry {

    public $order = 0;          // order in which to use this matcher
    public $items = array();    // the different elements of the match: section, display, entry, type
    public $type = 'default';   // type of entry matched
    public $section = 0;        // section this entry will be added to
    public $regex = '';

    function match() {
    }

    function add_ignore_syntax($regex) {
        return '`(?!=(\'\'|%%))' . $regex . '(?!(\'\'|%%))`';
    }
}