<?php

/**
 * Akismet plugin for use with the Linkback Plugin for DokuWiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'action.php');

require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/infoutils.php');

class action_plugin_linkback_akismet extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_LINKBACK_RECEIVED', 'BEFORE', $this, 'handle_linkback_received', array ());
        $controller->register_hook('ACTION_LINKBACK_HAM', 'AFTER', $this, 'handle_linkback_ham', array ());
        $controller->register_hook('ACTION_LINKBACK_SPAM', 'AFTER', $this, 'handle_linkback_spam', array ());
    }

    /**
     * Handler for the ACTION_LINKBACK_RECEIVED event
     */
    function handle_linkback_received(Doku_Event $event, $param) {
        $linkback = $event->data['linkback'];
        
        $tools =& plugin_load('tools', 'linkback');

        if (!$this->getConf('akismet_enable') || !$this->getConf('akismet_apikey'))
            return;

        $data = $this->_prepareData($linkback);
        if ($this->_checkForSpam($data)) {
            $event->data['log'][] = "\tAkismet marked linkback as spam";
            $event->data['show'] = false;
            if (!$this->getConf('akismet_moderate'))
                $event->preventDefault();
            else
            	$event->data['log'][] = "\t -> moderated";
        } else {
        	$event->data['log'][] = "\tAkismet marked linkback as ham";
        }

        return;
    }

    /**
     * Handler for the ACTION_LINKBACK_HAM event
     */
    function handle_linkback_ham(Doku_Event $event, $params) {
        $linkback = $event->data['linkback'];

        if (!$this->getConf('akismet_enabled') || !$this->getConf('akismet_apikey'))
            return;

        $data = $this->_prepareData($linkback);
        $this->_reportHam($data);
    }

    /**
     * Handler for the ACTION_LINKBACK_SPAM event
     */
    function handle_linkback_spam(Doku_Event $event, $params) {
        $linkback = $event->data['linkback'];

        if (!$this->getConf('akismet_enabled') || !$this->getConf('akismet_apikey'))
            return;

        $data = $this->_prepareData($linkback);
        $this->_reportSpam($data);
    }

    /**
     * Submit the data to the Akismet comment-check webservice and return whether it was
     * classified as spam (true) or ham (false)
     */
    function _checkForSpam($data) {
        $resp = $this->_submitData('comment-check', $data);
        if ($resp == 'true')
            return true;
        return false;
    }

    /**
     * Submit the data to the Akismet submit-ham webservice
     */
    function _reportHam($data) {
        $this->_submitData('submit-ham', $data);
    }

    /**
     * Submit the data to the Akismet submit-spam webservice
     */
    function _reportSpam($data) {
        $this->_submitData('submit-spam', $data);
    }

    /**
     * Prepares the data to send to Akismet
     */
    function _prepareData($linkback) {
        $data = array (
            'blog' => DOKU_URL,
            'user_ip' => $linkback['submitter_ip'],
            'user_agent' => $linkback['submitter_useragent'],
            'referrer' => $linkback['submitter_referer'],
            'comment_author_url' => $linkback['url'],
            'comment_type' => $linkback['type'],
            'comment_content' => $linkback['raw_excerpt'],
        );

        return $data;
    }

    /**
     * Submits the given data to the given Akismet service.
     * 
     * @param $function string  Akismet service to use. Can be 
     *                          'comment-check', 'submit-ham' or 
     *                          'submit-spam'
     * @param $data     array   Linkback data to submit
     * @return          string  The response of Akismet 
     */
    function _submitData($function, $data) {
        $info = $this->getInfo();
        $http = new DokuHTTPClient();
        // The Aksimet guys ask for a verbose UserAgent:
        $http->agent = 'DokuWiki/' . getVersion() . ' | ' . $info['name'] . '/' . $info['date'];
        $http->timeout = 5;
        $resp = $http->post('http://' . $this->getConf('akismet_apikey') . '.rest.akismet.com/1.1/comment-check', $data);
        return $resp;
    }

}
