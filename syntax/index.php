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
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN . 'syntax.php');
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
        // allow for multi-line syntax (clearer when writing many options)
        $this->Lexer->addSpecialPattern('\{\{subjectindex>(?m).*?(?-m)\}\}', $mode, 'plugin_subjectindex_index');
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
        $opt['label']       = '';        // table header label at top
        $opt['regex']       = null;      // a regex for filtering the index list
        $opt['hidejump']    = false;     // hide the 'jump to top' link

        // remove any trailing spaces caused by multi-line syntax
        $args = explode(';', $match);
        $args = array_map('trim', $args);

        foreach ($args as $arg) {
            list($key, $value) = explode('=', $arg);
            $key = strtolower($key);
            switch ($key) {
                case 'abstract':
                case 'default':
                case 'hideatoz':
                case 'proper':
                case 'showorder':
                case 'showcount':
                case 'hidejump':
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
                    $opt['section'] = ($value < 0) ? 0 : $value;
                    break;
                case 'label':
                case 'regex':
                    $opt[$key] = $value;
                    break;
                default:
            }
        }
        // update the list of default target pages for entry links
        if ($opt['default'] === true) {
            SI_Utils::set_target_page($ID, $opt['section']);
        }
        return $opt;
    }


    function render($mode, &$renderer, $opt) {
        if ($mode == 'xhtml') {
            $renderer->info['cache'] = false;

            require_once (DOKU_INC . 'inc/indexer.php');
            $all_pages = idx_getIndex('page', '');

            $all_entries = SI_Utils::get_index();
            if ($all_entries->is_empty()) {
                $renderer->doc .= $this->getLang('empty_index');
                return false;
            }

            // grab items for chosen index section only
            $section_entries = $all_entries->filtered($opt['section'], $opt['regex']);
            $count = count($section_entries->paths);
            $lines = $this->_create_index($section_entries, $all_pages, $opt['hideatoz'], $opt['proper']);
            $renderer->doc .= $this->_render_index($lines, $opt, $count);
            return true;
        } else {
            return false;
        }
    }


    // first build a list of valid subject entries to be rendered
    private function _create_index(SI_Index $section_entries, $all_pages, $hideAtoZ, $proper) {

        $lines = array();
        $links = array();
        $prev_path = '';

        list($next_entry, $next_pid) = $section_entries->current();

        do {

            $entry = $anchor = $next_entry;
            $pid = $next_pid;

            // cache the next entry for comparison purposes later
            list($next_entry, $next_pid) = $section_entries->next();

            // remove any trailing whitespace which could falsify the later comparison
            $page = rtrim($all_pages[$pid], "\n\r");

            // skip to next page if it is not valid: exists, accessible, permitted
            if ( ! SI_Utils::is_valid_page($page)) {
                continue;
            }

            // note: all comparisons are case-less
            // (this is an A-Z index after all humans don't distinguish between case when searching)
            // Need to do this check BEFORE adding the A-Z headings below, because $entry is modified
            $next_differs = strcasecmp($entry, $next_entry) !== 0;

            // Create the A-Z heading
            if ( ! $hideAtoZ) {
                $matches = array();
                $matched = preg_match('/(^\d+\.)?(.).+/', $entry, $matches);    // check for ordered entries 1st
                if ($matched > 0) {
                    $entry = $matches[1] . strtoupper($matches[2]) . '/' . $entry;
                } else {
                    $entry = strtoupper($entry[0]) . '/' . $entry;
                }
            }

            $cur_node = strtok($entry, '/');
            $cur_path = '';
            $heading = 1;    // html heading number 1-6

            do {
                $is_heading = $is_link = false;

                // build headers by adding each node
                $cur_path .= (empty($cur_path)) ? $cur_node : '/' . $cur_node;

                // we can add the page link(s) only if this is the final level;
                // links take priority over headings!
                $next_node = strtok('/');
                if ($next_node === false) {
                    $links[] = $page;
                    $is_link = true;
                // we only make headings if they are completely different from the previous
                } elseif (strpos($prev_path, $cur_path) !== 0) {
                    $is_heading = true;
                }

                //  the next_differs check ensures that links will be grouped
                if (($is_link && $next_differs) || $is_heading) {
                    if ($proper) {
                        $cur_node = ucwords($cur_node);
                    }
                    if ($is_link) {
                        $anchor = SI_Utils::valid_id($anchor);
                        $lines[] = array($heading, $cur_node, $links, $anchor);
                        $links = array();
                    } else {
                        $lines[] = array($heading, $cur_node, '' ,'');
                    }
                }
                // forgive the magic no's = html h1 to h6 is fixed anyway
                $heading = ($heading > 5) ? 6 : $heading + 1;
                $cur_node = $next_node;

            } while ($next_node !== false);

            $prev_path = $entry;
        } while ($section_entries->valid());

        return $lines;
    }


    private function _render_index($lines, $opt, $count){
        $links = '';
        $label = '';
        $show_count = '';
        $show_jump = '';

        // now render the subject index table

        $outer_border = ($opt['border'] == 'outside' || $opt['border'] == 'both') ? 'border' : '';
        $inner_border = ($opt['border'] == 'inside' || $opt['border'] == 'both') ? 'inner-border' : '';

        // fixed point to jump back to at top of the table
        $top_id = 'top-' . mt_rand();

        if ($opt['label'] != '') {
            $label = '<h1 class="title">' . $opt['label'] . '</h1>' . DOKU_LF;
        }

        // optional columns width adjustments
        if ($count > SUBJ_IDX_HONOUR_COLS) {
            $cols = $opt['cols'];
        } else {
            $cols = 1;
        }
        if (is_numeric($cols)) {
            $col_style = 'column-count:' . $cols . '; -moz-column-count:' . $cols . '; -webkit-column-count:' . $cols . ';';
        } else {
            $col_style = 'column-width:' . $cols . '; -moz-column-width:' . $cols . '; -webkit-column-width:' . $cols . ';';
        }

        if ($opt['showcount'] === true) {
            $show_count = '<div class="count">' . $count . ' ∞</div>' . DOKU_LF;
        }
        if ($opt['hidejump'] === false) {
            $show_jump = '<a class="jump" href="#' . $top_id . '">' . $this->getLang('link_to_top') . '</a>' . DOKU_LF;
        }

        $subjectindex = '';
        foreach ($lines as $line) {

            // grab each entry line
            list($heading, $cur_node, $pages, $anchor) = $line;

            // remove the ordering number from the entry if requested
            if ( ! $opt['showorder']) {
                $matched = preg_match('/^\d+\.(.+)/', $cur_node, $matches);
                if ($matched > 0) {
                    $cur_node = $matches[1];
                }
            }
            $indent_style = 'margin-left:' . ($heading - 1) * 10 . 'px';
            $entry = '<h' . $heading . ' style="' . $indent_style . '"';

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
                $entry .= $anchor . '>' . $cur_node . $freq . '<span class="links">' . $links . '</span></h' . $heading . '>';

                $links = '';

            // render headings
            } else {
                $entry .= '>' . $cur_node . '</h' . $heading . '>';
            }
            $subjectindex .= $entry . DOKU_LF;
        }

        // actual rendering to wiki page
        $render = '<div class="subjectindex ' . $outer_border . '" id="' . $top_id . '">' . DOKU_LF;
        $render .= $label . DOKU_LF;;
        $render .= '<div class="inner ' . $inner_border . '" style="' . $col_style . '">' . DOKU_LF;;
        $render .= $subjectindex;
        $render .= $show_count . $show_jump;
        $render .= '</div></div>' . DOKU_LF;
        return $render;
    }


    /**
     * Renders a complete page link, plus tooltip, abstract, casing, etc...
     *
     * @param string $id
     * @param bool $proper
     * @param bool $title
     * @param mixed $abstract
     * @param string $anchor
     * @return string
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
     * Swap normal link title (popup) for a more useful preview
     *
     * @param string $link  display name
     * @param string $id    page id
     * @return string
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
        $meta = \p_get_metadata($id, 'description abstract', true);
        $meta = ( ! empty($meta)) ? htmlspecialchars($meta, ENT_NOQUOTES, 'UTF-8') : '';
        return $meta;
    }


    private function _add_page_anchor($link, $anchor) {
        $link = preg_replace('/\" class/', '#' . $anchor . '" class', $link, 1);
        return $link;
    }
}