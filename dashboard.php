<?php

/**
 * Steps to adding a dashboard option:
 * 1. Add your new option to the array in /config/default_dashboard.php, following the instructions there.
 * 2. Add a column to the "user_settings" table for your new option.
 * 3. Add the HTML input below.
 * Your setting will now be available from $_SESSION['settings']['your_setting'] 
 */
 
require './includes/bootstrap.php';
force_id();
update_activity('dashboard');
$template->title = 'Kontrollzentrum';

$stylesheets = get_styles();
$custom_styles = array();
$res = $db->q('SELECT id, title, basis FROM user_styles WHERE uid = ?', $_SESSION['UID']);
while($style = $res->fetchObject()) {
	$custom_styles[$style->id] = array(
		'title' => $style->title,
		'basis' => $style->basis
	);
}

if(isset($_POST['form_sent'])) {
	/* default_dashboard.php contains $default_dashboard -- an array of info on dashboard options */
	require SITE_ROOT . '/config/default_dashboard.php';

	check_token();
	
	if(isset($_POST['revert'])) {
		$new_settings = array_map('current', $default_dashboard);
		$new_settings['memorable_name'] = $_POST['form']['memorable_name'];
	} else {
		$new_settings = array_map('trim', $_POST['form']);
	}
	
	/* First, make specific validations and transformations. */
	if(preg_match('!custom:([0-9]+)!', $new_settings['style'], $match)) {
		if( ! isset($custom_styles[$match[1]])) {
			error::add('Ungültige ID.');
		} else {
			$new_settings['custom_style'] = $match[1];
			$new_settings['style'] = $custom_styles[$match[1]]['basis'];
		}
	} else {
		$new_settings['custom_style'] = '0';
	}
	
	if( ! empty($new_settings['style']) && ! in_array($new_settings['style'], $stylesheets)) {
		error::add('Stylesheet existiert nicht.');
	}
	
	if( ! empty($new_settings['memorable_name']) && $new_settings['memorable_name'] != $_SESSION['settings']['memorable_name']) {
		$res = $db->q('SELECT 1 FROM user_settings WHERE LOWER(memorable_name) = LOWER(?)', $new_settings['memorable_name']);

		if($res->fetchColumn()) {
			error::add('Der Name "' . htmlspecialchars($new_settings['memorable_name']) . '" wird schon benutzt.');
		}
	}
	
	if($new_settings['memorable_password'] != '') {
		$new_settings['memorable_password'] = hash_password($new_settings['memorable_password']);
	}
	
	if(empty($new_settings['custom_menu'])) {
		/* Revert to the default */
		$new_settings['custom_menu'] = DEFAULT_MENU;
	}
	/* Clean up menu formatting*/
	$new_settings['custom_menu'] = str_replace(array('  ', ']{'), array(' ', '] {'), $new_settings['custom_menu']);
	
	
	/* Now let's get generic. */
	foreach($default_dashboard as $option => $prop) {
		if($new_settings[$option] === 'An') {
			/* Must be a ticked checkbox. */
			$new_settings[$option] = '1';
		}
	
		if( ! isset($new_settings[$option])) {
			if($prop['default'] === '1' || $prop['default'] === '0') {
				/* Must be an unticked checkbox. */
				$new_settings[$option] = '0';
			} else {
				/* Did someone forget to add the input? */
				$new_settings[$option] = $prop['default'];
			}
		}
		
		$setting_length = strlen($new_settings[$option]);
		if(isset($prop['max_length']) && $setting_length > $prop['max_length']) {
			error::add('Die Länge von "'.$option.'" hat '.$prop['max_length'].' überschritten.');
		}
		
		/* Validate the option against its type. */
		if(isset($prop['type'])) {
			if($prop['type'] === 'int') {
				if($new_settings[$option] === '') {
					$new_settings[$option] = $prop['default'];
				} else if( ! ctype_digit($new_settings[$option]) || $setting_length > 25) {
					error::add('"'.$option.'" sieht nicht nach einem Integer aus.');
				}
			}
			if($prop['type'] === 'bool' && $new_settings[$option] !== '1' && $new_settings[$option] !== '0') {
				error::add('"'.$option.'" ist kein gültiges boolean.');
			}
		}
	}
	
	if(error::valid()) {
		$update_count = 0;
		foreach($default_dashboard as $option => $prop) {
			if($option === 'memorable_password' && $new_settings[$option] === '') {
				continue;
			}
			
			if($new_settings[$option] != $_SESSION['settings'][$option]) {
				/**
				 * We use addslashes() because PDO's quote() function adds surrounding quotes; anyway, the option
				 * names aren't based on user input.
				 */
				 $db->q
				 (
					'INSERT INTO user_settings 
						(uid, ' . addslashes($option) . ') VALUES 
						(?, ?) 
					ON DUPLICATE KEY 
						UPDATE ' . addslashes($option) . ' = ?', 
					$_SESSION['UID'], $new_settings[$option], $new_settings[$option]
				);
				 $update_count++;
			}
		}
		
		/* Refresh our settings. */
		load_settings();
		$_SESSION['notice'] = $update_count . ' Einstellung' . ($update_count === 1 ? '' : 's') . ' gespeichert.';
	}
}

