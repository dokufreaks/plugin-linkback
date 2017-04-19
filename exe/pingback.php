<?php

/**
 * Pingback server for use with the DokuWiki Linkback Plugin.
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
require_once (DOKU_INC . 'inc/IXR_Library.php');
require_once (DOKU_INC . 'inc/pluginutils.php');
require_once (DOKU_PLUGIN . 'linkback/tools.php');
require_once (DOKU_PLUGIN . 'linkback/http.php');

// Pingback Faultcodes
define('PINGBACK_ERROR_GENERIC', 0);
define('PINGBACK_ERROR_SOURCEURI_DOES_NOT_EXIST', 16);
define('PINGBACK_ERROR_SOURCEURI_DOES_NOT_CONTAIN_LINK', 17);
define('PINGBACK_ERROR_TARGETURI_DOES_NOT_EXIST', 32);
define('PINGBACK_ERROR_TARGETURI_CANNOT_BE_USED', 33);
define('PINGBACK_ERROR_PINGBACK_ALREADY_MADE', 48);
define('PINGBACK_ERROR_ACCESS_DENIED', 49);
define('PINGBACK_ERROR_NO_UPSTREAM', 50);

class PingbackServer extends IXR_Server {

    // helper instance
    var $tools;

    /**
     * Register service and construct helper
     */
    function __construct() {
        $this->tools =& plugin_load('tools', 'linkback');
        parent::__construct(array (
            'pingback.ping' => 'this:ping',
            
        ));
    }

    function ping($sourceUri, $targetUri) {
        // Plugin not enabled? Quit
        if (plugin_isdisabled('linkback'))
            return new IXR_Error(PINGBACK_ERROR_TARGETURI_CANNOT_BE_USED, '');

        // pingback disabled? Quit
        if (!$this->tools->getConf('enable_pingback'))
            return new IXR_Error(PINGBACK_ERROR_TARGETURI_CANNOT_BE_USED, '');

        // Given URLs are no urls? Quit
        if (!preg_match("#^([a-z0-9\-\.+]+?)://.*#i", $sourceUri))
            return new IXR_Error(PINGBACK_ERROR_GENERIC, '');
        if (!preg_match("#^([a-z0-9\-\.+]+?)://.*#i", $targetUri))
            return new IXR_Error(PINGBACK_ERROR_GENERIC, '');

        // Source URL does not exist? Quit
        $page = $this->tools->getPage($sourceUri);
        if (!$page['success'] && ($page['status'] < 200 || $page['status'] >= 300))
            return new IXR_Error(PINGBACK_ERROR_SOURCEURI_DOES_NOT_EXIST, '');

        // Target URL does not match with request? Quit
        $ID = substr($_SERVER['PATH_INFO'], 1);
        if ($targetUri != wl($ID, '', true))
            return new IXR_Error(PINGBACK_ERROR_GENERIC, '');

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

        // Target URL is not pingback enabled? Quit
        if (!$data['receive'])
            return new IXR_Error(PINGBACK_ERROR_TARGETURI_CANNOT_BE_USED, '');

        // Pingback already done? Quit
        if ($data['receivedpings'][md5($sourceUri)])
            return new IXR_Error(PINGBACK_ERROR_PINGBACK_ALREADY_MADE, '');

        // Retrieve data from source
        $linkback = $this->_getTrackbackData($sourceUri, $targetUri, $page);

        // Source URL does not contain link to target? Quit
        if (!$linkback)
            return new IXR_Error(PINGBACK_ERROR_SOURCEURI_DOES_NOT_CONTAIN_LINK, '');

        // Prepare event for Antispam plugins
        $evt_data = array (
            'linkback' => $linkback,
            'page' => $page,
            'target' => $targetUri,
            'show' => true,
            'log' => array(
            	date('Y/m/d H:i', time()) . ': Received pingback from ' . $linkback['url'] . ' (' . $linkback['lid'] . ')',
            ),
        );
        $event = new Doku_Event('ACTION_LINKBACK_RECEIVED', $evt_data);
        if ($event->advise_before()) {
            $linkback['show'] = $evt_data['show'];
            if ($this->tools->getConf('usefavicon')) {
                $linkback['favicon'] = $this->tools->getFavicon($linkback['url'], $page['body']);
            }

            // add pingback			
            $data['receivedpings'][$linkback['lid']] = $linkback;
            if ($linkback['show'])
                $data['number']++;

            io_saveFile($file, serialize($data));
            $this->tools->addLogEntry($linkback['received'], $ID, 'cl', '', $linkback['lid']);
            $this->tools->notify($ID, $linkback);
            if ($this->tools->getConf('log_processing'))
                $this->tools->addProcessLogEntry($evt_data['log']);
            $event->advise_after();
        } else {
            // Pingback was denied
            if ($this->tools->getConf('log_processing'))
                $this->tools->addProcessLogEntry($evt_data['log']);
            $event->advise_after();
            return new IXR_Error(PINGBACK_ERROR_ACCESS_DENIED, $helper->getLang('error_noreason'));
        }
    }

    /**
     * Constructs linkback data and checks if source contains a link to target and a title.
     */
    function _getTrackbackData($sourceUri, $targetUri, $page) {
        // construct unique id for pingback
        $lid = md5($sourceUri);
        $linkback = array (
            'lid' => $lid,
            'title' => '',
            'url' => $sourceUri,
            'excerpt' => '',
            'raw_excerpt' => '',
            'blog_name' => '',
            'received' => time(), 
            'submitter_ip' => $_SERVER['REMOTE_ADDR'], 
            'submitter_useragent' => $_SERVER['HTTP_USER_AGENT'], 
            'submitter_referer' => $_SERVER['HTTP_REFERER'], 
            'type' => 'pingback', 
            'show' => true,
        );

        $searchurl = preg_quote($targetUri, '!');
        $regex = '!<a[^>]+?href="' . $searchurl . '"[^>]*?>(.*?)</a>!is';
        $regex2 = '!\s(' . $searchurl . ')\s!is';
        if (!preg_match($regex, $page['body'], $match) && !preg_match($regex2, $page['body'], $match)) {
            if ($this->tools->getConf('ping_internal') && (strstr($targetUri, DOKU_URL) == $targetUri)) {
                $ID = substr($_SERVER['PATH_INFO'], 1);
                $searchurl = preg_quote(wl($ID, '', false), '!');

                $regex = '!<a[^>]+?href="' . $searchurl . '"[^>]*?>(.*?)</a>!is';
                $regex2 = '!\s(' . $searchurl . ')\s!is';
                if (!preg_match($regex, $page['body'], $match) && !preg_match($regex2, $page['body'], $match))
                    return false;
            } else {
                return false;
            }
        }
        $linkback['raw_excerpt'] = '[...] ' . $match[1] . ' [...]';
        $linkback['excerpt'] = '[...] ' . strip_tags($match[1]) . ' [...]';

        $regex = '!<title.*?>(.*?)</title>!is';
        if (!preg_match($regex, $page['body'], $match))
            return false;
        $linkback['title'] = strip_tags($match[1]);

        return $linkback;
    }

}

$server = new PingbackServer();
