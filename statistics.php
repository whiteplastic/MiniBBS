<?php
require './includes/bootstrap.php';
force_id();
update_activity('statistics');
$template->title = 'Statistik';

$res = $db->q('SELECT count(*) FROM topics WHERE deleted = 0');
$num_topics = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM replies WHERE deleted = 0');
$num_replies = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM topics WHERE author = ? AND deleted = 0', $_SESSION['UID']);
$your_topics = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM replies WHERE author = ? AND deleted = 0', $_SESSION['UID']);
$your_replies = $res->fetchColumn();
$res = $db->q('SELECT COUNT(*) + 1 FROM users WHERE post_count > ?', $_SESSION['post_count']);
$your_ranking = $res->fetchColumn();
$res = $db->q('SELECT COUNT(*) FROM users WHERE post_count > 0');
$num_users = $res->fetchColumn();
$res = $db->q('SELECT count(*) FROM bans');
$num_bans = $res->fetchColumn();
$res = $db->q('SELECT AVG(replies) FROM topics WHERE author = ?', $_SESSION['UID']);
$average_replies_to_you = round($res->fetchColumn(), 2);

$replies_per_topic = $num_topics ? round($num_replies / $num_topics) : 0;
$your_replies_per_topic = $your_topics ? round($your_replies / $your_topics, 2) : 0;
$your_posts = $your_topics + $your_replies;
$total_posts = $num_topics + $num_replies; 

$days_since_start = floor(( $_SERVER['REQUEST_TIME'] - SITE_FOUNDED ) / 86400);
if ($days_since_start == 0) {
	$days_since_start = 1;
}

$days_since_first_visit = floor(( $_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] ) / 86400);
if ($days_since_first_visit == 0) {
	$days_since_first_visit = 1;
}

$posts_per_user = round($total_posts / $num_users, 2);
$posts_per_day = round($total_posts / $days_since_start);
$topics_per_day = round($num_topics / $days_since_start);
$replies_per_day = round($num_replies / $days_since_start);
$your_posts_per_day = round($your_posts / $days_since_first_visit, 2);
?>
<table>
	<tr>
		<th></th>
		<th class="minimal">Anzahl</th>
		<th>Kommentar</th>
	</tr>
	<tr class="odd">
		<th class="minimal">Insgesamt existierende Beiträge</th>
		<td class="minimal"><?php echo format_number($total_posts) ?></td>
		<td></td>
	</tr>
	<tr>
		<th class="minimal">Existierende Fäden</th>
		<td class="minimal"><?php echo format_number($num_topics) ?></td>
		<td></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Existierende Antworten</th>
		<td class="minimal"><?php echo format_number($num_replies) ?></td>
		<td>That's ~<?php echo $replies_per_topic ?> replies/topic.</td>
	</tr>
	<tr>
		<th class="minimal">Beiträge pro Tag</th>
		<td class="minimal">~<?php echo format_number($posts_per_day) ?></td>
		<td></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Fäden pro Tag</th>
		<td class="minimal">~<?php echo format_number($topics_per_day) ?></td>
		<td></td>
	</tr>
	<tr>
		<th class="minimal">Antworten pro Tag</th>
		<td class="minimal">~<?php echo format_number($replies_per_day) ?></td>
		<td></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Beiträge pro Benutzer</th>
		<td class="minimal">~<?php echo $posts_per_user ?></td>
		<td></td>
	</tr>
	<tr>
		<th class="minimal">Aktive Bans</th>
		<td class="minimal"><?php echo format_number($num_bans) ?></td>
		<td></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Tage seit der Gründung</th>
		<td class="minimal"><?php echo number_format($days_since_start) ?></td>
		<td>Online gegangen am <?php echo date('j.n.Y', SITE_FOUNDED) . ', vor ' . age(SITE_FOUNDED) ?>.</td>
	</tr>
</table>
<table>
	<tr>
		<th></th>
		<th>Anzahl</th>
		<th>Kommentar</th>
	</tr>
	
	
	<tr class="odd">
		<th class="minimal">Tage seit deinem ersten Besuch</th>
		<td class="minimal"><?php echo format_number($days_since_first_visit) ?></td>
		<td>Du bist seit dem <?php echo date('j.n.Y', $_SESSION['first_seen']) . ' dabei, das war vor ' . age($_SESSION['first_seen']) ?>.</td>
	</tr>
	<tr>
		<th class="minimal">Beiträge von dir</th>
		<td class="minimal"><?php echo format_number($your_posts) ?></td>
		<td></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Fäden von dir</th>
		<td class="minimal"><?php echo format_number($your_topics) ?></td>
		<td></td>
	</tr>
	<tr>
		<th class="minimal">Antworten von dir</th>
		<td class="minimal"><?php echo format_number($your_replies) ?></td>
		<td>That's ~<?php echo $your_replies_per_topic ?> Antworten pro Faden</td>
	</tr>
	<tr class="odd">
		<th class="minimal">Beiträge pro Tag von dir</th>
		<td class="minimal">~<?php echo $your_posts_per_day ?></td>
		<td></td>
	</tr>
	<tr>
		<th class="minimal">Bei den Beitragszahlen belegst du Rang</th>
		<td class="minimal">#<?php echo $your_ranking ?></td>
		<td></td>
	</tr>
	<tr class="odd">
		<th class="minimal">Durchschnittliche Antworten auf deine Fäden</th>
		<td class="minimal">~<?php echo $average_replies_to_you ?></td>
		<td></td>
	</tr>
	
</table>
<?php
$template->render();
?>
