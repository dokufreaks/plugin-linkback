<?php


/**
 * Linkback helper plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */

class helper_plugin_linkback extends DokuWiki_Plugin {

    function getMethods() {
        $result = array ();
        $result[] = array (
            'name' => 'th',
            'desc' => 'returns the header of the linkback column for pagelist',
            'return' => array (
                'header' => 'string'
            ),
        );
        $result[] = array (
            'name' => 'td',
            'desc' => 'returns the link to the linkback section with number of comments',
            'params' => array (
                'id' => 'string',
                'number of linkbacks (optional)' => 'integer'
            ),
            'return' => array (
                'link' => 'string'
            ),
        );
        $result[] = array (
            'name' => 'getLinkbacks',
            'desc' => 'returns recently added linkbacks individually',
            'params' => array (
                'namespace' => 'string',
                'number (optional)' => 'integer'
            ),
            'return' => array (
                'pages' => 'array'
            ),
        );
        return $result;
    }

    /**
     * Returns the header of the linkback column for the pagelist
     */
    function th() {
        return $this->getLang('linkbacks');
    }

    /**
     * Returns the link to the linkback section with number of comments
     */
    function td($ID, $number = NULL) {
        $section = '#linkback__section';

        if (!isset ($number)) {
            $lfile = metaFN($ID, '.linkbacks');
            $linkbacks = unserialize(io_readFile($lfile, false));

            $number = $linkbacks['number'];
            if (!$linkbacks['display'])
                return '';
        }

        if ($number == 0)
            $linkback = '0&nbsp;' . $this->getLang('linkback_plural');
        elseif ($number == 1) $linkback = '1&nbsp;' . $this->getLang('linkback_singular');
        else
            $linkback = $number . '&nbsp;' . $this->getLang('linkback_plural');

        return '<a href="' . wl($ID) . $section . '" class="wikilink1" title="' . $ID . $section . '">' .
        $linkback . '</a>';

    }

    /**
     * Returns recently added linkbacks individually
     */
    function getLinkbacks($ns, $num = NULL) {
        global $conf;

        $first = $_REQUEST['first'];
        if (!is_numeric($first))
            $first = 0;

        if ((!$num) || (!is_numeric($num)))
            $num = $conf['recent'];

        $result = array ();
        $count = 0;

        if (!@ file_exists($conf['metadir'] . '/_linkbacks.changes'))
            return $result;

        // read all recent changes. (kept short)
        $lines = file($conf['metadir'] . '/_linkbacks.changes');

        // handle lines
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $rec = $this->_handleRecentLinkback($lines[$i], $ns);
            if ($rec !== false) {
                if (-- $first >= 0)
                    continue; // skip first entries
                $result[$rec['date']] = $rec;
                $count++;
                // break when we have enough entries
                if ($count >= $num)
                    break;
            }
        }

        // finally sort by time of last comment
        krsort($result);

        return $result;
    }

    /**
     * Internal function used by $this->getLinkbacks()
     *
     * don't call directly
     *
     * @see getRecentComments()
     * @author Andreas Gohr <andi@splitbrain.org>
     * @author Ben Coburn <btcoburn@silicodon.net>
     * @author Esther Brunner <wikidesign@gmail.com>
     * @author Gina Haeussge <osd@foosel.net>
     */
    function _handleRecentLinkback($line, $ns) {
        static $seen = array (); //caches seen pages and skip them
        if (empty ($line))
            return false; //skip empty lines

        // split the line into parts
        $recent = parseChangelogLine($line);
        if ($recent === false)
            return false;

        $lid = $recent['extra'];
        $fulllid = $recent['id'] . '#' . $recent['extra'];

        // skip seen ones
        if (isset ($seen[$fulllid]))
            return false;

        // skip 'show comment' log entries
        if ($recent['type'] === 'sc')
            return false;

        // remember in seen to skip additional sights
        $seen[$fulllid] = 1;

        // check if it's a hidden page or comment
        if (isHiddenPage($recent['id']))
            return false;
        if ($recent['type'] === 'hl')
            return false;

        // filter namespace or id
        if (($ns) && (strpos($recent['id'] . ':', $ns . ':') !== 0))
            return false;

        // check ACL
        $recent['perm'] = auth_quickaclcheck($recent['id']);
        if ($recent['perm'] < AUTH_READ)
            return false;

        // check existance
        $recent['file'] = wikiFN($recent['id']);
        $recent['exists'] = @ file_exists($recent['file']);
        if (!$recent['exists'])
            return false;
        if ($recent['type'] === 'dc')
            return false;

        // get linkback meta file name
        $data = unserialize(io_readFile(metaFN($recent['id'], '.linkbacks'), false));

        // check if discussion is turned off
        if (!$data['display'])
            return false;

        // okay, then add some additional info
        $recent['name'] = $data['receivedpings'][$lid]['url'];
        $recent['desc'] = $data['receivedpings'][$lid]['excerpt'];

        return $recent;
    }

}
