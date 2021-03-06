<?php
/* The following constant may be used by bootstrap.php, so it must come first. */
define('REPRIEVE_BAN', true);
require './includes/bootstrap.php';
force_id();

$page = new Paginate();
$outbox = ! empty($_GET['outbox']);
$ignorebox = ! empty($_GET['ignored']);
$template->title = ($outbox ? 'Ausgang' : 'Posteingang');
if ($page->current > 1) {
	$template->title   .= ', Seite ' . number_format($page->current);
}

// Check if the user is ignoring all non-mods.
$res = $db->q('SELECT 1 FROM pm_ignorelist WHERE uid = ? and ignored_uid = \'*\'', $_SESSION['UID']);
$ignoring_all_users = (bool) $res->fetchColumn();

// Check for ignored PMs.
if( ! $ignorebox) {
	$res = $db->q('SELECT COUNT(*) FROM private_messages WHERE ignored = 1 AND destination = ?', $_SESSION['UID']);
	$num_ignored = $res->fetchColumn();
} else {
	$template->title = 'Ignorierte Nachrichten';
}

$db->select('`id`, `parent`, `source`, `destination`, `contents`, `time`, `name`, `trip`')
   ->from('private_messages');
if($outbox) {
	$db->where('`source` = ?', $_SESSION['UID']);
} else if($ignorebox) {
	$db->where("`ignored` = '1' AND `destination` = ?", $_SESSION['UID']);
} else if($perm->get('read_admin_pms')) {
	$db->where("`ignored` = '0' AND (`destination` = ? OR `destination` = 'mods' OR `destination` = 'admins')", $_SESSION['UID']);
} else if($perm->get('read_mod_pms')) {
	$db->where("`ignored` = '0' AND (`destination` = ? OR `destination` = 'mods')", $_SESSION['UID']);
} else {
	$db->where("`ignored` = '0' AND `destination` = ?", $_SESSION['UID']);
}
$db->group_by('`parent`')
   ->order_by('`time` DESC')
   ->limit($page->offset, $page->limit);
$res = $db->exec();

// Print the table.
$columns = array(
	($outbox ? 'Empfänger' : 'Autor'),
	'Auszug',
	'Alter ▼'
);
if($perm->get('delete')) {
	$columns[] = 'Löschen';
}
$pms = new Table($columns, 1);
$pms->add_td_class(1, 'snippet');

while( $pm = $res->fetchObject() ) {
	$values = array();

	// If we're using the outbox, determine what should be in the "Recipient" field.
	if($outbox) {
		if($pm->destination == 'mods' || $pm->destination == 'admins') {
			$author = ucfirst($pm->destination);
		} else if($perm->get('view_profile')) {
			$author = '<a href="'.DIR.'profile/' . $pm->destination . '">' . $pm->destination . '</a>';
		} else {
			$author = 'Ein Autor';
		}
	}
	// If we're using the inbox, determine what should be in the "Author" field.
	else if($pm->source == 'system') {
		$author = m('System');
	} else {
		$author = format_name($pm->name, $pm->trip);
		 if($perm->get('view_profile')) {
			$author = '<a href="'.DIR.'profile/' . $pm->source . '">' . $pm->source . '</a>';
		} 
	}
	
	$values[] = $author;
	
	$values[] = '<a href="' . DIR . 'private_message/'.$pm->parent. ($new_pm != $new_parent ? '#reply_'.$new_pm : '') .'">' . parser::snippet($pm->contents) . '</a>';
	
	$values[] = '<span class="help" title="' . format_date($pm->time) . '">' . age($pm->time) . '</span>';
	
	if($perm->get('delete')) {
		$values[] = '<a href="' . DIR . 'delete_message/' . $pm->parent . '">✘</a>';
	}
	$pms->row($values);
}

?>
<ul class="menu">
	<li><a href="<?php echo DIR; ?>compose_message/mods">Nachricht an einen Moderator</a></li>
	<li><a href="<?php echo DIR; ?>compose_message/admins">Nachricht an einen Admin</a></li>
<?php 
	if($ignorebox || $outbox): 
?>
	<li><a href="<?php echo DIR; ?>private_messages">Posteingang</a></li>
<?php 
	endif; 
	if( ! $outbox): 
?>
	<li><a href="<?php echo DIR; ?>outbox">Ausgang</a></li>
<?php 
	endif; 
	if( ! $ignorebox && $num_ignored > 0): 
?>
	<li><a href="<?php echo DIR; ?>ignored_PMs">Ignorierte Nachrichten anzeigen</a> (<?php echo $num_ignored ?>)</li>
<?php 
	endif; 
	if( ! $ignoring_all_users): 
?>
	<li><a href="<?php echo DIR; ?>ignore_PM/*" class="help" title="Du wirst auf keine Nachricht mehr hingewiesen, außer auf die von Moderatoren und Admins." onclick="return quickAction(this, 'Wirklich alle zukünftigen Nachrichten ignorieren?');">Alle Nachrichten ignorieren</a></li>
<?php 
	else: 
?>
	<li><a href="<?php echo DIR; ?>unignore_PM/*" class="help" title="Momentan wirst du auf keine Nachrichten, außer denen von Moderatoren und Admins, hingewiesen." onclick="return quickAction(this, 'Wirklich keine Nachrichten mehr ignorieren?');">Stop ignoring PMs</a></li>
<?php 
	endif; 
?>
</ul>
<?php
$pms->output('(Keine Nachrichten vorhanden.)');
$page->navigation($outbox ? 'outbox' : 'private_messages', $pms->row_count);
$template->render();
?>
