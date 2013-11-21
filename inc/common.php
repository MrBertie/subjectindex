<?php

// must be run within Dokuwiki
if( ! defined('DOKU_INC')) die();

global $conf;
define('SUBJ_IDX_DIR', $conf['indexdir'] . '/');
define('SUBJ_IDX_FILE', SUBJ_IDX_DIR . 'subjectindex.idx');
define('SUBJ_IDX_PLUGINS', DOKU_PLUGIN . 'subjectindex/plugins/');
define('SUBJ_IDX_DEFAULT_TARGETS', DOKU_PLUGIN . 'subjectindex/conf/default_targets');
define('SUBJ_IDX_LAST_CLEANUP', DOKU_PLUGIN . 'subjectindex/conf/last_cleanup');



/**
 * Class Index
 * Contains and manages the basic index data structure:
 * $path : key => section/path/to/entry
 * $pid :  key => page id where this entry is found
 */
class SI_Index implements Iterator {
    public $paths = array();
    public $pids = array();


    function __construct(Array $paths = null, Array $pids = null) {
        if ($paths === null) {
            $paths = $pids = array();
        }
        $this->paths = $paths;
        $this->pids  = $pids;
    }


    function add($path, $pid) {
        $this->paths[] = $path;
        $this->pids[]  = $pid;
    }


    function remove($key) {
        unset($this->paths[$key]);
        unset($this->pids[$key]);
    }


    function is_empty() {
        return empty($this->paths);
    }


    function sort() {
        uasort($this->paths, array($this, '_pathcmp'));
    }


    function rewind() {
        reset($this->paths);
        return $this;
    }

    function current($get_section = false) {
        if ($this->valid()) {
            $path = current($this->paths);
            if ( ! $get_section) {
                list($_, $path) = explode('/', $path, 2);
            }
            $key = key($this->paths);
            $pid = $this->pids[$key];
            $result = array($path, $pid );
        } else {
            $result = array(null, null);
        }
        return $result;
    }

    function key() {
        return key($this->paths);
    }

    function next($get_section = false) {
        next($this->paths);
        $result = $this->current($get_section);
        return $result;
    }

    function valid() {
        $valid = current($this->paths) !== false;
        return $valid;
    }


    /**
     * Filter the index by section, regex (on path) or pid
     * and return a new Index instance.
     *
     * @param null $section
     * @param null $regex
     * @param null $pid
     * @return SI_Index
     */
    function filtered($section = null, $regex = null, $pid = null) {
        $fpaths = $this->paths;
        if ($section !== null && is_numeric($section)) {
            $fpaths = preg_grep('`^' . $section . '\/.*`', $fpaths);
        } elseif ( ! empty($regex)) {
            $fpaths = preg_grep('`' . $regex . '`', $fpaths);
        } elseif ($pid !== null) {
            $fpaths = array_intersect_key($this->paths, preg_grep('/' . $pid . '/', $this->pids));
        }
        $fpids = array_intersect_key($this->pids, $fpaths);
        $index = new SI_Index($fpaths, $fpids);
        return $index;
    }




    /**
     * String compare function: sorts index "paths" correctly
     * i.e. root paths come before leaves
     */
    private function _pathcmp($a, $b) {
        $a_txt = strtok($a, '|');
        $b_txt = strtok($b, '|');
        if (strnatcasecmp($a_txt,$b_txt) != 0) {
            $a_sub = strpos($b_txt, $a_txt);
            $b_sub = strpos($a_txt, $b_txt);
            if ($a_sub !== false && $a_sub == 0) {
                return -1;
            } elseif ($b_sub !== false && $b_sub == 0) {
                return 1;
            }
        }
        return strnatcasecmp($a, $b);
    }
}


/**
 * Handles updating and cleaning the SI index
 */
class SI_Indexer {

    private $index;

    function __construct(SI_Index $index = null) {
        if ($index === null) {
            $this->index = SI_Utils::get_index();
        } else {
            $this->index = $index;
        }
    }


    function cleanup(Array $all_pages) {
        // first remove any entries that reference non-existant files (currently once a day!)
        if ($this->_cleanup_time()) {
            $this->_remove_invalid_entries($this->index, $all_pages);
        }
        return $this;
    }


    /**
     * Updates a list of matched entries in a page
     * Creates, deletes or updates as necessary
     *
     * @param integer $pid              The page id of the page being updated; needed to reference the correct page's entries
     * @param array   $matched_entries    List of all current entries in the page (could be existing or new)
     * @return SI_Indexer
     */
    function update($pid, Array $matched_entries) {

        // grab all existing subject index entries for this page
        $page_index = $this->index->filtered(null, null, $pid);
        $page_paths = $page_index->paths;
        $updated = false;
        foreach ($matched_entries as $match) {
            $matched_path = $match['section'] . '/' . $match['entry'];
            // compare the previous entries with the current entries (matched), does it exist already?
            $key = array_search($matched_path, $page_paths);
            if ($key !== false) {
                // EXISTS:
                unset($page_paths[$key]);
            } else {
                // CREATE:
                $this->index->add($matched_path, $pid);
                $updated = true;
            }
        }
        // DELETE: remove index entries that no longer exist on this page
        foreach (array_keys($page_paths) as $key) {
            $this->index->remove($key);
            $updated = true;
        }

        if ($updated) {
            $this->index->sort();
        }
        return $this;
    }


