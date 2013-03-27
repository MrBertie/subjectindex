<?php
/**
 * Subject Index plugin : entry syntax
 * indexes any subject index entries on the page (to data/index/subject.idx by default)
 *
 * Using the {{entry>[heading/sub-heading/]entry[|display text]}} syntax
 * a new subject index entry can be added
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Symon Bent <hendrybadao@gmail.com>
 *
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');
require_once(DOKU_PLUGIN . 'subjectindex/inc/matcher.php');


class syntax_plugin_subjectindex_entry extends DokuWiki_Syntax_Plugin {
    private $matcher = MatchEntry;

    function __construct() {
        $this->matcher = new MatchEntry();
    }

    function getType() {
		return 'substition';
	}

	function getSort() {
		return 305;
	}

	function getPType(){
		return 'normal';
	}

	function connectTo($mode) {
        if ( ! in_array($mode, array('preformatted', 'code', 'file', 'php', 'html'))) {
            foreach ($this->matcher as $matcher) {
                $this->Lexer->addSpecialPattern($matcher->regex, $mode, 'plugin_subjectindex_entry');
            }
        }
	}

	function handle($match, $state, $pos, &$handler) {

        if ($this->matcher->match($match) === true) {
            $entry = $this->matcher->first['entry'];
            $display = $this->matcher->first['display'];
            $section = $this->matcher->first['section'];
            $type = $this->matcher->first['type'];

            require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');
            $link_id = SubjectIndex::clean_id($entry);

            // remove any ordered list numbers (used for manual sorting)
            $entry = $this->remove_ord($entry);
            $sep = $this->getConf('subjectindex_display_sep');

            $hide = false;
            if ($display == '-') {
                // invisible entry, do not display at all
                $display = '';
                $hide = true;
            } elseif (empty($display)) {
                // default is full text of entry
                $display = str_replace('/', $sep, $entry);
            }
            $entry = str_replace('/', $sep, $entry);
            $target_page = SubjectIndex::get_target_page($section);

            return array($entry, $display, $link_id, $target_page, $hide, $type);

        } else {
            // It wasn't a recognised item so just return the original match for display
            return $match;
        }
	}


	function render($mode, &$renderer, $data) {

        if ($mode == 'xhtml') {
            // just re-display a failed match
            if ( ! is_array($data)) {
                $renderer->doc .= $data;
            } else {
                list($entry, $display, $link_id, $target_page, $hide, $type) = $data;
                if ($display == '*') {
                    $display = '';
                    $hidden = ' no-text';
                } elseif ($hide) {
                    $hidden = ' hidden';
                } else {
                    $hidden = '';
                }
                $entry = $this->getLang($type . '_prefix') . $entry;
                $display = $this->html_encode($display);

                if (empty($target_page)) {
                    $target_page = '';
                    $title = $this->getLang('no_default_target');
                    $class = 'bad-entry';
                } else {
                    $target_page = wl($target_page) . '#' . $link_id;
                    $title = $this->html_encode($entry);
                    $class = 'entry';
                }

                $renderer->doc .= '<a id="' . $link_id . '" class="' . $class . $hidden .
                                  '" title="' . $title .
                                  '" href="' . $target_page . '">' .
                                  $display .
                                  '</a>' . DOKU_LF;
            }
			return true;
		}
		return false;
	}


    // *************************************


    private function html_encode($text) {
        $text = htmlentities($text, ENT_QUOTES, 'UTF-8');
        return $text;
    }


    private function remove_ord($text) {
        $text = preg_replace('`^\d+\.`', '', $text);
        $text = preg_replace('`\/\d+\.`', '/', $text);
        return $text;
    }
}
