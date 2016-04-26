<?php

/**
 * Display component of the DokuWiki Linkback action plugin. Highly influenced by
 * the discussion plugin of Esther Brunner.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @link       http://wiki.foosel.net/snippets/dokuwiki/linkback
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'action.php');

require_once (DOKU_INC . 'inc/common.php');
require_once (DOKU_INC . 'inc/events.php');
require_once (DOKU_PLUGIN . 'linkback/tools.php');

if (!defined('NL'))
    define('NL', "\n");

class action_plugin_linkback_display extends DokuWiki_Action_Plugin {

    /**
     * A little helper.
     */
    var $tools;

    /**
     * Constructor
     */
    function action_plugin_linkback_display() {
        $this->tools =& plugin_load('tools', 'linkback');
    }

    /**
     * Register the eventhandlers.
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this, 'handle_act_render', array ());
        $controller->register_hook('RENDERER_CONTENT_POSTPROCESS', 'AFTER', $this, 'handle_content_postprocess', array ());
    }

    /**
     * Handler for the TPL_ACT_RENDER event
     */
    function handle_act_render(Doku_Event $event, $params) {
        global $ID, $INFO;
        
        if ($event->data != 'show')
            return;

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

        if (!isset ($_REQUEST['linkback']) || $INFO['perm'] != AUTH_ADMIN)
            $_REQUEST['linkback'] = 'linkback_show';

        $lid = $_REQUEST['lid'];
        if (!preg_match('![a-zA-Z0-9]!', $lid))
            $_REQUEST['linkback'] = 'linkback_show';

        switch ($_REQUEST['linkback']) {
            // toggle show/hide
            case 'linkback_toggle' :
                $linkback = $data['receivedpings'][$lid];
                $data = $this->_changeLinkbackVisibilities(array($lid), !$linkback['show']);
                break;
            // delete linkback
            case 'linkback_delete' :
            	$data = $this->_deleteLinkbacks(array($lid));
                break;
                // report linkback as ham
            case 'linkback_ham' :
            	$this->_markLinkbacks(array($lid), false);
                break;
                // report linkback as spam
            case 'linkback_spam' :
            	$this->_markLinkbacks(array($lid), true);
                break;
        }

        $this->_show($data);
    }

    /**
     * Shows all linkbacks for the current page
     */
    function _show($data) {
        global $ID;

        if (!$data['display'])
            return;

        if ((count($data['receivedpings']) == 0) && 
                (!$this->getConf('show_trackback_url') || !$this->getConf('enable_trackback')))
            return;

        // section title
        $title = $this->getLang('linkbacks');
        echo '<div class="linkback_wrapper">';
        echo '<h2><a name="linkback__section" id="linkback__section">' . $title . '</a></h2>';
        if ($this->getConf('show_trackback_url') && $data['receive']) {
            echo '<div class="level2 hfeed linkback_trackbackurl">';
            echo $this->getLang('trackback_url');
            echo '<span class="linkback_trackbackurl">' . DOKU_URL . 'lib/plugins/linkback/exe/trackback.php/' . $ID . '</span>';
            echo '</div>';
        }
        echo '<div class="level2 hfeed">';

        // now display the comments
        if (isset ($data['receivedpings'])) {
            foreach ($data['receivedpings'] as $key => $ping) {
                $this->_print($key, $ping);
            }
        }

        echo '</div>'; // level2
        echo '</div>'; // comment_wrapper

        return true;
    }

    /**
     * Prints an individual linkback
     */
    function _print($lid, $linkback, $visible = true) {
        global $ID;
        global $INFO;
        global $conf;

        if (!is_array($linkback))
            return false; // corrupt datatype

        if (!$linkback['show']) { // linkback hidden
            if ($INFO['perm'] == AUTH_ADMIN)
                echo '<div class="linkback_hidden">' . NL;
            else
                return true;
        }

        $title = $linkback['title'];
        $url = $linkback['url'];
        $date = $linkback['received'];
        $excerpt = $linkback['excerpt'];
        $icon = $linkback['favicon'];
        $type = $linkback['type'];

        // linkback head with date and link
        echo '<div class="hentry"><div class="linkback_head">' . NL .
        '<a name="linkback__' . $lid . '" id="linkback__' . $lid . '"></a>' . NL .
        '<span class="sender">';

        // show favicon image
        if ($this->getConf('usefavicon')) {
            if (!$icon)
                $icon = $this->getConf('favicon_default');

            $size = 16;
            $src = ml($icon);
            echo '<img src="' . $src . '" class="medialeft photo" title="' . $url . '"' .
            ' width="' . $size . '" height="' . $size . '" />' . NL;
            $style = ' style="margin-left: ' . ($size +14) . 'px;"';
        } else {
            $style = ' style="margin-left: 20px;"';
        }

        echo $this->external_link($url, ($linkback['blog_title'] ? $linkback['blog_title'] . ': ' : '') . $title, 'urlextern url fn');
        echo '</span>, <abbr class="received" title="' . gmdate('Y-m-d\TH:i:s\Z', $date) .
        '">' . strftime($conf['dformat'], $date) . '</abbr>';
        echo ' (<abbr class="type" title="' . $this->getLang('linkback_type_' . $type) . '">' . $this->getLang('linkback_type_' . $type) . '</abbr>)';
        echo NL . '</div>' . NL; // class="linkback_head"

        // main linkback content
        if (strlen($excerpt) > 0) {
            echo '<div class="linkback_body entry-content"' .
             ($this->getConf('usefavicon') ? $style : '') . '>' . NL .
            $excerpt . NL . '</div>' . NL; // class="linkback_body"
        }
        echo '</div>' . NL; // class="hentry"

        echo '<div class="linkback_buttons">' . NL;
        if ($INFO['perm'] == AUTH_ADMIN) {
            if (!$linkback['show'])
                $label = $this->getLang('btn_show');
            else
                $label = $this->getLang('btn_hide');

            $this->_button($lid, $label, 'linkback_toggle');
            $this->_button($lid, $this->getLang('btn_ham'), 'linkback_ham');
            $this->_button($lid, $this->getLang('btn_spam'), 'linkback_spam');
            $this->_button($lid, $this->getLang('btn_delete'), 'linkback_delete');
        }
        echo '</div>';

        echo '<div class="linkback_line" ' . ($this->getConf('usefavicon') ? $style : '') . '>&nbsp;</div>' . NL;

        if (!$linkback['show'])
            echo '</div>' . NL; // class="linkback_hidden"
    }

    /**
     * General button function. 
     * 
     * Code mostly taken from the discussion plugin by Esther Brunner.
     */
    function _button($lid, $label, $act) {
        global $ID;
?>
    <form class="button" method="post" action="<?php echo script() ?>">
      <div class="no">
        <input type="hidden" name="id" value="<?php echo $ID ?>" />
        <input type="hidden" name="do" value="show" />
        <input type="hidden" name="linkback" value="<?php echo $act ?>" />
        <input type="hidden" name="lid" value="<?php echo $lid ?>" />
        <input type="submit" value="<?php echo $label ?>" class="button" title="<?php echo $label ?>" />
      </div>
    </form>
    <?php


        return true;
    }

    /**
     * Adds a TOC entry for the linkback section.
     * 
     * Code mostly taken from the discussion plugin by Esther Brunner.
     */
    function handle_content_postprocess(Doku_Event $event, $params) {
        global $ID;
        global $conf;

        if ($event->data[0] != 'xhtml')
            return; // nothing to do for us

        $file = metaFN($ID, '.linkbacks');
        if (!@ file_exists($file))
            return false;
        $data = unserialize(io_readFile($file, false));
        if (!$data['display'] || (count($data['receivedpings']) == 0))
            return; // no linkback section

        $pattern = '/<div id="toc__inside">(.*?)<\/div>\s<\/div>/s';
        if (!preg_match($pattern, $event->data[1], $match))
            return; // no TOC on this page

        $title = $this->getLang('linkbacks');
        $section = '#linkback__section';
        $level = 3 - $conf['toptoclevel'];

        $item = '<li class="level' . $level . '"><div class="li"><span class="li"><a href="' .
        $section . '" class="toc">' . $title . '</a></span></div></li>';

        if ($level == 1)
            $search = "</ul>\n</div>";
        else
            $search = "</ul>\n</li></ul>\n</div>";

        $new = str_replace($search, $item . $search, $match[0]);
        $event->data[1] = preg_replace($pattern, $new, $event->data[1]);
    }
    
    function _changeLinkbackVisibilities($lids, $visible) {
        global $ID;

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
        $update = false;
            
        foreach ($lids as $lid) {
            $linkback = $data['receivedpings'][$lid];
            if ($linkback['show'] == $visible) 
                continue;

            $linkback['show'] = $visible;
            if ($linkback['show'])
                $data['number']++;
            else
                $data['number']--;
            $data['receivedpings'][$lid] = $linkback;
            $this->tools->addLogEntry($linkback['received'], $ID, (($linkback['show']) ? 'sl' : 'hl'), '', $linkback['lid']);
            $update = true;
        }

        if ($update)
            io_saveFile($file, serialize($data));
        return $data;
    }

    function _deleteLinkbacks($lids) {
        global $ID;

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
        $update = false;

        foreach ($lids as $lid) {
            $linkback = $data['receivedpings'][$lid];
            unset ($data['receivedpings'][$lid]);
            if ($linkback['show'])
                $data['number']--;
            $this->tools->addLogEntry($linkback['received'], $ID, 'dl', '', $linkback['lid']);
            $update = true;
        }

        if ($update)
            io_saveFile($file, serialize($data));
        return $data;
    }
    
    function _markLinkbacks($lids, $isSpam) {
        global $ID;

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
            
        foreach ($lids as $lid) {
            $linkback = $data['receivedpings'][$lid];
            if ($isSpam)
                trigger_event('ACTION_LINKBACK_SPAM', $linkback);
            else
                trigger_event('ACTION_LINKBACK_HAM', $linkback);
        }
    }
    
}
