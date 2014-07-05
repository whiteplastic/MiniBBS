<?php
require './includes/bootstrap.php';
$template->title        = 'ID per Mail wiederherstellen.';
$template->onload = 'focusId(\'e-mail\');';

if (!empty($_POST['e-mail'])) {
	// Validate e-mail address.
	if (!filter_var($_POST['e-mail'], FILTER_VALIDATE_EMAIL)) {
		error::add('Das scheint keine gültige Mailadresse zu sein.');
	}
	// Deny flooders (this should be done from the database for added security).
	if ($_SESSION['recovery_email_count'] > 3) {
		error::add('Wie oft willst du deine ID noch wiederherstellen?');
	}
	
	$res = $db->q('SELECT user_settings.uid, users.password FROM user_settings INNER JOIN users ON user_settings.uid = users.uid WHERE user_settings.email = ? LIMIT 50', $_POST['e-mail']);	

	$ids_for_email = array();
	while (list($uid, $password) = $res->fetch()) {
		$ids_for_email[$uid] = $password;
	}
	
	if (empty($ids_for_email)) {
		error::add('Mit der Adresse gibt es keine verbundenen IDs.');
	}
	
	if (error::valid()) {
		$num_ids = count($ids_for_email);
		if ($num_ids == 1) {
			$email_body = 'Deine ID ist ' . key($ids_for_email) . ' und dein Passwort ist ' . current($ids_for_email) . '. Um deine ID wiederherzustellen, folge diesem Link: ' . DIR . 'restore_ID/' . key($ids_for_email) . '/' . current($ids_for_email);
		} else {
			$email_body = 'Folgende IDs sind mit dieser Adresse verbunden:' . "\n\n";
			foreach ($ids_for_email as $id => $password) {
				$email_body .= 'ID: ' . $id . "\n" . 'Passwort: ' . $password . "\n" . 'Link zum wiederherstellen: ' . DIR . 'restore_ID/' . $id . '/' . $password . "\n\n";
			}
		}
		mail($_POST['e-mail'], SITE_TITLE . ' ID-Wiederherstellung', $email_body, 'Von: ' . SITE_TITLE . '<' . MAILER_ADDRESS . '>');
		$_SESSION['recovery_email_count']++;
		redirect('Wiederherstellungsmail versendet.', '');
	}
}
error::output();
?>
<p>Wenn du mit deiner ID eine Mailadresse verbunden hast (du kannst das im <a href="<?php echo DIR; ?>dashboard">Kontrollzentrum</a> tun), kannst du hiermit das Passwort wiederherstellen. Du bekommst einen Wiederherstellungslink für jede ID, die mit deiner Adresse verbunden ist.</p>
<form action="" method="post">
	<div class="row">
		<label for="e-mail">Deine E-Mail-Adresse</label>
		<input type="text" id="e-mail" name="e-mail" size="30" maxlength="100" />
	</div>
	
	<div class="row">
		<input type="submit" value="Wiederherstellungsmail versenden" />
	</div>
</form>
<?php
$template->render();
?>
