<?php
require './includes/bootstrap.php';
update_activity('restore_id');
$template->title  = 'ID wiederherstellen';
$template->onload = 'focusId(\'memorable_name\')';

// If an ID card was uploaded.
if (isset($_POST['do_upload'])) {
	list($uid, $password) = file($_FILES['id_card']['tmp_name'], FILE_IGNORE_NEW_LINES);
}
// ...or an ID and password was inputted.
else if ( ! empty($_POST['UID']) && ! empty($_POST['password'])) {
	$uid = $_POST['UID'];
	$password = $_POST['password'];
}
// ...or a link from a recovery e-mail is being used.
else if ( ! empty($_GET['UID']) && ! empty($_GET['password'])) {
	$uid = $_GET['UID'];
	$password = $_GET['password'];
}
// ...or a memorable name was inputted.
else if ( ! empty($_POST['memorable_name'])) {
	$password_hash = ($_POST['memorable_password'] === '' ? '' : hash_password($_POST['memorable_password']));
	$res = $db->q('SELECT user_settings.uid, users.password FROM user_settings INNER JOIN users ON user_settings.uid = users.uid WHERE LOWER(user_settings.memorable_name) = LOWER(?) AND user_settings.memorable_password = ?', $_POST['memorable_name'], $password_hash);
	list($uid, $password) = $res->fetch();
	if (empty($uid)) {
			error::add('Die Angaben waren fehlerhaft.');
	}
}
	
if ( ! empty($uid)) {
	$previous_id = $_SESSION['UID'];
	$previous_post_count = $_SESSION['post_count'];
	if(activate_id($uid, $password)) {
		load_settings();
		$notice = 'Willkommen zurück.';
		
		if( ! empty($_POST['merge_uid'])) {
			if($perm->uid_banned($previous_id)) {
				$notice .= ' Du kannst keine gebannte ID mit dieser zusammenfügen.';
			} else {
				$db->q('UPDATE topics SET author = ? WHERE author = ?', $_SESSION['UID'], $previous_id);
				$db->q('UPDATE replies SET author = ? WHERE author = ?', $_SESSION['UID'], $previous_id);
				$db->q('UPDATE users SET post_count = post_count + ? WHERE uid = ?', $previous_post_count, $_SESSION['UID']);
				$db->q('UPDATE users SET post_count = 0 WHERE uid = ?', $previous_id);
				$db->q('UPDATE private_messages SET source = ? WHERE source = ?', $_SESSION['UID'], $previous_id);
				$db->q('UPDATE private_messages SET destination = ? WHERE destination = ?', $_SESSION['UID'], $previous_id);
				$notice .= ' IDs wurden zusammengefügt.';
			}
		}
		redirect($notice, '');
	}
	else {
		error::add('Benutzername oder Passwort inkorrekt.');
	}
}
error::output();
?>
<p>Deine interne ID kann auf unterschiedliche Weise wiederhergestellt werden. Wenn keine davon funktioniert, kannst du sie möglicherweise <a href="<?php echo DIR; ?>recover_ID_by_email">per Mail wiederherstellen</a>.
<?php if($_SESSION['post_count']): ?><p>Wenn du die "<strong>IDs zusammenfügen</strong>"-Option wählst, werden die Daten deiner aktuellen ID mit der wiederhergestellten ID zusammengeführt.</p><?php endif; ?>
<fieldset>
	<legend>Benutzernamen und Passwort eingeben</legend>
	<p>Zugangsdaten können im <a href="<?php echo DIR; ?>dashboard">Kontrollzentrum</a> gesetzt werden.</p>
	<form action="" method="post">
		<div class="row">
			<label for="memorable_name">Benutzername</label>
			<input type="text" id="memorable_name" name="memorable_name" maxlength="100" />
		</div>
		<div class="row">
			<label for="memorable_password">Password</label>
			<input type="password" id="memorable_password" name="memorable_password" />
		</div>
		<div class="row">
			<input type="submit" value="Wiederherstellen" class="inline" />   <?php if($_SESSION['post_count']): ?><input type="checkbox" name="merge_uid" id="merge_uid" value="1" class="inline" /> <label for="merge_uid" class="inline">IDs zusammenfügen</label><?php endif; ?>
		</div>
	</form>
</fieldset>
<fieldset>
	<legend>ID und Passwort eingeben</legend>
	<p>Deine interne ID und das zugehörige Passwort werden automatisch generiert, wenn deine ID erstellt wird. Sie sind auf der Seite <a href="<?php echo DIR; ?>back_up_ID">"ID wiederherstellen"</a> abrufbar.</p>
	
	<form action="" method="post">
		<div class="row">
			<label for="UID">Interne ID</label>
			<input type="text" id="UID" name="UID" size="23" maxlength="23" />
		</div>
		<div class="row">
			<label for="password">Internes Passwort</label>
			<input type="password" id="password" name="password" size="32" maxlength="32" />
		</div>
		<div class="row">
			<input type="submit" value="Wiederherstellen" class="inline" />  <?php if($_SESSION['post_count']): ?><input type="checkbox" name="merge_uid" id="merge_uid2" value="1" class="inline" /> <label for="merge_uid2" class="inline">IDs zusammenfügen</</label><?php endif; ?>
		</div>
	</form>
</fieldset>
<fieldset>
	<legend>ID-Karte hochladen</legend>
	<p>Wenn du eine <a href="<?php echo DIR; ?>generate_ID_card">ID-Karte</a> generiert hast, kannst du sie hier hochladen.</p>
	<form enctype="multipart/form-data" action="" method="post">
		<div class="row">
			<input name="id_card" type="file" />
		</div>
		<div class="row">
			<input name="do_upload" type="submit" value="Hochladen und wiederherstellen" class="inline" />  <?php if($_SESSION['post_count']): ?><input type="checkbox" name="merge_uid" id="merge_uid3" value="1" class="inline" /> <label for="merge_uid3" class="inline">IDs zusammenfügen</</label><?php endif; ?>

		</div>
	</form>
</fieldset>
<?php
$template->render();
?>
