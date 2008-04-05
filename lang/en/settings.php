<?php

/**
 * english language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */
 
$lang['enable_pingback'] = 'Enable pingbacks.';
$lang['enable_trackback'] = 'Enable trackbacks.';
$lang['order'] = 'Order in which to try to send linkbacks.';
$lang['range'] = 'How many kilobytes to fetch from linked page for autodiscovery.';
$lang['allow_guests'] = 'Allow non-registered users to send linkbacks.';
$lang['enabled_namespaces'] = 'Namespaces where the sending of linkbacks should be enabled by default (comma-separated list, * enables linkbacks by default everywhere).';
$lang['ping_internal'] = 'Also ping internal links e.g. for crossreferencing former blog entries.';
$lang['show_trackback_url'] = 'Show trackback URL on enabled sites.';
$lang['log_processing'] = 'Log processing of incoming linkbacks (Log will be called linkback.log and be located in the cache-dir).';

$lang['usefavicon'] = 'Retrieve favicon for incoming linkbacks and display it.';
$lang['favicon_default'] = 'URL of favicon to use if pinging page does not have one.';

$lang['antispam_linkcount_enable'] = 'Enable linkcount antispam measure.';
$lang['antispam_linkcount_moderate'] = 'If allowed linkcount exceeds, moderate linkback instead of deleting it.';
$lang['antispam_linkcount_max'] = 'Maximum number of links to allow in excerpt without taking action.';

$lang['antispam_wordblock_enable'] = 'Enable wordblock antispam measure.';
$lang['antispam_wordblock_moderate'] = 'If linkback contains blacklisted words, moderate linkback instead of deleting it.';

$lang['antispam_host_enable'] = 'Enable host antispam measure.';
$lang['antispam_host_moderate'] = 'If hostname of sending site and remote address of the connection do not match, moderate linkback instead of deleting it.';

$lang['antispam_link_enable'] = 'Enable link antispam measure.';
$lang['antispam_link_moderate'] = 'If sending site does not contain a link to us, moderate linkback instead of deleting it.';

$lang['akismet_enable'] = 'Enable Akismet antispam measure.';
$lang['akismet_apikey'] = 'The <a href="http://wordpress.com/signup/">Akismet API Key</a>. The plugin will not work without!';
$lang['akismet_moderate'] = 'If Akismet classifies linkback as spam, moderate it instead of deleting it.';
