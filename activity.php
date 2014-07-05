<?php
require './includes/bootstrap.php';
update_activity('activity', 1);

$res = $db->q('SELECT activity.action_name, activity.action_id, activity.uid, activity.time, topics.headline FROM activity LEFT OUTER JOIN topics ON activity.action_id = topics.id WHERE activity.time > ? - 960 ORDER BY time DESC', $_SERVER['REQUEST_TIME']);
$count      = $db->num_rows();
$template->title = 'Folks on-line (' . $count . ')';

$columns = array(
	'Doing',
	'Poster',
	'Last sign of life ▼'
);

$table = new Table($columns, 0);

$i = 0;

while (list($action, $action_id, $uid, $age, $headline) = $res->fetch()) {
	// Maximum amount of actions to be shown.
	if (++$i == 100) {
		break;
	}
	
	if ($uid == $_SESSION['UID']) {
		$uid = 'You!';
	} else {
		if ($perm->get('view_profile')) {
			$uid = '<a href="'.DIR.'profile/' . $uid . '">' . $uid . '</a>';
		} else {
			$uid = '?';
		}
	}
	
	$bump     = age($age, $_SERVER['REQUEST_TIME']);
	$headline = htmlspecialchars($headline);
	// Array key based off.
	$actions  = array(
		'advertise' => 'Inquiring about advertising.',
		'statistics' => 'Schaut sich Statisitken an.',
		'hot_topics' => 'Schaut sich beliebte Fäden an.',
		'shuffle' => 'Schaut sich Zufallsfäden an.',
		'bulletins' => 'Liest die neuesten Mitteilungen.',
		'bulletins_old' => 'Liest ältere Mitteilungen.',
		'bulletins_new' => 'Schreibt eine neue Mitteilung.',
		'events' => 'Schaut sich die Termine an.',
		'events_new' => 'Stellt einen neuen Termin ein.',
		'activity' => 'Schaut sich an, was andere machen.',
		'ignore_list' => 'Bearbeitet seine Ignoreliste.',
		'notepad' => 'Liest oder schreibt auf seinem <a href="'.DIR.'notepad">Notizblock</a>.',
		'topics' => 'Schaut sich ältere Fäden an.',
		'dashboard' => 'Bearbeitet sein Kontrollzentrum.',
		'latest_replies' => 'Schaut sich die letzten Antworten an..',
		'latest_bumps' => 'Schaut sich die letzten Stöße an.',
		'latest_topics' => 'Schaut sich die aktuellen Fäden an.',
		'search' => 'Sucht nach einem Faden.',
		'stuff' => 'Ist in der Übersicht.',
		'history' => 'Schaut sich den Verlauf an.',
		'failed_postings' => 'Schaut sich fehlgeschlagene Beiträge an.',
		'watchlist' => 'Schaut sich beobachtete Fäden an.',
		'restore_id' => 'Loggt sich ein.',
		'new_topic' => 'Erstellt einen Faden.',
		'nonexistent_topic' => 'Versucht, einen nichtexistenten Faden aufzurufen.',
		'topic' => "Liest den Faden: <strong><a href=\"".DIR."topic/$action_id\">$headline</a></strong>",
		'replying' => "Antwortet: <strong><a href=\"".DIR."topic/$action_id\">$headline</a></strong>",
		'topic_trivia' => "Liest <a href=\"".DIR."trivia_for_topic/$action_id\">Informationen zum Faden</a>: <strong><a href=\"".DIR."topic/$action_id\">$headline</a></strong>",
		'trash_can' => 'Wühlt im Müll.',
		'status_check' => 'Prüft den Status.',
		'banned' => 'Ist gebannt.'
	);
	
	$action = $actions[$action];
	
	// Unknown or unrecorded actions are bypassed.
	if ($action == null) {
		continue;
	}
	
	// Repeated actions are listed as (See above).
	if ($action == $old_action) {
		$temp = '<span class="unimportant">(Siehe oben)</span>';
	} else {
		$old_action = $action;
		$temp       = $action;
	}
	
	$values = array(
		$temp,
		$uid,
		'<span class="help" title="' . format_date($age) . '">' . age($age) . '</span>'
	);
	$table->row($values);
}
$table->output();
if ($count > 100) {
	echo '<p class="unimportant">(Es sind gerade <b>sehr viele</b> Leute online. Es werden also nicht alle angezeigt.)</p>';
}
$template->render();
?>
