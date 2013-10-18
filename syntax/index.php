<?php
/**
 * Part of Subject Index plugin:
 *
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author	   Symon Bent <hendrybadao@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');


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
        $opt['abstract']    = true;      // show snippet (abstract) of page content
        $opt['border']      = 'none';    // show borders around table and between columns
        $opt['cols']        = 1;         // number of columns in a SubjectIndex display page (max=12)
        $opt['default']     = false;     // whether this display index page is the default for this index section number
        $opt['hideatoz']    = false;     // turn off the A,B,C main headings
        $opt['proper']      = false;     // use proper-case for page names
        $opt['title']       = false;     // use title (first heading) instead of page name
        $opt['section']     = 0;         // which section to use and display (0-9)...hopefully 10 is enough
        $opt['showorder']   = false;     // display any bullet numbers used for ordering
        $opt['label']       = '';

        $args = explode(';', $match);
        foreach ($args as $arg) {
            list($key, $value) = explode('=', $arg);
            $key = strtolower($key);
            switch ($key) {
                case 'abstract':
                case 'default':
                case 'hideatoz':
                case 'proper':
                case 'showorder':
                case 'title':
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
                case 'label':
                    $opt['label'] = $value;
                    break;
                default:
            }
        }
        // update the list of default target pages for entry links
        if ($opt['default'] === true) {
            SubjectIndex::set_target_page($ID, $opt['section']);
        }
        return $opt;
    }


    function render($mode, &$renderer, $opt) {
        if ($mode == 'xhtml') {
            $renderer->info['cache'] = false;
            $subject_idx = file(SubjectIndex::get_index($this->getConf('subjectindex_data_dir')));
            if (empty($subject_idx)) {
                $renderer->doc .= $this->getLang('empty_index');
                return false;
            }
            require_once (DOKU_INC . 'inc/indexer.php');
            $page_idx = idx_getIndex('page', '');

            $lines = $this->_create_index($subject_idx, $page_idx, $opt['section'], $opt['hideatoz'], $opt['proper']);
            $renderer->doc .= $this->_render_index($lines, $opt);
        } else {
            return false;
        }
    }


    private function _create_index($subject_idx, $page_idx, $section, $hideAtoZ, $proper) {

        $lines = array();
        $links = array();

        // grab only items for chosen index section
        $subject_idx = preg_grep('/^' . $section . '.+/', $subject_idx);

        // first build a list of valid subject entries to be rendered, plus their heights
        list($next_entry, $next_pid) = $this->_split_entry(current($subject_idx));
        $prev_entry = '';

        do {
            $entry = $next_entry;
            $pid = $next_pid;

            // cache the next entry for comparison purposes later
            $next = next($subject_idx);
            if ($next !== false) {
                list($next_entry, $next_pid) = $this->_split_entry($next);
            } else {
                $next_entry = '';
                $next_pid = '';
            }

            $page = rtrim($page_idx[intval($pid)], "\n\r");

            // skip to next page if it is not valid: exists, accessible, permitted
            if ( ! SubjectIndex::valid_page($page)) {
                continue;
            }

            // note: all comparisons are caseless (this is an A-Z index after all, humans don't distinguish when searching)
            // Check for this BEFORE  adding the A-Z headings below!
            $next_differs = strcasecmp($entry, $next_entry) !== 0;

            // Create the A-Z heading
            if ( ! $hideAtoZ) {
                $matches = array();
                $matched = preg_match('/(^\d+\.)?(.).+/', $entry, $matches);    // check for ordered entries 1
                if ($matched > 0) {
                    $entry = $matches[1] . strtoupper($matches[2]) . '/' . $entry;
                } else {
                    $entry = strtoupper($entry[0]) . '/' . $entry;
                }
            }

            $cur_level = strtok($entry, '/');
            $cur_entry = '';
            $hlevel = 1;    // html heading number 1-6

            do {
                $next_level = strtok('/');
                $cur_entry .= (empty($cur_entry)) ? $cur_level : '/' . $cur_level;  // current heading state

                $is_heading = $is_link = false;
                // we can add the page link(s) only if this is the final level;
                // links take priority over headings!
                if ($next_level === false) {
                    $links[] = $page;
                    $is_link = true;
                // we only make headings if they are completetly different from the previous
                } elseif (strpos($prev_entry, $cur_entry) !== 0) {
                    $is_heading = true;
                }

                //  the next_differs check ensures that links will be grouped
                if (($is_link && $next_differs) || $is_heading) {
                    if ($proper) {
                        $cur_level = ucwords($cur_level);
                    }
                    if ($is_link) {
                        $anchor = SubjectIndex::clean_id($entry);
                        $lines[] = array($hlevel, $cur_level, $links, $anchor);
                        $links = array();
                    } else {
                        $lines[] = array($hlevel, $cur_level, '' ,'');
                    }
                }
                $hlevel = ($hlevel > 5) ? 6 : $hlevel + 1;  // forgive the magic no's = html h1 to h6 is fixed anyway
                $cur_level = $next_level;
            } while ($next_level !== false);

            $prev_entry = $entry;
        } while ($next !== false);

        return $lines;
    }


    private function _split_entry($entry) {
        $_ = null;
        list($text, $pid) = explode('|', $entry);
        // remove the index section number
        list($_, $text) = explode('/', $text, 2);
        return array($text, $pid);
    }


    private function _render_index($lines, $opt){
        $prev_was_link = true;
        $links = '';

        // now render the subject index table

        $outer_border = ($opt['border'] == 'outside' || $opt['border'] == 'both') ? 'border' : '';
        $inner_border = ($opt['border'] == 'inside' || $opt['border'] == 'both') ? 'inner-border' : '';

        // fixed point to jump back to at top of the table
        $top_id = 'top-' . mt_rand();

        if ($opt['label'] != '') {
            $label = '<h1 class="title">' . $opt['label'] . '</h1>' . DOKU_LF;
        }

        // optional columns width adjustments
        $cols = $opt['cols'];
        if (is_numeric($cols)) {
            $col_style = 'column-count:' . $cols . '; -moz-column-count:' . $cols . '; -webkit-column-count:' . $cols . ';';
        } else {
            $col_style = 'column-width:' . $cols . '; -moz-column-width:' . $cols . '; -webkit-column-width:' . $cols . ';';
        }

        $render = '<div class="subjectindex ' . $outer_border . '" id="' . $top_id . '">' . DOKU_LF;
        $render .= $label;
        $render .= '<div class="inner ' . $inner_border . '" style="' . $col_style . '">';

        foreach ($lines as $line) {

            // grab each entry line
            list($lvl, $cur_level, $pages, $anchor) = $line;

            // remove the ordering number from the entry if requested
            if ( ! $opt['showorder']) {
                $matched = preg_match('/^\d+\.(.+)/', $cur_level, $matches);
                if ($matched > 0) {
                    $cur_level = $matches[1];
                }
            }
            $indent_style = 'margin-left:' . ($lvl - 1) * 10 . 'px';
            $entry = '<h' . $lvl . ' style="' . $indent_style . '"';

            // render page links
            if ( ! empty($pages)) {
                $cnt = 0;
                $freq = '';
                foreach($pages as $page) {
                    if ( ! empty($links)) {
                        $links .= ' | ';
                    }
                    $links .= $this->_render_wikilink($page, $opt['proper'], $opt['title'], $opt['abstract'], $anchor);
                    $cnt++;
                }
                if ($cnt > 1) {
                    $freq = '<span class="frequency">' . count($pages) . '</span>';
                }
                $anchor = ' id="' . $anchor . '"';
                $entry .= $anchor . '>' . $cur_level . $freq . '<span class="links">' . $links . '</span></h' . $lvl . '>';

                $prev_was_link = true;
                $links = '';

            // render headings
            } else {
                $entry .= '>' . $cur_level . '</h' . $lvl . '>';
                $prev_was_link = false;
            }
            $render .= $entry . DOKU_LF;
        }
        $render .= '<a class="top" href="#' . $top_id . '">' . $this->getLang('link_to_top') . '</a>';
        $render .= '</div></div>' . DOKU_LF;
        return $render;
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

        $link = html_wikilink($id, $name);
        $link = $this->_add_page_anchor($link, $anchor);
        // show the "abstract" as a tooltip
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
        $tooltip = $this->_get_abstract($id);
        if (!empty($tooltip)) {
            $tooltip = str_replace("\n", '  ', $tooltip);
            $link = preg_replace('/title=\".+?\"/', 'title="' . $tooltip . '"', $link, 1);
        }
        return $link;
    }


    private function _get_abstract($id) {
        $meta = p_get_metadata($id, 'description abstract', true);
        $meta = ( ! empty($meta)) ? htmlspecialchars($meta, ENT_NOQUOTES, 'UTF-8') : '';
        return $meta;
    }


    private function _add_page_anchor($link, $anchor) {
        $link = preg_replace('/\" class/', '#' . $anchor . '" class', $link, 1);
        return $link;
    }
}