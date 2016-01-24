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

    function __construct() {
        $this->matcher = new SI_MatchEntry();
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
        // avoid problems with code; {} show up a lot in php and js code!
        if ( ! in_array($mode, array('unformatted', 'preformatted', 'code', 'file', 'php', 'html'))) {
            foreach ($this->matcher as $matcher) {
                $this->Lexer->addSpecialPattern($matcher->regex, $mode, 'plugin_subjectindex_entry');
            }
        }
	}


	function handle($match, $state, $pos, Doku_Handler $handler) {

        if ($this->matcher->match($match) === true) {
            $item          = $this->matcher->first;
            $item['entry'] = $this->_remove_ord($item['entry']); // remove any ordered list numbers (used for manual sorting)
            $link_id       = SI_Utils::valid_id($item['entry']);
            $target_page   = SI_Utils::get_target_page($item['section']);
            $sep           = $this->getConf('subjectindex_display_sep');
            $path          = str_replace('/', $sep, $item['entry']);
            return array($item, $path, $link_id, $target_page);
        } else {
            // It wasn't a recognised item so just return the original match for display
            return $match;
        }
	}


    private function _remove_ord($text) {
        $text = preg_replace('`^\d+\.`', '', $text);
        $text = preg_replace('`\/\d+\.`', '/', $text);
        return $text;
    }


    // *************************************

	function render($mode, Doku_Renderer $renderer, $data) {

        if ($mode == 'xhtml') {
            // just re-display a failed match
            if ( ! is_array($data)) {
                $renderer->doc .= $data;
            } else {
                list($item, $path, $link_id, $target_page) = $data;
                $star     = $item['star'];
                $star_css = ($star) ? '' : ' no-star';
                $display  = $item['display'];
                if ($display == '-') {
                    // hidden text
                    $display    = '';
                    $hidden_css = ($star === false) ? ' hidden' : ' no text';
                } else {
                    // visible text
                    if (empty($display)) $display = $path;
                    $display    = $this->_html_encode($display);
                    $hidden_css = '';
                }
                $type = "\n(" . $item['section'] . '-' . $this->getLang($item['type'] . '_type') . ')';
                if (empty($target_page)) {
                    $target_page = '';
                    $title       = $this->getLang('no_default_target') . $type;
                    $class       = 'bad-entry';
                } else {
                    $target_page = wl($target_page) . '#' . $link_id;
                    if (isset($item['title'])) {
                        $title = $this->_html_encode($item['title']);
                    } else {
                        $title = $this->_html_encode($path . $type);
                    }
                    $class = 'entry';
                }

                $renderer->doc .= '<a id="' . $link_id .
                                  '" class="' . $class . $hidden_css . $star_css .
                                  '" title="' . $title .
                                  '" href="' . $target_page . '">' .
                                  $display .
                                  '</a>';
            }
			return true;
		}
		return false;
	}


    private function _html_encode($text) {
        $text = htmlentities($text, ENT_QUOTES, 'UTF-8');
        return $text;
    }
}
