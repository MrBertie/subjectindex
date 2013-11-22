<?php


/**
 * Matches the default syntax:
 * {{entry>section/path/to/entry|display*}}  [Deprecated]
 *  OR
 * {{entry>section;path/to/item;display*}}   [Preferred: matches default syntax]
 * e.g.
 * {{entry>2;Home/Gardening/Bonsai;Planting Bonsai*}}
 *
 *  Display formatting:
 *   ...|text}}	    User-defined text
 *   ...|text*}}    User-defined text + star(*)
 *   ...|-}}	    No text, i.e. hidden
 *   ...|-*}}	    No text, but show star(*)
 *   ...|*}}	    Use entry text + star(*)
 *   ...|}}		    Use entry text
 *   ...}}		    Use entry text (default)
 *
 *  Therefore: 	'-' => no text at all
 *              '*' => add a star symbol to end.
 *
 * This is the standard format and all other syntax matchers are converted into this format for processing
 */
class SI_EntryDefault extends SI_Entry {

    public $order   = 30;
    public $type    = 'default';
    public $section = 0;
    public $types   = array();

    // first is for Dokuwiki syntax parser matching, second for internal lexing
    public $regex = '\{\{entry>.+?\}\}';
    // this regex allows for both old and new syntax (nasty!)
    private $_regex = '`\{\{entry>(?:(\d+)[;\/])?([^|;}]+)(?:[;|](.*?)(\*?))?\}\}`';

    // [1] = section; [2] = entry; [3] = display; [4] = star


    function match($text) {
        $this->items = array();
        $matches =  array();
        $hits  = preg_match_all($this->_regex, $text, $matches, PREG_SET_ORDER);
        if ($hits > 0) {
            foreach ($matches as $match) {
                $item = &$this->items[];
                $item['section'] = ( ! empty($match[1])) ? $match[1] : $this->section;
                // special case: default items can be of any type/section...
                $item['type'] = $this->type;
                if ($item['type']== 'default' && isset($this->types[$item['section']])) {
                    $item['type'] = $this->types[$item['section']];
                }
                $item['entry'] = $match[2];
                if (isset($match[3])) {
                    $item['display'] = $match[3];
                    $item['star'] = ($match[4] == '*');
                } else {
                    $item['display'] = '';
                    $item['star'] = false;
                }
            }
            return true;
        } else {
            return false;
        }
    }
}