<?php
require './includes/bootstrap.php';

if (!ctype_digit($_GET['id'])) {
	error::fatal('Ungültige ID.');
}

$res = $db->q('SELECT headline, visits, replies, author FROM topics WHERE id = ?', $_GET['id']);

if($db->num_rows() < 1) {
	$template->title = 'Faden existiert nicht';
	error::fatal('Vielleicht wurde er gelöscht.');
}

list($topic_headline, $topic_visits, $topic_replies, $topic_author) = $res->fetch();

update_activity('topic_trivia', $_GET['id']);

$template->title = 'Informationen zum Faden: <a href="'.DIR.'topic/' . $_GET['id'] . '">' . htmlspecialchars($topic_headline) . '</a>';

$statistics = array();
$res = $db->q('SELECT count(*) FROM watchlists WHERE topic_id = ?', $_GET['id']);
$topic_watchers = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM activity WHERE action_name = ? AND action_id = ?', 'topic', $_GET['id']);
$topic_readers = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM activity WHERE action_name = ? AND action_id = ?', 'replying', $_GET['id']);
$topic_writers = $res->fetchColumn();
$res = $db->q('SELECT count(DISTINCT author) FROM replies WHERE parent_id = ? AND author != ?', $_GET['id'], $topic_author);
$topic_participants = $res->fetchColumn() + 1;  // Include topic author.

?>
<table>
	<tr>
		<th class="minimal">Besucher</th>
		<td><?php echo format_number($topic_visits) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Beobachter</th>
		<td><?php echo format_number($topic_watchers) ?></td>
	</tr>
	<tr>
		<th class="minimal">Teilnehmer</th>
		<td><?php echo ($topic_participants === 1) ? '(Just the creator.)' : format_number($topic_participants) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Antworten</th>
		<td><?php echo format_number($topic_replies) ?></td>
	</tr>
	<tr>
		<th class="minimal">Aktuelle Leser</th>
		<td><?php echo format_number($topic_readers) ?></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Aktuelle Antwortende</th>
		<td><?php echo format_number($topic_writers) ?></td>
	</tr>
</table>
<?php
$template->render();
?>
