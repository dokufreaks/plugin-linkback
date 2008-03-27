<?php

/**
 * Options for the Linkback Plugin
 */

// Basic options
$conf['enable_pingback'] = true;
$conf['enable_trackback'] = true;
$conf['order'] = 'pingback, trackback';
$conf['range'] = 20;
$conf['allow_guests'] = false;
$conf['enabled_namespaces'] = 'blog';
$conf['ping_internal'] = true;
$conf['show_trackback_url'] = false;
$conf['log_processing'] = true;

// Favicon
$conf['usefavicon'] = true;
$conf['favicon_default'] = DOKU_URL . 'lib/plugins/linkback/images/favicon.gif';

// Linkcount antispam
$conf['antispam_linkcount_enable'] = true;
$conf['antispam_linkcount_moderate'] = false;
$conf['antispam_linkcount_max'] = 5;

// Wordblock antispam
$conf['antispam_wordblock_enable'] = true;
$conf['antispam_wordblock_moderate'] = false;

// Host antispam
$conf['antispam_host_enable'] = true;
$conf['antispam_host_moderate'] = true;

// Link antispam
$conf['antispam_link_enable'] = true;
$conf['antispam_link_moderate'] = false;

// Aksimet antispam
$conf['akismet_enable'] = false;
$conf['akismet_moderate'] = false;
$conf['akismet_apikey'] = '';