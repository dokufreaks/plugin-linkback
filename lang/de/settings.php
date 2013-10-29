<?php

/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * 
 * @author Egmont Schreiter <egmont.schreiter@gmx.de>
 */
$lang['enable_pingback']       = 'Pingbacks aktivieren.';
$lang['enable_trackback']      = 'Trackbacks aktivieren.';
$lang['order']                 = 'Reihenfolge in der Linkbacks gesendet werden sollen.';
$lang['range']                 = 'Wieviele KiloBytes sollen von verlinkten Seiten zur Erkundung angefordert werden?';
$lang['allow_guests']          = 'Erlaube unregistrierten Nutzer das Senden von Linkbacks.';
$lang['enabled_namespaces']    = 'Namespaces in denen das Senden von Linkbacks standartmässig aktiviert ist (mit Komma getrennt, Stern \'*\' aktiviert Linkbacks überall)';
$lang['ping_internal']         = 'Auch interne Links pingen, z.B. um ehemalige Blogeinträge zu referenzieren.';
$lang['show_trackback_url']    = 'Zeige trackback URLs auf aktivierten Seiten';
$lang['log_processing']        = 'Logge die Verarbeitung von ankommenden Linkbacks (Log-Datei "linkback.log" liegt im cache-dir)';
$lang['usefavicon']            = 'Hole und Zeige Favicon für ankommende Linkbacks';
$lang['favicon_default']       = 'URL zum Favicon wenn angefragte Seite keines hat.';
$lang['antispam_linkcount_enable'] = 'aktiviert linkcount antispam (Links zählen um Spam einzuschätzen) Messungen';
$lang['antispam_linkcount_moderate'] = 'Wenn gezählte Links die erlaubte Anzahl überschreitet, moderiere Linkback anstatt zu löschen.';
$lang['antispam_linkcount_max'] = 'Maximale Anzahl von Links die ohne weitere Maßnahmen erlaubt sind.';
$lang['antispam_wordblock_enable'] = 'Aktiviert wordblock antispam (Wörter prüfen um Spam einzuschätzen) Messung';
$lang['antispam_wordblock_moderate'] = 'Wenn Linkback Worte der schwarzen Liste (blacklist) enthält, moderiere anstatt zu löschen.';
$lang['antispam_host_enable']  = 'Aktiviert host antispam Messung (Adresse des Servers prüfen um Spam einzuschätzen) ';
$lang['antispam_host_moderate'] = 'Wenn hostname und zugreifende IP-Adresse nicht stimmen, moderiere anstatt zu löschen.';
$lang['antispam_link_enable']  = 'Aktiviert link antispam Messung (für Linkbacks muss ein Link zu uns gesetzt sein) ';
$lang['antispam_link_moderate'] = 'Wenn sendende Seite keinen Link zu uns enthält, moderiere Linkback anstatt zu löschen.';
$lang['akismet_enable']        = 'Aktiviert Akismet antispam Messung. ';
