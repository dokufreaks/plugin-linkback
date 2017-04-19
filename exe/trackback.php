<?php

/**
 * Trackback server for use with the DokuWiki Linkback Plugin.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../../') . '/');

if (!defined('NL'))
    define('NL', "\n");

require_once (DOKU_INC . 'inc/init.php');
require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/events.php');
require_once (DOKU_INC . 'inc/pluginutils.php');
require_once (DOKU_PLUGIN . 'linkback/tools.php');
require_once (DOKU_PLUGIN . 'linkback/http.php');

class TrackbackServer {

    // helper instance
    var $tools;

    /**
     * Construct helper and process request.
     */
    function __construct() {
        $this->tools =& plugin_load('tools', 'linkback');
        $this->_process();
    }

    /**
     * Process trackback request.
     */
    function _process() {
        
        // Plugin not enabled? Quit
        if (plugin_isdisabled('linkback')) {
            $this->_printTrackbackError('Trackbacks disabled.');
            return;
        }

        // Trackbacks not enabled? Quit
        if (!$this->tools->getConf('enable_trackback')) {
            $this->_printTrackbackError('Trackbacks disabled.');
            return;
        }
            

        // No POST request? Quit
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->_printTrackbackError('Trackback was not received via HTTP POST.');
            return;
        }

        // get ID
        $ID = substr($_SERVER['PATH_INFO'], 1);

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

        // target is not trackback-enabled? Quit
        if (!$data['receive']) {
            $this->_printTrackbackError('Trackbacks not enabled for this resource.');
            return;
        }

        // construct unique id for trackback
        $lid = md5($_REQUEST['url']);
        $linkback = array (
            'lid' => $lid,
            'title' => strip_tags($_REQUEST['title']), 
            'url' => $_REQUEST['url'], 
            'excerpt' => strip_tags($_REQUEST['excerpt']),  
            'raw_excerpt' => $_REQUEST['excerpt'],
            'blog_name' => strip_tags($_REQUEST['blog_name']), 
            'received' => time(), 
            'submitter_ip' => $_SERVER['REMOTE_ADDR'], 
            'submitter_useragent' => $_SERVER['HTTP_USER_AGENT'], 
            'submitter_referer' => $_SERVER['HTTP_REFERER'], 
            'type' => 'trackback',  
            'show' => true,
        );
        $log = array(
            date('Y/m/d H:i', time()) . ': Received trackback from ' . $linkback['url'] . ' (' . $linkback['lid'] . ')',
        );

        // Given URL is not an url? Quit
        if (!preg_match("#^([a-z0-9\-\.+]+?)://.*#i", $linkback['url'])) {
        $log[] = "\tTrackback URL is invalid";
            if ($this->tools->getConf('log_processing'))
                $this->tools->addProcessLogEntry($log);
            $this->_printTrackbackError('Given trackback URL is not an URL.');
            return;
        }

        // Trackback already done? Quit
        if ($data['receivedpings'][$lid]) {
        $log[] = "\tTrackback already received";
            if ($this->tools->getConf('log_processing'))
                $this->tools->addProcessLogEntry($log);
            $this->_printTrackbackError('Trackback already received.');
            return;
        }

        // Source does not exist? Quit
        $page = $this->tools->getPage($linkback['url']);
        if (!$page['success'] && ($page['status'] < 200 || $page['status'] >= 300)) {
        $log[] = "\tLinked page cannot be reached, status " .$page['status'];
            if ($this->tools->getConf('log_processing'))
                $this->tools->addProcessLogEntry($log);
            $this->_printTrackbackError('Linked page cannot be reached ('.$page['error'].').');
            return;
        }

        // Prepare event for Antispam plugins
        $evt_data = array (
            'linkback' => $linkback,
            'page' => $page,
            'target' => wl($ID, '', true),
            'show' => true,
            'log' => $log,
        );
        $event = new Doku_Event('ACTION_LINKBACK_RECEIVED', $evt_data);
        if ($event->advise_before()) {
            $linkback['show'] = $evt_data['show'];
            if ($this->tools->getConf('usefavicon')) {
                $linkback['favicon'] = $this->tools->getFavicon($linkback['url'], $page['body']);
            }

            // add trackback
            $data['receivedpings'][$lid] = $linkback;
            if ($linkback['show'])
                $data['number']++;

            io_saveFile($file, serialize($data));
            $this->tools->addLogEntry($linkback['received'], $ID, 'cl', '', $linkback['lid']);
            $this->tools->notify($ID, $linkback);
            $this->_printTrackbackSuccess();
        } else {
            $this->_printTrackbackError('Trackback denied: Spam.');
        }
        if ($this->tools->getConf('log_processing'))
        	$this->tools->addProcessLogEntry($evt_data['log']);
        $event->advise_after();
    }

    /**
     * Print trackback success xml.
     */
    function _printTrackbackSuccess() {
        echo '<?xml version="1.0" encoding="iso-8859-1"?>' . NL .
        '<response>' . NL .
        '<error>0</error>' . NL .
        '</response>';
    }

    /**
     * Print trackback error xml.
     */
    function _printTrackbackError($reason = '') {
        echo '<?xml version="1.0" encoding="iso-8859-1"?>' . NL .
        '<response>' . NL .
        '<error>1</error>' . NL .
        '<message>' . $reason . '</message>' . NL .
        '</response>';
    }

}

$server = new TrackbackServer();
