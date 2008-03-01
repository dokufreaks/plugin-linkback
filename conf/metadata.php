<?php

/**
 * Metadata for configuration manager plugin
 * Additions for the linkback plugin
 *
 * @author    Gina Haeussge <osd@foosel.net>
 */

$meta['enable_pingback'] = array( 'onoff' );
$meta['enable_trackback'] = array( 'onoff' );
$meta['order'] = array( 'multichoice', '_choices' => array( 'trackback, pingback', 'pingback, trackback' ) );
$meta['range'] = array( 'string', '_pattern' => '#[0-9]+#' );
$meta['allow_guests'] = array( 'onoff' );
$meta['enabled_namespaces'] = array( 'string', '_pattern' => '#[A-Za-z_:0-9, ]*#' );
$meta['ping_internal'] = array('onoff');
$meta['log_processing'] = array('onoff');

$meta['usefavicon'] = array('onoff');
$meta['favicon_default'] = array('string');

$meta['antispam_linkcount_enable'] = array('onoff');
$meta['antispam_linkcount_moderate'] = array('onoff');
$meta['antispam_linkcount_max'] = array( 'string', '_pattern' => '#[0-9]+#' );

$meta['antispam_wordblock_enable'] = array('onoff');
$meta['antispam_wordblock_moderate'] = array('onoff');

$meta['antispam_host_enable'] = array('onoff');
$meta['antispam_host_moderate'] = array('onoff');

$meta['antispam_link_enable'] = array('onoff');
$meta['antispam_link_moderate'] = array('onoff');

$meta['akismet_enable'] = array('onoff');
$meta['akismet_moderate'] = array('onoff');
$meta['akismet_apikey'] = array('string');