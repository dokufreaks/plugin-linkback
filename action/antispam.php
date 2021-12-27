<?php

/**
 * Basic antispam features for the DokuWiki Linkback Plugin.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

class action_plugin_linkback_antispam extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers.
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_LINKBACK_RECEIVED', 'BEFORE', $this, 'handle_linkback_received', array ());
    }

    /**
     * Handler for the ACTION_LINKBACK_RECEIVED event.
     */
    function handle_linkback_received(Doku_Event $event, $param) {
        $linkback = $event->data['trackback_data'];
        $page = $event->data['page'];
        $target = $event->data['target'];

        if ($this->getConf('antispam_linkcount_enable') && !$this->_clean_linkcount($linkback['raw_excerpt'])) {
            $event->data['log'][] = "\tLinkcount exceeded, marked as spam";
            $event->data['show'] = false;
            if (!$this->getConf('antispam_linkcount_moderate'))
                $event->preventDefault();
            else
                $event->data['log'][] = "\t -> moderated";
        } else {
            $event->data['log'][] = "\tLinkcount ok, marked as ham";
        }

        if ($this->getConf('antispam_wordblock_enable') && !$this->_clean_wordblock($linkback['raw_excerpt'])) {
            $event->data['log'][] = "\tWordblock active, marked as spam";
            $event->data['show'] = false;
            if (!$this->getConf('antispam_wordblock_moderate'))
                $event->preventDefault();
            else
                $event->data['log'][] = "\t -> moderated";
        } else {
            $event->data['log'][] = "\tWordblock ok, marked as ham";
        }

        if ($this->getConf('antispam_host_enable') && !$this->_clean_host($linkback['url'], $linkback['submitter_ip'])) {
            $event->data['log'][] = "\tHosts do not match, marked as spam";
            $event->data['show'] = false;
            if (!$this->getConf('antispam_host_moderate'))
                $event->preventDefault();
            else
                $event->data['log'][] = "\t -> moderated";
        } else {
            $event->data['log'][] = "\tHosts ok, marked as ham";
        }

        if ($this->getConf('antispam_link_enable') && !$this->_clean_link($target, $page, $linkback['type'])) {
            $event->data['log'][] = "\tURL not contained in linking page, marked as spam";
            $event->data['show'] = false;
            if (!$this->getConf('antispam_link_moderate'))
                $event->preventDefault();
            else
                $event->data['log'][] = "\t -> moderated";
        } else {
            $event->data['log'][] = "\tURL found in linking page, marked as ham";
        }

        return;
    }

    /**    
     * Check against linkcount limit.
     */
    function _clean_linkcount($excerpt) {
        $regex = '!<a\s.*?</a>!is';
        if (preg_match($regex, $excerpt) > $this->getConf('antispam_linkcount_max'))
            return false;
        return true;
    }

    /**
     * Check against wordblock.
     */
    function _clean_wordblock($excerpt) {
        global $TEXT;

        $otext = $TEXT;
        $TEXT = $excerpt;
        $retval = checkwordblock();
        $TEXT = $otext;

        return !$retval;
    }

    /**
     * Check whether source host matches requesting host.
     */
    function _clean_host($sourceUri, $remote_addr) {
        $urlparts = parse_url($sourceUri);
        $source_addr = gethostbyname($urlparts['host']);
        return ($source_addr == $remote_addr);
    }
    
    /**
     * Check whether linking page contains link to us.
     * 
     * Only used for trackbacks (pingbacks get this treatment right on arrival
     * for excerpt extraction anyway...)
     */
    function _clean_link($targetUri, $page, $type) {
        if ($type == 'pingback')
            return true;

        $searchurl = preg_quote($targetUri, '!');
        $regex = '!<a[^>]+?href="' . $searchurl . '"[^>]*?>(.*?)</a>!is';
        $regex2 = '!\s(' . $searchurl . ')\s!is';
        if (!preg_match($regex, $page['body'], $match) && !preg_match($regex2, $page['body'], $match)) {
            if ($this->getConf('ping_internal') && (strstr($targetUri, DOKU_URL) == $targetUri)) {
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
        
        return true;
    }
}
