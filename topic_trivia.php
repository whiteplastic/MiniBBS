<?php
require './includes/bootstrap.php';
load_twig();

ini_set('display_errors', true);
error_reporting(E_ALL);

if (!ctype_digit($_GET['id'])) {
	error::fatal('Invalid ID.');
}

$res = $db->q('SELECT headline, visits, replies, author FROM topics WHERE id = ?', $_GET['id']);

if($db->num_rows() < 1) {
	$template->title = 'Non-existent topic';
	error::fatal('There is no such topic. It may have been deleted.');
}

list($topic_headline, $topic_visits, $topic_replies, $topic_author) = $res->fetch();

update_activity('topic_trivia', $_GET['id']);

$template->title = 'Trivia for topic: <a href="'.DIR.'topic/' . $_GET['id'] . '">' . htmlspecialchars($topic_headline) . '</a>';

$stats = array(
	'topic_visits' => $topic_visits,
	'topic_replies' => $topic_replies,
	'topic_author' => $topic_author,
);
$res = $db->q('SELECT count(*) FROM watchlists WHERE topic_id = ?', $_GET['id']);
$stats['topic_watchers'] = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM activity WHERE action_name = ? AND action_id = ?', 'topic', $_GET['id']);
$stats['topic_readers'] = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM activity WHERE action_name = ? AND action_id = ?', 'replying', $_GET['id']);
$stats['topic_writers'] = $res->fetchColumn();
$res = $db->q('SELECT count(DISTINCT author) FROM replies WHERE parent_id = ? AND author != ?', $_GET['id'], $topic_author);
$stats['topic_participants'] = $res->fetchColumn() + 1;  // Include topic author.

echo $twig->render('topic_trivia.html', $stats);
$template->render();
