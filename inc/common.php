<?php
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

define('SUBJ_IDX_CHECK_TIME', DOKU_PLUGIN . 'subjectindex/conf/last_cleanup');
define('SUBJ_IDX_DEFAULT_TARGETS', DOKU_PLUGIN . 'subjectindex/conf/default_targets');

define('SUBJ_IDX_INDEX_NAME', 'subject');
define('SUBJ_IDX_DEFAULT_DIR', DOKU_INC . 'data/index/');
define('SUBJ_IDX_DEFAULT_PAGE', ':subjectindex');

define('SUBJ_IDX_SECTION_MAX', 9);

/**
 * Returns the subject index file name
 *
 * @param string $data_dir  where subject index file is located
 * @return string
 */
function get_subj_index($data_dir) {
    $filename = SUBJ_IDX_INDEX_NAME . '.idx';
    if (is_dir($data_dir)) {
        $index_file = $data_dir . '/' . $filename;
    } else {
        $index_file = SUBJ_IDX_DEFAULT_DIR . $filename;
    }
    // create if missing
    if ( ! is_file($index_file)) {
        fclose(fopen($index_file, 'w'));
    }
    return $index_file;
}
/**
 * Gets the correct target wiki page name based on a (section) number
 * Returns an empty string if missing
 */
function get_target_page($section = 0) {
    $pages = unserialize(file_get_contents(SUBJ_IDX_DEFAULT_TARGETS));
    if ($pages !== false && isset($pages[$section])) {
        return $pages[$section];
    } else {
        return '';
    }
}
/**
 * Adds/Updates a target page for entry links
 */
function set_target_page($page, $section = 0) {
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
function clean_id($text) {
    $text = strtolower($text);
    $text = str_replace('/', '-', $text);
    $text = preg_replace('/[^0-9a-zA-Z-_]/', '', $text);
    return $text;
}
/**
 * Does this page: exist, is it visible, and does user have rights to see it?
 */
function valid_page($id) {
    $id = trim($id);
    if(page_exists($id) && isVisiblePage($id) && ! (auth_quickaclcheck($id) < AUTH_READ)) {
        return true;
    } else {
        return false;
    }
}