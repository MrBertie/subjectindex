<?php
/**
 * Part of Subject Index plugin:
 *
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author	   Symon Bent <symonbent@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_subjectindex_index extends DokuWiki_Syntax_Plugin {

	function getType() {
		return 'substition';
	}

	function getPType() {
		return 'block';
	}

	function getSort() {
		return 98;
	}

	function connectTo($mode) {
		$this->Lexer->addSpecialPattern('\{\{subjectindex>.*?\}\}', $mode, 'plugin_subjectindex_index');
	}

	function handle($match, $state, $pos, &$handler) {
        global $ID;

		$match = substr($match, 15, -2); // strip "{{subjectindex>...}}" markup

        $opt = array();

        // defaults
        $opt['abstract'] = true;       // show abstract of page content
        $opt['border'] = 'none';       // show borders around table columns
        $opt['cols'] = 1;              // number of columns in a SubjectIndex display page (max=6)
        $opt['section'] = 0;           // which section to use and display (0-9)...hopefully 10 is enough
        $opt['proper'] = false;        // use proper-case for page names
        $opt['title'] = false;         // use title instead of name
        $opt['noAtoZ'] = false;        // turn off the A,B,C main headings
        $opt['showorder'] = false;     // display any bullet numbers used for ordering
        $opt['default'] = false;       // whether this display index page is the default for this index section number

		$args = explode(';', $match);
        foreach ($args as $arg) {
            list($key, $value) = explode('=', $arg);
            switch ($key) {
                case 'abstract':
                case 'proper':
                case 'title':
                case 'noAtoZ':
                case 'noatoz':
                case 'showorder':
                case 'default':
                    $opt[strtolower($key)] = true;
                    break;
                case 'border':
                    switch ($value) {
                        case 'none':
                        case 'inside':
                        case 'outside':
                        case 'both':
                            $opt['border'] = $value;
                            break;
                        default:
                            $opt['border'] = 'both';
                    }
                    break;
                case 'cols':
                    if ($value < 1) {
                        $value = 1;
                    } elseif ($value > 12) {
                        $value = 12;
                    }
                    $opt['cols'] = $value;
                    break;
                case 'section':
                    $opt['section'] = ($value <= SUBJ_IDX_SECTION_MAX) ? $value : 0;
                    break;
                default:
            }
        }
        // update the list of default target pages for entry links
        if ($opt['default'] === true) {
            set_target_page($ID, $opt['section']);
        }
		return $opt;
	}

    function render($mode, &$renderer, $opt) {
        if ($mode == 'xhtml') {
            $renderer->info['cache'] = false;

            require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');
            $subject_idx = file(get_subj_index($this->getConf('subjectindex_data_dir')));
            if (empty($subject_idx)) {
                $renderer->doc .= $this->getLang('empty_index');
                return false;
            }
            require_once (DOKU_INC . 'inc/indexer.php');
            $page_idx = idx_getIndex('page', '');

            list($lines, $heights) = $this->_create_index($opt['section'], $subject_idx, $page_idx, $opt['noatoz'], $opt['proper']);
            $renderer->doc .= $this->_render_index($lines, $heights, $opt);
        } else {
            return false;
        }
    }

    private function _create_index($section, $subject_idx, $page_idx, $noAtoZ, $proper) {
        // grab only items for chosen index section
        $subject_idx = preg_grep('/^' . $section . '.+/', $subject_idx);

        // ratio of different heading heights (%), to ensure more even use of columns (h1 -> h6)
        $ratios = array(1.3, 1.17, 1.1, 1.03, .96, .90);

        $lines = array();
        $heights = array();
        $links = array();

        // first build a list of valid subject entries to be rendered, plus their heights
        list($next_entry, $next_pid) = $this->_split_entry(current($subject_idx));
        $prev_entry = '';

        do {
            $entry = $next_entry;
            $pid = $next_pid;

            // cache the next entry for comparison purposes
            $next = next($subject_idx);
            if ($next !== false) {
                list($next_entry, $next_pid) = $this->_split_entry($next);
            } else {
                $next_entry = '';
                $next_pid = '';
            }

            $page = rtrim($page_idx[intval($pid)], "\n\r");
            if ( ! valid_page($page)) continue;

            // note: all comparisons are caseless (this is an A-Z index after all!)
            $next_differs = strcasecmp($entry, $next_entry) != 0;

            if ( ! $noAtoZ) {
                // check for ordered entries 1
                $matched = preg_match('/(^\d+\.)?(.).+/', $entry, $matches);
                if ($matched > 0) {
                    $entry = $matches[1] . strtoupper($matches[2]) . '/' . $entry; // A-Z heading
                } else {
                    $entry = strtoupper($entry[0]) . '/' . $entry;
                }
            }
            $cur_level = strtok($entry, '/');
            $cur_entry = '';
            $lvl = 1;

            do {
                $next_level = strtok('/');
                $cur_entry .= (empty($cur_entry)) ? $cur_level : '/' . $cur_level;  // current heading state

                // we can add the page link only if this is the final level
                $is_link = ($next_level === false);
                if ($is_link) $links[] = $page;

                // we only make headings that are different from the previous
                $match = strpos($prev_entry, $cur_entry);
                $is_level = ($match === false || $match != 0);

                // only render if:
                //   1. next entry is different, and...
                //   2. this is a new level or a link
                //   (this ensures that links will be grouped)
                if ($next_differs && ($is_level || $is_link)) {
                    if ($proper) $cur_level = ucwords($cur_level);
                    if ($is_link) {
                        $anchor = clean_id($entry);
                        $lines[] = array($lvl, $cur_level, $links, $anchor);
                        $links = array();
                    } else {
                        $lines[] = array($lvl, $cur_level, '' ,'');
                    }
                    $heights[] = $ratios[$lvl] - 1;
                }
                $lvl = ($lvl > 5) ? 6 : $lvl + 1; // html heading number 1-6 (forgive the magic no's = h1 to h6)
                $cur_level = $next_level;
            } while ($next_level !== false);

            $prev_entry = $entry;
        } while ($next !== false);

        return array($lines, $heights);
    }

    private function _render_index($lines, $heights, $opt) {

        // try to get a realistic column height, based on all headers
        $col_height = array_sum($heights) / $opt['cols'];
        $height = current($heights);
        $prev_was_link = true;
        $links = '';

        $width = floor(100 / $opt['cols']);

        // now render the subject index table

        $noborder_css = ' class="noborder" ';
        $border_style = ($opt['border'] == 'outside' || $opt['border'] == 'both') ? '' : $noborder_css;
        $top_id = 'top-' . mt_rand();  // fixed point to jump back to at top of the table
        $render = '<div id="subjectindex"' . $border_style . '>' . DOKU_LF;
        $render .= '<table id="' . $top_id . '">' . DOKU_LF;

        foreach ($lines as $line) {
            // are we ready to start a new column? (up to max allowed)
            if ($col == 0 || ( ! $new_col && ($col < $opt['cols'] && $cur_height > $col_height))) {
                $cur_height = 0;
                $new_col = true;
                $col++;
            }
            // new column, starts only at headings not at page links (for visual clarity)
            if ($new_col && $prev_was_link) {
                $border_style = ($opt['border'] == 'inside' || $opt['border'] == 'both') ? '' : $noborder_css;
                // close the previous column if necessary
                $close = ($idx > 0) ? '</td>' . DOKU_LF : '';
                $render .= $close . '<td' . $border_style . ' valign="top"   width="' . $width . '%">' . DOKU_LF;
                $new_col = false;
            }
            // render each entry line
            list($lvl, $cur_level, $pages, $anchor) = $line;

            // remove the ordering number from the entry if requested
            if ( ! $opt['showorder']) {
                $matched = preg_match('/^\d+\.(.+)/', $cur_level, $matches);
                if ($matched > 0) $cur_level = $matches[1];
            }
            $indent_css = ' style="margin-left:' . ($lvl - 1) * 10 . 'px"';
            $entry = "<h$lvl$indent_css";
            // render page links
            if ( ! empty($pages)) {
                $cnt = 0;
                $freq = '';
                foreach($pages as $page) {
                    if ( ! empty($links)) $links .= ' | ';
                    $links .= $this->_render_wikilink($page, $opt['proper'], $opt['title'], $opt['abstract'], $anchor);
                    $cnt++;
                }
                if ($cnt > 1) $freq = '<span class="frequency">' . count($pages) . '</span>';
                $anchor = ' id="' . $anchor . '"';
                $entry .= "$anchor>$cur_level$freq" . '<span class="links">' . "$links</span></h$lvl>";
                $prev_was_link = true;
                $links = '';
            // render headings
            } else {
                $entry .= ">$cur_level</h$lvl>";
                $prev_was_link = false;
            }
            $render .= $entry . DOKU_LF;

            $cur_height += $height;
            $height = next($heights);
        }
        $render .= '</td></table>' . DOKU_LF;
        $render .= '<a class="top" href="#' . $top_id . '">' . $this->getLang('link_to_top') . '</a>';
        $render .= '</div>' . DOKU_LF;
        return $render;
    }

    private function _split_entry($entry) {
        list($text, $pid) = explode('|', $entry);
        // remove the index section number
        list($_, $text) = explode('/', $text, 2);
        return array($text, $pid);
    }

    /**
     * Renders a complete page link, plus tooltip, abstract, casing, etc...
     * @param string $id
     * @param bool  $proper
     * @param bool  $title
     * @param mixed $abstract
     */
    private function _render_wikilink($id, $proper, $title, $abstract, $anchor) {

        $id = (strpos($id, ':') === false) ? ':' . $id : $id;   // : needed for root pages

        // does the user want to see the "title" instead "pagename"
        if ($title) {
            $value = p_get_metadata($id, 'title', true);
            $name = (empty($value)) ? $this->_proper(noNS($id)) : $value;
        } elseif ($proper) {
            $name = $this->_proper(noNS($id));
        } else {
            $name = '';
        }

        // show the "abstract" as a tooltip
        $link = html_wikilink($id, $name);
        $link = $this->_add_page_anchor($link, $anchor);
        if ($abstract) {
            $link = $this->_add_tooltip($link, $id);
        }
        return $link;
    }

    private function _proper($id) {
         $id = str_replace(':', ': ', $id);
         $id = str_replace('_', ' ', $id);
         $id = ucwords($id);
         $id = str_replace(': ', ':', $id);
         return $id;
    }

    /**
     * swap normal link title (popup) for a more useful preview
     *
     * @param string $id    page id
     * @param string $name  display name
     * @return complete href link
     */
    private function _add_tooltip($link, $id) {
        $tooltip = $this->_abstract($id);
        if (!empty($tooltip)) {
            $tooltip = str_replace("\n", '  ', $tooltip);
            $link = preg_replace('/title=\".+?\"/', 'title="' . $tooltip . '"', $link, 1);
        }
        return $link;
    }

    private function _abstract($id) {
        $meta = p_get_metadata($id, 'description abstract', true);
        return htmlspecialchars($meta, ENT_IGNORE, 'UTF-8');
    }

    private function _add_page_anchor($link, $anchor) {
        $link = preg_replace('/\" class/', '#' . $anchor . '" class', $link, 1);
        return $link;
    }
}