    function save() {
        SI_Utils::save_index($this->index);
        return $this;
    }


    /**
     * Removes any pages that point to non-existing or otherwise invalid pages.
     * Currently once a day.
     *
     * @param SI_Index $index   The SI entry index
     * @param $all_pages        The Dokuwiki page index
     */
    private function _remove_invalid_entries(SI_Index $index, $all_pages) {
        $missing_pids = array();
        foreach ($index->pids as $key => $pid) {
            // here we first check if the pid has already been processed,
            // if so we just add the key straight to the missing list
            // saving an unnecessary page check
            if (isset($missing_pids[$pid]) || ! SI_Utils::is_valid_page($all_pages[$pid])) {
                $missing_pids[$pid][] = $key;
            }
        }
        foreach ($missing_pids as $pid) {
            foreach ($pid as $key)
            $index->remove($key);
        }
    }


    /**
     * Returns true if a full day has passed since last cleanup
     * @return bool true => time to do clean up
     */
    private function _cleanup_time() {
        $last_cleanup = file_get_contents(SUBJ_IDX_LAST_CLEANUP);
        if ($last_cleanup === false) $last_cleanup = 0;
        if ($last_cleanup == 0 || time() > $last_cleanup + 60 * 60 * 24) {
            file_put_contents(SUBJ_IDX_LAST_CLEANUP, time());
            return true;
        } else {
            return false;
        }
    }
}



class SI_Utils {

    /**
     * Get the subject index.
     * Simply stored as a serialized pair of arrays,
     * Tested up to 32,000 entries and still quicker than text files...
     *
     * @return SI_Index
     */
    static function get_index() {

        // first check for old index format (deprecated, was slower)
        $fn = SUBJ_IDX_DIR . 'subject.idx';
        if (file_exists($fn)) {
            list($paths, $pids) = self::import_old($fn);
            $index = new SI_Index($paths, $pids);
            unlink($fn);
            self::save_index($index);
        } else {
            if (file_exists(SUBJ_IDX_FILE)) {
                $data = file_get_contents(SUBJ_IDX_FILE);
                $index = unserialize($data);
            } else {
                $index = new SI_Index();
            }
        }
        return $index;
    }


    private function import_old($fn) {
        $entries = file($fn, FILE_IGNORE_NEW_LINES);
        $path = array();
        $pid = array();
        foreach ($entries as $entry) {
            $delim = stripos($entry, '|');
            $path[] = substr($entry, 0, $delim);
            $pid[] = substr($entry, $delim + 1);
        }
        return array($path, $pid);
    }


    static function save_index(SI_Index $index) {
        file_put_contents(SUBJ_IDX_FILE, serialize($index));
    }

    /**
     * Gets target wiki page name based on a section number.
     *
     * @param int $section  index section number
     * @return string       page name | empty string ('') if missing
     */
    static function get_target_page($section = 0) {
        $pages = unserialize(file_get_contents(SUBJ_IDX_DEFAULT_TARGETS));
        if ($pages !== false && isset($pages[$section])) {
            return $pages[$section];
        } else {
            return '';
        }
    }


    /**
     * Adds/Updates default target wiki page for entry links in a given section.
     */
    static function set_target_page($page, $section = 0) {
        // create if missing
        if ( ! is_file(SUBJ_IDX_DEFAULT_TARGETS)) {
            $pages = array();
        } else {
            $pages = unserialize(file_get_contents(SUBJ_IDX_DEFAULT_TARGETS));
        }
        $pages[$section] = $page;
        file_put_contents(SUBJ_IDX_DEFAULT_TARGETS, serialize($pages));
    }


    /**
     * Removes invalid chars from any string to make it suitable for use as a HTML id attribute
     * @param string $text Any text string
     * @return string A string suitable for a HTML id attribute
     */
    static function valid_id($text) {
        $text = strtolower($text);
        $text = str_replace('/', '-', $text);
        $text = preg_replace('/[^0-9a-zA-Z-_]/', '', $text);
        return $text;
    }


    /**
     * Does this page: exist, is it visible, and does user have rights to see it?
     */
    static function is_valid_page($id) {
        $id = trim($id);
        return (page_exists($id) && isVisiblePage($id) && ! (auth_quickaclcheck($id) < AUTH_READ));
    }
}