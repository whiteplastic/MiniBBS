<?php
require './includes/bootstrap.php';
$template->title = 'ID löschen';

if($_POST['drop_ID'] && check_token()) {
	session_destroy();
	setcookie('UID', '', $_SERVER['REQUEST_TIME'] - 3600, '/');
	setcookie('password', '', $_SERVER['REQUEST_TIME'] - 3600, '/');
	redirect('Deine ID wurde gelöscht.', '');
}
?>
<p><em>Das Löschen</em> deiner ID wird die UID, das Passwort und Cookies löschen, was dich ausloggt. Wenn du deine Daten behalten willst, solltest du deine <a href="<?php echo DIR; ?>back_up_ID">ID speichern</a> und/oder <a href="<?php echo DIR; ?>dashboard">ein Passwort setzen</a> bevor du das tust.</p>
<form action="" method="post">
	<?php csrf_token() ?>
	<input type="submit" name="drop_ID" value="Meine ID löschen." />
</form>
<?php
$template->render();
?>
