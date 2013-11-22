<?php

/**
 * SubjectIndex plugin indexer
 *
 * @author  Symon Bent
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');



class action_plugin_subjectindex_indexer extends DokuWiki_Action_Plugin {

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(&$controller) {
        $controller->register_hook('INDEXER_PAGE_ADD', 'AFTER', $this, 'handle');
    }


    function handle(&$event, $param) {
        require_once(DOKU_INC . 'inc/indexer.php');
        require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');
        require_once(DOKU_PLUGIN . 'subjectindex/inc/matcher.php');

        if (isset($event->data['page'])) {
            $page = $event->data['page'];
        }
        if (empty($page)) return;   // get out if no such wiki-page

        $raw_page = rawWiki($page);

        $all_pages = idx_getIndex('page', '');
        $indexer = new SI_Indexer();

        // first remove any entries that reference non-existant files (currently once a day!)
        $indexer->cleanup($all_pages);

        // now get all marked up entries for this wiki page
        $matched_entries = array();
        if ( ! $this->_skip_index($raw_page)) {
            $matcher = new SI_MatchEntry();
            if ($matcher->match($raw_page) === true) {
                $matched_entries = $matcher->all;
            }
        }

        // get page id--this corresponds to line number in page.idx file
        $doku_indexer = idx_get_indexer();
        $page_id = $doku_indexer->getPID($page);

        $indexer->update($page_id, $matched_entries)->save();
    }


    private function _skip_index($page) {
        $skip_index = (preg_match('`~~NOSUBJECTINDEX~~`i', $page) == 1);
        return $skip_index;
    }
}