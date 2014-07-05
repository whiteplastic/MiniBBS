<?php
require './includes/bootstrap.php';
update_activity('history');
force_id();

$page = new Paginate();
$template->title = 'Dein Beitragsverlauf';

if ($page->current > 1) {
	$template->title .= ', Seite ' . number_format($page->current);
}

if(isset($_POST['clear_citations']) && check_token()) {
	$db->q('DELETE FROM citations WHERE uid = ?', $_SESSION['UID']);
	redirect('Zitate gelöscht.', '');
}

if($notifications['citations']) {
	if( ! isset($_GET['citations'])) {
		echo '<h4 class="section">Antworten auf deine Antworten.</h4>';
	} else {
		$template->title = 'Antworten auf deine Antworten';
	}

	// Delete notifications of replies-to-replies that no longer exist.
	$db->q
	(
		"DELETE citations FROM citations
		INNER JOIN replies ON citations.reply = replies.id 
		INNER JOIN topics ON citations.topic = topics.id 
		WHERE citations.uid = ? AND (topics.deleted = '1' OR replies.deleted = '1')", 		
		$_SESSION['UID']
	);

	// List replies to user's replies.
	$res = $db->q
	(
		'SELECT DISTINCT citations.reply AS id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time AS parent_time
		FROM citations 
		INNER JOIN replies ON citations.reply = replies.id 
		INNER JOIN topics ON replies.parent_id = topics.id 
		WHERE citations.uid = ? ORDER BY citations.reply 
		DESC LIMIT '.$page->offset.', '.$page->limit,
		$_SESSION['UID']
	);

	$columns = array
	(
		'Antwort auf deine Antwort',
		'Faden',
		'Alter ▼'
	);
	$citations = new Table($columns, 1);
	$citations->add_td_class(1, 'topic_headline');
	$citations->add_td_class(0, 'reply_body_snippet');

	while ($reply = $res->fetchObject()) {
		$values = array
		(
			'<a href="'.DIR.'topic/' . $reply->parent_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $reply->id . '">' . parser::snippet($reply->body) . '</a>',
			'<a href="'.DIR.'topic/' . $reply->parent_id . '">' . htmlspecialchars($reply->headline) . '</a> <span class="help unimportant" title="' . format_date($reply->parent_time) . '">(' . age($reply->parent_time) . ' alt)</span>',
			'<span class="help" title="' . format_date($reply->time) . '">' . age($reply->time) . '</span>'
		);
		
		$citations->row($values);
	}
	$citations->output('(Es scheint, als wäre diese Antwort gelöscht worden.)');
?>
<form action="" method="post">
	<?php csrf_token() ?>
	<input type="submit" name="clear_citations" value="Clear citations" class="help" title="Du wirst für diese Antworten nicht länger benachrichtigt." />
</form>
<?php
}

if( ! $_GET['citations']) {
	if($notifications['citations']) {
		echo '<h4 class="section">Deine Beiträge</h4>';
	}
	// List topics.
	$res = $db->q('SELECT id, time, replies, visits, headline, poll, locked, sticky FROM topics WHERE author = ? AND deleted = 0 ORDER BY id DESC LIMIT '.$page->offset.', '.$page->limit, $_SESSION['UID']);

	$columns = array
	(
		'Titel',
		'Antworten',
		'Aufrufe',
		'Alter ▼'
	);
	$topics = new Table($columns, 0);
	$topics->add_td_class(0, 'topic_headline');

	while (list($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline, $topic_poll, $topic_locked, $topic_sticky) = $res->fetch()) {
		$values = array
		(
			format_headline(htmlspecialchars($topic_headline), $topic_id, $topic_replies, $topic_poll, $topic_locked, $topic_sticky),
			replies($topic_id, $topic_replies),
			format_number($topic_visits),
			'<span class="help" title="' . format_date($topic_time) . '">' . age($topic_time) . '</span>'
		);
		
		$topics->row($values);
	}
	$num_topics_fetched = $topics->row_count;
	echo $topics->output();

	// List replies.
	$res = $db->q
	(
		'SELECT replies.id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time, topics.replies
		FROM replies 
		INNER JOIN topics ON replies.parent_id = topics.id 
		WHERE replies.author = ? AND replies.deleted = 0 AND topics.deleted = 0 
		ORDER BY id DESC LIMIT '.$page->offset.', '.$page->limit, 
		$_SESSION['UID']
	);

	$columns = array
	(
		'Antwortauszug',
		'Titel',
		'Antworten',
		'Alter ▼'
	);
	$replies = new Table($columns, 1);
	$replies->add_td_class(1, 'topic_headline');
	$replies->add_td_class(0, 'reply_body_snippet');

	while (list($reply_id, $parent_id, $reply_time, $reply_body, $topic_headline, $topic_time, $topic_replies) = $res->fetch()) {
		$values = array
		(
			'<a href="'.DIR.'topic/' . $parent_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $reply_id . '">' . parser::snippet($reply_body) . '</a>',
			'<a href="'.DIR.'topic/' . $parent_id . '">' . htmlspecialchars($topic_headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . age($topic_time) . ' alt)</span>',
			replies($parent_id, $topic_replies),
			'<span class="help" title="' . format_date($reply_time) . '">' . age($reply_time) . '</span>'
		);
		
		$replies->row($values);
	}
	$num_replies_fetched = $replies->row_count;
	$replies->output();
}

if($num_topics_fetched + $num_replies_fetched == 0 && ! isset($_GET['citations'])) {
	echo '<p>Du hast noch nichts geschrieben.</p>';
}

$page->navigation('history', $num_replies_fetched);
$template->render();
?>
