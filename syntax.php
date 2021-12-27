<?php

/**
 * Linkback Plugin
 *
 * Enables/disables linkback features.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 */

class syntax_plugin_linkback extends DokuWiki_Syntax_Plugin {

    function getType() {
        return 'substition';
    }
    function getPType() {
        return 'block';
    }
    function getSort() {
        return 333;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        if ($mode == 'base') {
            $this->Lexer->addSpecialPattern('~~LINKBACK(?:|:off|:closed)~~', $mode, 'plugin_linkback');
        }
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;
        global $ACT;

        // don't show linkback section on blog mainpages
        if (defined('IS_BLOG_MAINPAGE'))
            return false;

        // don't allow usage of syntax in comments
        if (isset($_REQUEST['comment']))
            return false;

        // get linkback meta file name
        $file = metaFN($ID, '.linkbacks');

        $data = array (
            'send' => false,
            'receive' => false,
            'display' => false,
            'sentpings' => array (),
            'receivedpings' => array (),
            'number' => 0,
            
        );
        if (@ file_exists($file))
            $data = unserialize(io_readFile($file, false));
        if ($match == '~~LINKBACK~~') {
            $data['receive'] = true;
            $data['display'] = true;
        } else
            if ($match == '~~LINKBACK:off~~') {
                $data['receive'] = false;
                $data['display'] = false;
            } else {
                $data['receive'] = false;
                $data['display'] = true;
            }
        io_saveFile($file, serialize($data));
    }

    function render($mode, Doku_Renderer $renderer, $status) {
        // do nothing, everything is handled in the action components
        return true;
    }

}