error::output();
?>
<form action="" method="post">
	<?php csrf_token() ?>
	<div>
		<label class="common" for="memorable_name">Name</label>
		<input type="text" id="memorable_name" name="form[memorable_name]" class="inline" value="<?php echo htmlspecialchars($_SESSION['settings']['memorable_name']) ?>" maxlength="100" size="20" />
	</div>
	<div>
		<label class="common" for="memorable_password">Passwort</label>
		<input type="password" class="inline" id="memorable_password" name="form[memorable_password]" maxlength="100" size="20" /> <?php if(!empty($_SESSION['settings']['memorable_password'])) echo '<em>Speichern</em>'; ?>
		
		<p class="caption">Diese Information dient nur dazu, deine <a href="<?php echo DIR; ?>restore_ID">ID wiederherzustellen</a>. Auf die Anonymität beim posten hat sie keinen Einfluss.</p>
	</div>
	
	<div class="row">
		<label class="common" for="e-mail">E-Mail-Adresse</label>
		<input type="text" id="e-mail" name="form[email]" class="inline" value="<?php echo htmlspecialchars($_SESSION['settings']['email']) ?>"  size="35" maxlength="100" />
		
		<p class="caption">Wird benutzt, wenn du deine <a href="<?php echo DIR; ?>recover_ID_by_email">ID per Mail</a> bekommen möchtest.</p>
	</div>
	
	<div class="row">
		<label class="common" for="style" class="inline">Stylesheet</label>
		<select id="style" name="form[style]" class="inline">
        <?php
		$master_style = ($_SESSION['settings']['custom_style'] ? $_SESSION['settings']['custom_style'] : $_SESSION['settings']['style']);
		foreach($stylesheets as $style) {
			echo '<option value="'.htmlspecialchars($style).'"' . ($master_style == $style ? ' ausgewählt' : '') . '>' .htmlspecialchars($style) . (DEFAULT_STYLESHEET == $style ? ' (standard)' : '') . '</option>';
		}
		foreach($custom_styles as $id => $style) {
			echo '<option value="custom:' . (int) $id . '"' . ($master_style == $id ? ' ausgewählt' : '') . '>' . htmlspecialchars($style['title']) . ' (benutzerdefiniert)</option>';
		}
		?>
		</select>
		<p class="caption">Verändere das Erscheinungsbild des Forums. Du kannst eigene Stylesheets aus der <a href="<?php echo DIR ?>theme_gallery">Gallerie auswählen</a>. Die "Gmail"-Stile haben unterschiedlich eingefärbte Benutzernamen.</p>
	</div>
		
	<div class="row">
		<label class="common" for="topics_mode" class="inline">Fäden sortieren nach:</label>
		<select id="topics_mode" name="form[topics_mode]" class="inline">
			<option value="0"<?php if($_SESSION['settings']['topics_mode'] == 0) echo ' selected' ?>>Letztem Beitrag (Standard)</option>
			<option value="1"<?php if($_SESSION['settings']['topics_mode']) echo ' selected' ?>>Erstellungsdatum</option>
		</select>
		<p class="caption">Nach welcher Reihenfolge sollen Fäden aufgelistet werden?</p>
	</div>

	<div class="row">
		<label class="common" for="custom_menu">Benutzerdefiniertes Menü</label>
		<textarea id="custom_menu" name="form[custom_menu]" class="inline" rows="2" style="width:60%;"><?php echo htmlspecialchars($_SESSION['settings']['custom_menu']) ?></textarea>
		
		<p class="caption"><strong>Standardoptionen</strong> (zum hinzufügen klicken): 
<?php
		foreach($template->menu_options as $text => $path):
			$text = str_replace('Neuer Faden', 'New_topic', $text);
?>
			· <span onclick="document.getElementById('custom_menu').value += ' <?php echo $text ?>';"><?php echo $text ?></span> 
<?php
		endforeach;
