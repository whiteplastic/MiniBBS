<?php
require './includes/bootstrap.php';
update_activity('back_up_id');
force_id();
$template->title = 'ID speichern';
if ($_GET['action'] === 'generate_id_card') {
	header('Content-type: text/plain');
	header('Content-Disposition: attachment; filename="' . rawurlencode(SITE_TITLE) . '_ID.crd"');
	echo $_SESSION['UID'] . "\n" . $_COOKIE['password'];
	exit;
} else {
?>
	<table>
		<tr>
			<th class="minimal">Deine ID</th>
			<td><code><?php
	echo $_SESSION['UID'];
?></code></td>
		</tr>
		<tr>
			<th class="minimal">Dein Passwort</th>
			<td><code class=spoiler><?php
	echo $_COOKIE['password'];
?></code></td>
		</tr>
	</table>
	<p>Du kannst <a href="<?php echo DIR; ?>generate_ID_card">deine ID-Karte als Datei herunterladen.</a>.</p>
	<?php
}
$template->render();
?>
