<?php
require './includes/bootstrap.php';
force_id();
$template->title = 'Ignoreliste';
update_activity('ignore_list', 1);
$template->onload = 'focusId(\'ignore_list\'); init();';

if ($_POST['form_sent']) {
	// CSRF checking.
	check_token();
	check_length($_POST['ignore_list'], 'ignore list', 0, 4000);
	if (error::valid()) {
		// Refresh our cache of phrases.
		unset($_SESSION['ignored_phrases']);
		// Insert or update.
		$db->q('INSERT INTO ignore_lists (uid, ignored_phrases) VALUES (?, ?) ON DUPLICATE KEY UPDATE ignored_phrases = ?', $_SESSION['UID'], $_POST['ignore_list'], $_POST['ignore_list']);
        $_SESSION['notice'] = 'Ignore list updated.';
        if ( ! $_SESSION['settings']['ostrich_mode']) {
            $_SESSION['notice'] .= ' Du musst im <a href="'.DIR.'dashboard">Strauss-Modus sein</a> damit die Ignoreliste einen Effekt zeigt.';
        }
    } else {
        $ignored_phrases = $_POST['ignore_list'];
    }
}

$res = $db->q('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?', $_SESSION['UID']);
$ignored_phrases = $res->fetchColumn();
error::output();
?> 
<p>Wenn der Strauss-Modus <a href="<?php echo DIR; ?>dashboard">aktiviert</a> ist, wird jeder Faden und jede Antwort versteckt, wenn Einträge aus deiner Ignoreliste vorhanden sind. Bitte beachte, dass, wenn du einen Namen ignorierst, auch jeder Beitrag gefiltert wird, der ihn enthält. Wenn du bestimmte Benutzer ignorieren willst, benutze lieber einen Tripcode. Verweise auf versteckte Beiträge werden mit "@versteckt" angegeben. Pro Zeile einen Eintrag angeben. Groß- und Kleinschreibung wird nicht beachtet.</p>

<p>Reguläre Ausdrücke in der Form <kbd>/.../</kbd> sind auch möglich. Sie dürfen nicht länger als 28 Zeichen sein.</p>

<p>Bilder werden nach MD5-hash ignoriert.</p>
<form action="" method="post">
	<?php csrf_token() ?>
	<div>
		<textarea id="ignore_list" name="ignore_list" cols="80" rows="10"><?php echo htmlspecialchars($ignored_phrases) ?></textarea>
	</div>
	<div class="row">
		<input type="submit" name="form_sent" value="Speichern" />
	</div>
</form>
<?php
$template->render();
?>
