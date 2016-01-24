<?php


/**
 * Subject Index plugin : control macro
 * ~~NOSUBJECTINDEX~~
 *
 * Removes the macro syntax from displayed page.
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



class syntax_plugin_subjectindex_ignore extends DokuWiki_Syntax_Plugin {

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
        $this->Lexer->addSpecialPattern('~~(?i)NOSUBJECTINDEX(?-i)~~', $mode, 'plugin_subjectindex_ignore');
	}


	function handle($match, $state, $pos, Doku_Handler $handler) {
       // For use by indexer only in raw wiki text, not for display
        return $match;
	}


	function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode == 'xhtml') {
            $renderer->doc .= '';
        } else {
            return false;
        }
	}
}
