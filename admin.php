<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'admin.php');
 
class admin_plugin_linkback extends DokuWiki_Admin_Plugin {
 
  function getInfo(){
    return array(
      'author' => 'Gina Häußge',
      'email'  => 'osd@foosel.net',
      'date' => @file_get_contents(DOKU_PLUGIN.'linkback/VERSION'),
      'name'   => 'Linkback Plugin (admin component)',
      'desc'   => 'Moderate linkbacks',
      'url'    => 'http://foosel.net/snippets/dokuwiki/linkback',
    );
  }

  function getMenuSort(){ return 201; }
  function forAdminOnly(){ return false; }
  
  function handle(){
    global $lang;
    
    $lid = $_REQUEST['lid'];
    if (is_array($lid)) $lid = array_keys($lid);
    
    $action =& plugin_load('action', 'linkback_display');
    if (!$action) return; // couldn't load action plugin component
    
    switch ($_REQUEST['linkback']){
      case $lang['btn_delete']:
        $action->_deleteLinkbacks($lid);
        break;
        
      case $this->getLang('btn_show'):
        $action->_changeLinkbackVisibilities($lid, true);
        break;
        
      case $this->getLang('btn_hide'):
        $action->_changeLinkbackVisibilities($lid, false);
        break;
        
      case $this->getLang('btn_ham'):
      	$action->_markLinkbacks($lid, false);
      	break;
        
      case $this->getLang('btn_spam'):
      	$action->_markLinkbacks($lid, true);
      	break;
        
      case $this->getLang('btn_change'):
        $this->_changeStatus($_REQUEST['status']);
        break;
    }
  }

  function html(){
    global $conf;
    
    $first = $_REQUEST['first'];
    if (!is_numeric($first)) $first = 0;
    $num = $conf['recent'];
    
    ptln('<h1>'.$this->getLang('menu').'</h1>');
        
    $targets = $this->_getTargets();
    
    // slice the needed chunk of linkback targets
    $more = ((count($targets) > ($first + $num)) ? true : false);
    $targets = array_slice($targets, $first, $num);
    
    foreach ($targets as $target){
      $linkbacks = $this->_getLinkbacks($target);
      $this->_targetHead($target);
      if ($linkbacks === false){
        ptln('</div>', 6); // class="level2"
        continue;
      }
      
      ptln('<form method="post" action="'.wl($target['id']).'">', 8);
      ptln('<div class="no">', 10);
      ptln('<input type="hidden" name="do" value="admin" />', 10);
      ptln('<input type="hidden" name="page" value="linkback" />', 10);
      echo html_buildlist($linkbacks, 'admin_linkback', array($this, '_linkbackItem'), array($this, '_li_linkback'));
      $this->_actionButtons($target['id']);
    }
    $this->_browseLinkbackLinks($more, $first, $num);
    
  }
  
  /**
   * Returns an array of targets with linkback features, sorted by recent linkbacks
   */
  function _getTargets(){
    global $conf;
    
    require_once(DOKU_INC.'inc/search.php');
            
    // returns the list of pages in the given namespace and it's subspaces
    $items = array();
    search($items, $conf['datadir'], 'search_allpages', array());
            
    // add pages with comments to result
    $result = array();
    foreach ($items as $item){
      $id = $item['id'];
      
      // some checks
      $file = metaFN($id, '.linkbacks');
      if (!@file_exists($file)) continue; // skip if no comments file
      
      $date = filemtime($file);
      $result[] = array(
        'id'   => $id,
        'file' => $file,
        'date' => $date,
      );
    }
    
    // finally sort by time of last comment
    usort($result, array('admin_plugin_linkback', '_targetCmp'));
          
    return $result;
  }
  
  /**
   * Callback for comparison of target data. 
   * 
   * Used for sorting targets in descending order by date of last linkback. 
   * If this date happens to be equal for the compared targest, page id 
   * is used as second comparison attribute.
   */
  function _targetCmp($a, $b) {
  	if ($a['date'] == $b['date']) {
        return strcmp($a['id'], $b['id']);
    }
    return ($a['date'] < $b['date']) ? 1 : -1;
  }
  
  /**
   * Outputs header, page ID and status of linkbacks
   */
  function _targetHead($target){
    $id = $target['id'];
    
    $labels = array(
      'send' => $this->getLang('send'),
      'receive' => $this->getLang('receive'),
      'display' => $this->getLang('display')
    );
    $title = p_get_metadata($id, 'title');
    if (!$title) $title = $id;
    ptln('<h2 name="'.$id.'" id="'.$id.'">'.hsc($title).'</h2>', 6);
    ptln('<form method="post" action="'.wl($id).'">', 6);
    ptln('<div class="mediaright">', 8);
    ptln('<input type="hidden" name="do" value="admin" />', 10);
    ptln('<input type="hidden" name="page" value="linkback" />', 10);
    ptln($this->getLang('status').': ', 10);
    foreach ($labels as $key => $label){
      $selected = (($target[$key]) ? ' checked="checked"' : '');
      ptln('<label for="status_'.$id.'_'.$key.'">'.$label.'</label>&nbsp;<input type="checkbox" name="status['.$key.']" value="1"'.$selected.' id="status_'.$id.'_'.$key.'" />&nbsp;&nbsp;', 12);
    }
    ptln('<input type="submit" name="linkback" value="'.$this->getLang('btn_change').'" class"button" title="'.$this->getLang('btn_change').'" />', 10);
    ptln('</div>', 8);
    ptln('</form>', 6);
    ptln('<div class="level2">', 6);
    ptln('<a href="'.wl($id).'" class="wikilink1">'.$id.'</a> ', 8);
    return true;
  }
  
