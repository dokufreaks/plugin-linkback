<?php

/**
 * Linkback Plugin
 *
 * Enables/disables linkback features.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 */

if (!defined('DOKU_INC'))
    die();
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_linkback extends DokuWiki_Syntax_Plugin {

    /**
     * return some info
     */
    function getInfo() {
        return array (
            'author' => 'Gina Haeussge',
            'email' => 'osd@foosel.net',
            'date' => '2007-04-12',
            'name' => 'Linkback Plugin',
            'desc' => 'Enables/disables linkback features.',
            'url' => 'http://wiki.foosel.net/snippets/dokuwiki/linkback',
        );
    }

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
    function handle($match, $state, $pos, & $handler) {
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

    function render($mode, & $renderer, $status) {
        // do nothing, everything is handled in the action components
        return false;
    }

}