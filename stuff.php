<?php
require './includes/bootstrap.php';
update_activity('stuff');
$template->title = 'Übersicht';

/**
 * If 48 hours has passed since we last optimized the database, send an AJAX
 * request to maintenance.php. (We use this because many hosts do not support cron.)
 */
if(cache::fetch('maintenance') < $_SERVER['REQUEST_TIME'] - 172800) {
	$template->onload = "$.get('".DIR."maintenance.php');";
}

?>
<div style="width: 50%; float:left">
<ul class="stuff">
	<li><strong><a href="<?php echo DIR; ?>dashboard">Kontrollzentrum</a></strong> — <span class="unimportant">Persönliche Einstellungen, Benutzername und Passwort..</span></li>
	<li><a href="<?php echo DIR; ?>private_messages">Inbox</a> — <span class="unimportant">Deine privaten Nachrichten (<?php echo $notifications['pms']; ?> ungelesen).</span></li>
	<li><a href="<?php echo DIR; ?>edit_ignore_list">Ignoreliste bearbeiten</a> — <span class="unimportant">Selbstzensur.</span></li>
	<li><a href="<?php echo DIR; ?>theme_gallery">Theme-Gallerie</a> <?php echo ($_SESSION['settings']['custom_style'] ? '(<a href="'.DIR.'edit_style/' . (int) $_SESSION['settings']['custom_style'] . '">bearbeiten</a>)' : '(<a href="'.DIR.'new_style">neu</a>)') ?> — <span class="unimportant">Benutzerdefinierte Themes ansehen.</span></li>
</ul>

<ul class="stuff">
	<li><a href="<?php echo DIR; ?>mod_log">Mod-Log</a></li>
	<li><a href="<?php echo DIR; ?>statistics">Statistik</a></li>
	<li><a href="<?php echo DIR; ?>failed_postings">Fehlgeschlagene Beiträge</a></li>
	<li><a href="<?php echo DIR; ?>date_and_time">Datum und Uhrzeit</a></li>
	<li><a href="<?php echo DIR; ?>notepad">Notizblock</a> — <span class="unimportant">Dein persönlicher Notizblock.</span></li>
</ul>

</div>

<div class="width:50%; float:right">
<ul class="stuff">
	<li><strong><a href="<?php echo DIR; ?>restore_ID">ID wiederherstellen</a></strong> — <span class="unimportant">Einloggen.</span></li>
	<li><a href="<?php echo DIR; ?>back_up_ID">ID speichern</a></li>
	<li><a href="<?php echo DIR; ?>recover_ID_by_email">ID per Mail wiederherstellen</a></li>
	<li><a href="<?php echo DIR; ?>drop_ID">ID löschen</a> — <span class="unimportant">Ausloggen.</span></li>
	<li><a href="<?php echo DIR; ?>trash_can">Papierkorb</a> — <span class="unimportant">Deine gelöschten Beiträge.</span></li>
</ul>

<ul class="stuff">
<?php
	$user_menu = $template->get_user_menu();
	foreach($template->menu_options as $text => $link):
		if( ! in_array($link, $user_menu)):
?>
		<li><a href="<?php echo DIR . $link ?>"><?php echo $template->mark_new($text, $link) ?></a></li>
<?php
		endif;
	endforeach;
?>
</ul>

</div>

<?php
if ($perm->get('cms') || $perm->get('ban') || $perm->get('defcon')):
?>
<h4 class="section" style="clear: both;">Moderation</h4>
<ul class="stuff">
<?php
	if($perm->get('admin_dashboard')):
?>
	<li><a href="<?php echo DIR ?>admin_dashboard"><strong>Administrative dashboard</strong></a>  — <span class="unimportant">Manage board-wide settings.</span></li>
<?php
	endif;
	if($perm->get('cms')): 
?>
	<li><a href="<?php echo DIR ?>CMS">Content management</a>  — <span class="unimportant">Manage non-dynamic (static) pages.</span></li>
<?php
	endif;
	if($perm->get('ban')):
?>
	<li><a href="<?php echo DIR ?>ban">Ban user</a>  — <span class="unimportant">Ban a UID, IP or IP range.</span></li>
	<li><a href="<?php echo DIR ?>bans">Bans</a>  — <span class="unimportant">View a list of current bans and manage them.</span></li>
<?php
	endif;
	if ($perm->get('exterminate')):
?>
	<li><a href="<?php echo DIR ?>exterminate">Exterminate trolls by phrase</a>  — <span class="unimportant">A last measure.</span></li>
<?php
	endif;
	if($perm->get('defcon')):
?>
	<li><a href="<?php DIR ?>defcon">Manage DEFCON</a>  — <span class="unimportant">Do not treat this lightly.</span></li>
<?php
	endif;
	if($perm->get('manage_messages')):
?>
	<li><a href="<?php DIR ?>message_manager">Manage messages</a>  — <span class="unimportant">Edit text from the MiniBBS interface.</span></li>
<?php
	endif;
?>
</ul>
<?php
endif;

$template->render();
?>
