<?php

/**
 * Internal helper functions for use with the DokuWiki Linkback Plugin.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

if (!defined('DOKU_LF'))
    define('DOKU_LF', "\n");
if (!defined('DOKU_TAB'))
    define('DOKU_TAB', "\t");

require_once (DOKU_INC . 'inc/init.php');
require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/mail.php');
require_once (DOKU_PLUGIN . 'linkback/http.php');

class tools_plugin_linkback extends DokuWiki_Plugin {

    function DokuWiki_Linkback_Interface() {
        $this->DokuWiki_Plugin();
    }

    /**
     * Retrieves a favicon for the given page.
     */
    function getFavicon($url, $page) {
        $urlparts = parse_url($url);

        $regex = '!<link rel="(shortcut )?icon" href="([^"]+)" ?/?>!';
        if (preg_match($regex, $page, $match)) {
            $icon = $match[2];
            if (!preg_match("#^(http://)?[^/]+#i", $icon)) {
                $icon = $urlparts['scheme'] . '://' . $urlparts['host'] . (($icon[0]) == '/' ? '' : '/') . $icon;
            }
            return $icon;
        }

        $icon = $urlparts['scheme'] . '://' . $urlparts['host'] . '/favicon.ico';

        $http_client = new LinkbackHTTPClient();
        $http_client->sendRequest($icon, array (), 'HEAD');
        if ($http_client->status == 200)
            return $icon;
        else
            return $this->getConf('favicon_default');
    }

    /**
     * Retrieves $conf['range']kB of $url and returns headers and retrieved body.
     */
    function getPage($url) {
        $range = $this->getConf('range') * 1024;

        $http_client = new LinkbackHTTPClient();
        $http_client->headers['Range'] = 'bytes=0-' . $range;
        $http_client->max_bodysize = $range;
        $http_client->max_bodysize_limit = true;

        $retval = $http_client->get($url, true);

        return array (
            'success' => $retval,
            'headers' => $http_client->resp_headers,
            'body' => $http_client->resp_body,
            'error' => $http_client->error,
            'status' => $http_client->status,
        );
    }

    /**
     * Sends a notify mail on new linkback
     *
     * @param  string $ID       id of the wiki page for which the
     *                          linkback was received 
     * @param  array  $comment  data array of the new linkback
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Esther Brunner <wikidesign@gmail.com>
     * @author Gina Haeussge <osd@foosel.net>
     */
    function notify($ID, $linkback) {
        global $conf;

        if (!$conf['notify'])
            return;
        $to = $conf['notify'];
        $text = io_readFile($this->localFN('subscribermail'));

        $search = array (
            '@PAGE@',
            '@TITLE@',
            '@DATE@',
            '@URL@',
            '@TEXT@',
            '@UNSUBSCRIBE@',
            '@DOKUWIKIURL@',
            '@PAGEURL@',
            
        );
        $replace = array (
            $ID,
            $conf['title'],
            strftime($conf['dformat'], $linkback['received']), 
            $linkback['url'], 
            $linkback['excerpt'], 
            wl($ID, 'do=unsubscribe', true, '&'), 
            DOKU_URL,
            wl($ID, '', true), 
        );
        $text = str_replace($search, $replace, $text);

        $subject = '[' . $conf['title'] . '] ' . $this->getLang('mail_newlinkback');

        mail_send($to, $subject, $text, $conf['mailfrom'], '');

    }

    /**
     * Adds an entry to the linkbacks changelog
     *
     * @author Esther Brunner <wikidesign@gmail.com>
     * @author Ben Coburn <btcoburn@silicodon.net>
     * @author Gina Haeussge <osd@foosel.net>
     */
    function addLogEntry($date, $id, $type = 'cl', $summary = '', $extra = '') {
        global $conf;

        $changelog = $conf['metadir'] . '/_linkbacks.changes';

        if (!$date)
            $date = time(); //use current time if none supplied
        $remote = $_SERVER['REMOTE_ADDR'];
        $user = $_SERVER['REMOTE_USER'];

        $strip = array (
            "\t",
            "\n"
        );
        $logline = array (
            'date' => $date,
            'ip' => $remote,
            'type' => str_replace($strip,
            '',
            $type
        ), 'id' => $id, 'user' => $user, 'sum' => str_replace($strip, '', $summary), 'extra' => str_replace($strip, '', $extra));

        // add changelog line
        $logline = implode("\t", $logline) . "\n";
        io_saveFile($changelog, $logline, true); //global changelog cache
        $this->_trimRecentCommentsLog($changelog);
    }

    /**
     * Trims the recent comments cache to the last $conf['changes_days'] recent
     * changes or $conf['recent'] items, which ever is larger.
     * The trimming is only done once a day.
     *
     * @author Ben Coburn <btcoburn@silicodon.net>
     */
    function _trimRecentCommentsLog($changelog) {
        global $conf;

        if (@ file_exists($changelog) && (filectime($changelog) + 86400) < time() && !@ file_exists($changelog .
            '_tmp')) {

            io_lock($changelog);
            $lines = file($changelog);
            if (count($lines) < $conf['recent']) {
                // nothing to trim
                io_unlock($changelog);
                return true;
            }

            io_saveFile($changelog . '_tmp', ''); // presave tmp as 2nd lock
            $trim_time = time() - $conf['recent_days'] * 86400;
            $out_lines = array ();

            $linecount = count($lines);
            for ($i = 0; $i < $linecount; $i++) {
                $log = parseChangelogLine($lines[$i]);
                if ($log === false)
                    continue; // discard junk
                if ($log['date'] < $trim_time) {
                    $old_lines[$log['date'] . ".$i"] = $lines[$i]; // keep old lines for now (append .$i to prevent key collisions)
                } else {
                    $out_lines[$log['date'] . ".$i"] = $lines[$i]; // definitely keep these lines
                }
            }

            // sort the final result, it shouldn't be necessary,
            // however the extra robustness in making the changelog cache self-correcting is worth it
            ksort($out_lines);
            $extra = $conf['recent'] - count($out_lines); // do we need extra lines do bring us up to minimum
            if ($extra > 0) {
                ksort($old_lines);
                $out_lines = array_merge(array_slice($old_lines, - $extra), $out_lines);
            }

            // save trimmed changelog
            io_saveFile($changelog . '_tmp', implode('', $out_lines));
            @ unlink($changelog);
            if (!rename($changelog . '_tmp', $changelog)) {
                // rename failed so try another way...
                io_unlock($changelog);
                io_saveFile($changelog, implode('', $out_lines));
                @ unlink($changelog . '_tmp');
            } else {
                io_unlock($changelog);
            }
            return true;
        }
    }
    
    function addProcessLogEntry($data) {
        global $conf;
    
        io_saveFile($conf['cachedir'].'/linkback.log',join("\n",$data)."\n\n",true);
    }

}