  /**
   * Returns the full comments data for a given wiki page
   */
  function _getLinkbacks(&$target){
    $id = $target['id'];
    
    if (!$target['file']) $target['file'] = metaFN($id, '.linkbacks');
    if (!@file_exists($target['file'])) return false; // no discussion thread at all
    
    $data = unserialize(io_readFile($target['file'], false));
    
    $target['send'] = $data['send'];
    $target['receive'] = $data['receive'];
    $target['display'] = $data['display'];
    $target['number'] = $data['number'];
    
    if (!$data['display']) return false;   // comments are turned off
    if (!$data['receivedpings']) return false; // no comments

    $result = array();
    foreach($data['receivedpings'] as $lid => $linkback) {
    	$linkback['level'] = 1;
    	$result[$lid] = $linkback;
    }
    
    if (empty($result)) return false;
    else return $result;
  }
  
  /**
   * Checkbox and info about a linkback item
   */
  function _linkbackItem($linkback){
    global $conf;
  
    // prepare variables
    $title = $linkback['title'];
    $url = $linkback['url'];
    $date = $linkback['received'];
    $excerpt = $linkback['excerpt'];
    $type = $linkback['type'];
        
    if (utf8_strlen($excerpt) > 160) $excerpt = utf8_substr($excerpt, 0, 160).'...';

    return '<input type="checkbox" name="lid['.$linkback['lid'].']" value="1" /> '.
      $this->external_link($url, $title).', '.strftime($conf['dformat'], $date).', ' . $this->getLang('linkback_type_' . $type) . ': '.
      '<span class="excerpt">'.$excerpt.'</span>';
  }
  
  /**
   * list item tag
   */
  function _li_linkback($linkback){
    if (!$linkback['show'])
    	return '<li class="hidden">';
    return '<li>';
  }
  
  /**
   * Show buttons to bulk remove, hide or show comments
   */
  function _actionButtons($id){
    ptln('<div class="linkback_buttons">', 12);
    ptln('<input type="submit" name="linkback" value="'.$this->getLang('btn_show').'" class="button" title="'.$this->getLang('btn_show').'" />', 14);
    ptln('<input type="submit" name="linkback" value="'.$this->getLang('btn_hide').'" class="button" title="'.$this->getLang('btn_hide').'" />', 14);
    ptln('<input type="submit" name="linkback" value="'.$this->getLang('btn_ham').'" class="button" title="'.$this->getLang('btn_ham').'" />', 14);
    ptln('<input type="submit" name="linkback" value="'.$this->getLang('btn_spam').'" class="button" title="'.$this->getLang('btn_spam').'" />', 14);
    ptln('<input type="submit" name="linkback" value="'.$this->getLang('btn_delete').'" class="button" title="'.$this->getLang('btn_delete').'" />', 14);
    ptln('</div>', 12); // class="comment_buttons"
    ptln('</div>', 10); // class="no"
    ptln('</form>', 8);
    ptln('</div>', 6); // class="level2"
    return true;
  }
  
  /**
   * Displays links to older newer discussions
   */
  function _browseLinkbackLinks($more, $first, $num){
    global $ID;
    
    if (($first == 0) && (!$more)) return true;
    
    $params = array('do' => 'admin', 'page' => 'linkback');
    $last = $first+$num;
    ptln('<div class="level1">', 8);
    if ($first > 0){
      $first -= $num;
      if ($first < 0) $first = 0;
      $params['first'] = $first;
      ptln('<p class="centeralign">', 8);
      $ret = '<a href="'.wl($ID, $params).'" class="wikilink1">&lt;&lt; '.$this->getLang('newer').'</a>';
      if ($more){
        $ret .= ' | ';
      } else {
        ptln($ret, 10);
        ptln('</p>', 8);
      }
    } else if ($more){
      ptln('<p class="centeralign">', 8);
    }
    if ($more){
      $params['first'] = $last;
      $ret .= '<a href="'.wl($ID, $params).'" class="wikilink1">'.$this->getLang('older').' &gt;&gt;</a>';
      ptln($ret, 10);
      ptln('</p>', 8);
    }
    ptln('</div>', 6); // class="level1"
    return true;
  }
    
  /**
   * Changes the status of a comment
   */
  function _changeStatus($new){
    global $ID;
    
    // get discussion meta file name
    $file = metaFN($ID, '.linkbacks');
    $data = unserialize(io_readFile($file, false));
    
    $updated = false;
    $updateText = false;
    foreach (array('send', 'receive', 'display') as $key) {
    	if (!isset($new[$key]))
    		$new[$key] = false;
    	
    	if ($new[$key] == $data[$key])
    		continue;
    		
    	$data[$key] = $new[$key];
    	$updated = true;
    	
    	if (in_array($key, array('send', 'receive')))
    		$updateText = true;
    }
    
    if (!$updated)
    	return false;
    
    // save the comment metadata file
    io_saveFile($file, serialize($data));
    
    if (!$updateText)
    	return true;
    	
    // look for ~~LINKBACK~~ command in page file and change it accordingly
    if ($data['receive'] && $data['display'])
    	$replace = '~~LINKBACK~~';
    else if (!$data['receive'])
    	$replace = '~~LINKBACK:closed~~';
    else 
    	$replace = '~~LINKBACK:off~~';
    $wiki = preg_replace('/~~LINKBACK([\w:]*)~~/', $replace, rawWiki($ID));
    saveWikiText($ID, $wiki, $this->getLang('statuschanged'), true);
    
    return true;
  }
    	
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