?>
		</p>
		
	<p class="caption">Neben den Standardmenüpunkten kannst du eigene Links in der Form <kbd>[/dashboard Mein Kontrollzentrum]</kbd> oder <kbd>[http://google.de Google]</kbd> einstellen. Du kannst auch eigene Untermenüs hinzufügen, wenn du sie in geschweifte Klammern setzt. Beispiel: <kbd>[/normal Normal [/Übergeordnet Übergeordnet] {[/unterpunkt1 Unterpunkt 1 [http://google.de Unterpunkt 2]}</kbd>".</p>
</div>
	
	<div class="row">
		<label class="common" for="posts_per_page" class="inline">Posts per topic page</label>
		<input id="posts_per_page" name="form[posts_per_page]" class="inline" value="<?php echo htmlspecialchars($_SESSION['settings']['posts_per_page']) ?>"  size="5" maxlength="4" />
	<p class="caption">Die maximale Anzahl von Antworten pro Seite eines Fadens. Wenn du 0 angibst, gibt es keine Obergrenze mehr und alles wird auf einer Seite angezeigt. Benutzer können das in ihren Einstellungen ändern.</p>
</div>
	
	<div class="row">
		<label class="common" for="snippet_length" class="inline">Auszugslänge</label>
		<select id="snippet_length" name="form[snippet_length]" class="inline">
			<option value="80"<?php if($_SESSION['settings']['snippet_length'] == 80) echo ' selected' ?>>80 (Standard)</option>
			<option value="100"<?php if($_SESSION['settings']['snippet_length'] == 100) echo ' selected' ?>>100</option>
			<option value="120"<?php if($_SESSION['settings']['snippet_length'] == 120) echo ' selected' ?>>120</option>
			<option value="140"<?php if($_SESSION['settings']['snippet_length'] == 140) echo ' selected' ?>>140</option>
			<option value="160"<?php if($_SESSION['settings']['snippet_length'] == 160) echo ' selected' ?>>160</option>
		</select>
		<p class="caption">Wie lang (in Zeichen) sollten Auszüge aus Beiträgen (die bei Zitatlinks, im Spoilermodus und anderen Stellen auftauchen) sein? </p>
	</div>
	
	<div class="row">
		<label class="common" for="spoiler_mode">Spoilermodus</label>
		<input type="checkbox" id="spoiler_mode" name="form[spoiler_mode]" value="1" class="inline"<?php if($_SESSION['settings']['spoiler_mode']) echo ' checked="checked"' ?> />
		<p class="caption">Auszüge aus Beiträgen werden in der Fadenliste angezeigt. Sollte nur mit einem sehr großen Bildschirm aktiviert werden. </p>
	</div>
	<div class="row">
		<label class="common" for="ostrich_mode">Vogel Strauss-Modus</label>
		<input type="checkbox" id="ostrich_mode" name="form[ostrich_mode]" value="1" class="inline"<?php if($_SESSION['settings']['ostrich_mode']) echo ' checked="checked"' ?> />
		
		<p class="caption">Beiträge mit Einträgen aus deiner <a href="<?php echo DIR; ?>edit_ignore_list">Ignoreliste</a> werden versteckt. Du kannst du bestimmte Bilder mit dem "Bild verstecken"-Link ignorieren.</p>
	</div>
	
	<div class="row">
		<label class="common" for="celebrity_mode">Promi-Modus</label>
		<input type="checkbox" id="celebrity_mode" name="form[celebrity_mode]" value="1" class="inline"<?php if($_SESSION['settings']['celebrity_mode']) echo ' checked="checked"' ?> />
		
		<p class="caption">Die Namen der Fadenteilnehmer wird in der Liste angezeigt.</p>
	</div>
	
	<div class="row">
		<label class="common" for="text_mode">Textmodus</label>
		<input type="checkbox" id="text_mode" name="form[text_mode]" value="1" class="inline"<?php if($_SESSION['settings']['text_mode']) echo ' checked="checked"' ?> />
		
		<p class="caption">Alle Bilder werden versteckt. Nützlich, wenn man Datenvolumen sparen will.</p>
	</div>
	
	<div class="row" id="dash_submit">
		<input type="hidden" name="form_sent" value="1" />
		<input type="submit" value="Save settings" class="inline" />
		<input type="submit" name="revert" value="Zurücksetzen" onclick="return confirm('Wirklich alle Einstellungen zurücksetzen? Du behältst deine ID, aber alle Einstellungen gehen verloren.')"  class="inline"/>
	</div>
</form>
<?php
$template->render();
?>
