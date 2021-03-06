<?php
function load_class($class) {
	require SITE_ROOT . '/includes/class.' . strtolower($class) . '.php';
}

function stripslashes_from_array(&$array) {
	foreach ($array as $key => $value) {
		if (is_array($value)) {
			stripslashes_from_array($array[$key]);
		} else {
			$array[$key] = stripslashes($value);
		}
	}
}

function check_user_agent($type) {
	$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
	if($type == 'bot') {
		// Matches popular bots
		if(preg_match('/googlebot|adsbot|yahooseeker|yahoobot|bingbot|watchmouse|pingdom\.com|feedfetcher-google/', $user_agent)) {
			return true;
		}
	} else if($type == 'mobile') {
		// Matches popular mobile devices that have small screens and/or touch inputs
		if (preg_match('/phone|iphone|itouch|ipod|symbian|android|htc_|htc-|palmos|blackberry|opera mini|mobi|windows ce|nokia|fennec|hiptop|kindle|mot |mot-|webos\/|samsung|sonyericsson|^sie-|nintendo|mobile/', $user_agent)) {
			return true;
		}
	}
	return false;
}

function check_proxy($ip) {
	$ip = implode('.', array_reverse( explode('.', $ip) ));
	return ( gethostbyname($ip . '.rbl.efnetrbl.org') == '127.0.0.1' || gethostbyname($ip . '.niku.2ch.net') == '127.0.0.2' || gethostbyname($ip . '.80.208.77.188.166.ip-port.exitlist.torproject.org') == '127.0.0.2');
}

function hash_password($password) {
	for($i = 0; $i < STRETCH; ++$i) {
		if(USE_SHA256) {
			$password = hash('SHA256', SALT . $password);
		} else {
			$password = sha1(SALT . $password);
		}
	}
	return $password;
}

/* Converts 'user#tripcode' into array('user', '!3GqYIJ3Obs'). By AC. */
function tripcode($name_input) {
	$t = explode('#', $name_input);
	
	$name = $t[0];
	if (isset($t[1]) || isset($t[2])) {
		$trip = ((strlen($t[1]) > 0) ? $t[1] : $t[2]);
		if (function_exists ( 'mb_convert_encoding' )) {
			mb_substitute_character('none');
			$recoded_cap = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');
		}
		$trip = ( ! empty($recoded_cap) ? $recoded_cap : $trip );
		$salt = substr($trip.'H.', 1, 2);
		$salt = preg_replace('/[^\.-z]/', '.', $salt);
		$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');
		if(isset($t[2])) {
			// secure
			$trip = '!!' . substr(crypt($trip, TRIP_SEED), (-1 * 10));
		} else {
			// insecure
			$trip = '!' . substr(crypt($trip, $salt), (-1 * 10));
		}
	}
	return array($name, $trip);
}

function create_id() {
	global $db;
	if(DEFCON < 5 || check_user_agent('bot')) {
		return false;
	}

	if(RECAPTCHA_ENABLE) {
		$res = $db->q('SELECT COUNT(*) FROM users WHERE ip_address = ? AND first_seen > (? - 3600)', $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME']);
		$uids_recent = $res->fetchColumn();
		if($uids_recent > RECAPTCHA_MAX_UIDS_PER_HOUR) { 
			show_captcha('Bitte aktiviere Cookies.');
		}
	}
		
	$user_id = uniqid('', true);
	$password = generate_password();
	
	$db->q
	(
		'INSERT INTO users 
		(uid, password, ip_address, first_seen, last_seen) VALUES 
		(?, ?, ?, ?, ?)', 
		$user_id, $password, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME']
	);
	
	$_SESSION['first_seen'] = $_SERVER['REQUEST_TIME'];
	setcookie('UID', $user_id, $_SERVER['REQUEST_TIME'] + 315569260, '/');
	setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/');
	$_SESSION['UID'] = $user_id;
	$_SESSION['topic_visits'] = array();
	$_SESSION['post_count'] = 0;
	$_SESSION['notice'] = m('Hinweis: Willkommen', SITE_TITLE);
}

function generate_password() {
	$characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
	$password = '';

	for($i = 0; $i < 32; ++$i) {
		$password .= $characters[array_rand($characters)];
	}
	return $password;
}

function activate_id($uid, $password) {
	global $db;
	if( ! empty($_SESSION['UID']) && $uid === $_SESSION['UID']) {
		// We're already logged in.
		$_SESSION['ID_activated'] = true;
		return true;
	}
	
	$res = $db->q('SELECT password, first_seen, topic_visits, namefag, post_count FROM users WHERE uid = ?', $uid);
	list($db_password, $first_seen, $topic_visits, $name, $post_count) = $res->fetch();
	
	if( ! empty($db_password) && $password === $db_password) {
		// The password is correct!
		$_SESSION['UID'] = $uid;
		// Our ID wasn't just created.
		$_SESSION['ID_activated'] = true;
		// For post.php
		$_SESSION['first_seen'] = $first_seen;
		// Our last name and tripcode
		$_SESSION['poster_name'] = $name;
		// Turn topic visits into an array
		$_SESSION['topic_visits'] = json_decode($topic_visits, true);
		$_SESSION['post_count'] = $post_count;
		
		// Set cookie
		if($_COOKIE['UID'] !== $_SESSION['UID']) {
			setcookie('UID', $_SESSION['UID'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
			setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/');
		}
		
		return true;
	}
	// If the password was wrong, create a new ID.
	return false;
}

function force_id() {
	global $db, $perm;
	
	if( ! $_SESSION['ID_activated']) {
		error::fatal(m('Fehler: keine ID'));
	}
	
	if(ALLOW_BAN_READING && ! defined('REPRIEVE_BAN')) {
		$perm->die_on_ban();
	}
	
	if($_SESSION['post_count'] < 15 && ! $_SESSION['IP_checked']) {
		$res = $db->q("SELECT COUNT(*) FROM whitelist WHERE uid = ?", $_SESSION['UID']);
		$is_whitelisted = $res->fetchColumn();
		if( ! $is_whitelisted) {
			if(check_proxy($_SERVER['REMOTE_ADDR'])) {
				if( show_captcha('Du benutzt anscheinend ein Proxy ('.htmlspecialchars($_SERVER['REMOTE_ADDR']).'). Bitte bearbeite das folgende CAPTCHA. (Wenn du schon eine gültige ID hattest, <a href="'.DIR.'restore_ID">stell sie wieder her</a>.)') ) {
					$_SESSION['IP_checked'] = true;
					$db->q('INSERT INTO whitelist (uid) VALUES (?)', $_SESSION['UID']);
				}
			}
			else {
				$_SESSION['IP_checked'] = true;
			}
		
		}
		
	}
}

function load_settings() {
	global $db;
	
	/* Fetch default settings (always needed in case a custom setting is null) */
	$settings = array();
	
	/* Contains $default_dashboard */
	require SITE_ROOT . '/config/default_dashboard.php';
	
	foreach($default_dashboard as $option => $properties) {
		$settings[$option] = $properties['default'];
	}
	
	/* Fetch custom settings from database */
	if(isset($_SESSION['UID'])) {
		$res = $db->q('SELECT * FROM user_settings WHERE uid = ?', $_SESSION['UID']);
		$custom_settings = $res->fetch(PDO::FETCH_ASSOC);
		
		if(is_array($custom_settings)) {
			/* Unset null values (options that haven't been set by the user) */
			$custom_settings = array_filter($custom_settings, 'is_string');
		
			/* Overwrite default settings */
			$settings = array_merge($settings, $custom_settings);
		}
	}

	$_SESSION['settings'] = $settings;
}

function show_captcha($message) {
	global $template;
	
	if(isset($_SESSION['is_human'])) {
		return true;
	}
	
	$template->title = 'CAPTCHA';
	require_once 'includes/recaptcha.php';
	
	if($_POST['recaptcha_response_field']) {
		$resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);

        if ($resp->is_valid) {
			$_SESSION['is_human'] = true;
			return true;
        } else {
			$error = $resp->error;
        }
	}
	if(empty($message)) {
		$message = m('CAPTCHA-Vorspann');
	}
	echo '<p>'.$message.'</p>';
	echo '<form action="" method="post">';
	echo recaptcha_get_html(RECAPTCHA_PUBLIC_KEY, $error);
	echo '<input type="submit" value="Weiter" />';
	foreach($_POST as $k => $v) { // Let the user resume as intended
		if($k == 'recaptcha_challenge_field' || $k == 'recaptcha_response_field') {
			continue;
		}
		if(is_array($v)) {
			foreach($v as $nk => $nv) {
				echo '<input type="hidden" name="'.htmlspecialchars($k).'['.htmlspecialchars($nk).']" value="'.htmlspecialchars($nv).'" />';
			}
		} else {
			echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'" />';
		}
	}
	
	$template->render();
	exit();
}

function update_activity($action_name, $action_id = '') {
	global $db;
	
	if( ! isset($_SESSION['UID'])) {
		return false;
	}
	
	$db->q
	(
		'INSERT INTO activity 
		(time, uid, action_name, action_id) VALUES 
		(?, ?, ?, ?) ON DUPLICATE KEY UPDATE time = ?, action_name = ?, action_id = ?',
		$_SERVER['REQUEST_TIME'], $_SESSION['UID'], $action_name, $action_id, $_SERVER['REQUEST_TIME'], $action_name, $action_id
	);
}

function id_exists($id) {
	global $db;

	$uid_exists = $db->q('SELECT 1 FROM users WHERE uid = ?', $id);
	return (bool) $uid_exists->fetchColumn();
}

function is_ignored(/* ... */) { 
	global $db;
	$fields = func_get_args();

	if($_SESSION['settings']['ostrich_mode']) {
		if( ! isset($_SESSION['ignored_phrases'])) {
			$fetch_ignore_list = $db->q('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?', $_SESSION['UID']);
			$ignored_phrases = $fetch_ignore_list->fetchColumn();
			$ignored_phrases = array_filter(explode("\n", str_replace("\r", '', $ignored_phrases)));
			$_SESSION['ignored_phrases'] = $ignored_phrases;
		}
		
		// To make this work with Windows input, we need to strip out the return carriage.
		foreach($fields as $field) {
			foreach($_SESSION['ignored_phrases'] as $phrase) {
				if($phrase[0] == '/' && strlen($phrase < 28) && preg_match('|^/.+/$|', $phrase)) {
					if(preg_match($phrase, $field)) {
						return true;
					}
				}
				else if(stripos($field, $phrase) !== false) {
					return true;
				}
			}
		}
	}
	return false;
}

/* Removes whitespace bloat */
function super_trim($text) {
	static $nonprinting_characters = array
	(
		"\r",
		'­', //soft hyphen ( U+00AD)
		'﻿', // zero width no-break space ( U+FEFF)
		'​', // zero width space (U+200B)
		'‍', // zero width joiner (U+200D)
		'‌' // zero width non-joiner (U+200C)
	);
	/* Strip return carriage and non-printing characters. */
	$text = str_replace($nonprinting_characters, '', $text);
	 /* Trim and kill excessive newlines (maximum of 3). */
	return preg_replace( '/(\r?\n[ \t]*){3,}/', "\n\n\n", trim($text) );
}

/* Calculates the difference between two timestamps as a unit of time */
function age($timestamp, $comparison = null) {
	static $units = array
	(
		'Sek.' => 60,
		'Min.' => 60,
		'Std.' => 24,
		'Tg.' => 7,
		'Wo.' => 4.25, 
		'Mo.' => 12
	);
	if(is_null($comparison)) {
		$comparison = $_SERVER['REQUEST_TIME'];
	}
	$age_current_unit = abs($comparison - $timestamp);
	foreach($units as $unit => $max_current_unit) {
		$age_next_unit = $age_current_unit / $max_current_unit;
		if($age_next_unit < 1) { // Are there enough of the current unit to make one of the next unit?
			$age_current_unit = floor($age_current_unit);
			$formatted_age = $age_current_unit . ' ' . $unit;
			return $formatted_age . ($age_current_unit == 1 ? '' : '');
		}
		$age_current_unit = $age_next_unit;
	}

	$age_current_unit = round($age_current_unit, 1);
	$formatted_age = $age_current_unit . ' Jahr';
	return $formatted_age . (floor($age_current_unit) == 1 ? '' : 'e');	
}

function format_date($timestamp) {
	return date('j.n.Y. G:i:s', $timestamp);
}

function format_number($number) {
	if($number == 0) {
		return '-';
	}
	return number_format($number);
}

function format_name($name, $tripcode, $link = null, $poster_number = null, $shorthand = false) {
	static $anonymous;
	
	if( ! isset($anonymous)) {
		$anonymous = m('Anonym');
	}
	
	if(empty($name) && empty($tripcode)) {
		$formatted_name = $anonymous;
		if(isset($poster_number)) {
			$formatted_name .= ' <strong>' . number_to_letter($poster_number) . '</strong>';
		}
	} else {
		if(empty($name)) {
			$formatted_name = $tripcode;
		} else {
			$formatted_name = '<strong>' . htmlspecialchars($name) . '</strong>';
			if( ! empty($link)) {
				$formatted_name = '<a href="' . DIR . htmlspecialchars($link) . '">' . $formatted_name . '</a>';
			}
		
			if( ! $shorthand) {
				$formatted_name .= ' ' . $tripcode;
			} else if($tripcode) {
				$formatted_name = '<span class="help" title="'.$tripcode.'">' . $formatted_name . '</span>';
			}
		}
	}
	
	return $formatted_name;
}

function number_to_letter($number) {
	static $alphabet;
	
	if( ! isset($alphabet)) {
		$alphabet = range('A', 'Y');
	}
	
	if($number < 24) {
		return $alphabet[$number];
	}
	$number = $number - 23;
	return 'Z-' . $number;
}

/* Remember to htmlspecialchars() the headline before passing it to this function. */
function format_headline($headline, $id, $reply_count, $poll, $locked, $sticky) {
	$headline = '<a href="'.DIR.'topic/' . $id . page($reply_count) . '"' . (isset($_SESSION['topic_visits'][$id]) ? ' class="visited"' : '') . '>' . $headline . '</a>';
		
	if($poll) {
		$headline .= ' <span class="poll_marker">(Abstimmung)</span>';
	}
		
	if($_SESSION['settings']['posts_per_page'] && $reply_count > $_SESSION['settings']['posts_per_page']) {
		$headline .= ' <span class="headline_pages">[';
		for($i = 1, $comma = '', $pages = ceil($reply_count / $_SESSION['settings']['posts_per_page']); $i <= $pages; ++$i) {
			$headline .= $comma . '<a href="'.DIR.'topic/'.$id. '/' .$i.'">' . number_format($i) . '</a>';
			$comma = ', ';
		}
		$headline .= ']</span>';
	}
		
	$headline .= '<small class="topic_info">';
	if($locked) {
		$headline .= '[GESCHLOSSEN]';
	}
	if($sticky) {
		$headline .= ' [STICKY]';
	}
	$headline .= '</small>';
	
	return $headline;
}

function replies($topic_id, $topic_replies) {
	$output = format_number($topic_replies);
	
	if( ! isset($_SESSION['topic_visits'][$topic_id])) {
		$output = '<strong>' . $output . '</strong>';
	} else if($_SESSION['topic_visits'][$topic_id] < $topic_replies) {
		$output .= ' <span class="new_replies">(<a href="' . DIR . 'topic/' . $topic_id . page($topic_replies, $_SESSION['topic_visits'][$topic_id] + 1) . '#new">';
		$new_replies = $topic_replies - $_SESSION['topic_visits'][$topic_id];
		if($new_replies != $topic_replies) {
			$output .= '<strong>' . $new_replies . '</strong> ';
		} else {
			$output .= 'all-';
		}
		$output .= 'neu</a>)</span>';
	}
	
	return $output;
}

/**
 * Returns the part of a topic URL indicating the page.
 * @param int $total_replies The topic's total reply count.
 * @param int $reply_number  The reply number (not ID), counting from 1 at the beginning of the topic.
 */
function page($total_replies, $reply_number = null) {
	if( ! $_SESSION['settings']['posts_per_page'] || $total_replies <= $_SESSION['settings']['posts_per_page']) {
		/* Pagination is either disabled or unnecessary for this topic. */
		return '';
	}
	
	if(empty($reply_number)) {
		return '/1';
	}
	
	return '/' . ceil($reply_number / $_SESSION['settings']['posts_per_page']);
}

// To redirect to index, use redirect($notice, ''). To redirect back to referrer, 
// use redirect($notice). To redirect to /topic/1,  use redirect($notice, 'topic/1')
function redirect($notice = null, $location = null) {
	if( ! empty($notice)) {
		$_SESSION['notice'] = $notice;
	}
	
	if(is_null($location) && ! empty($_SERVER['HTTP_REFERER'])) {
		$location = $_SERVER['HTTP_REFERER'];
	}
	
	if(substr($location, 0, strlen(URL)) == URL){
		$location = substr($location, strlen(URL));
	}
	
	if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
		$_SESSION['redirected_by_ajax'] = true;
	}
	
	header('Location: ' . URL . $location);
	exit;
}

function check_length($text, $name, $min_length, $max_length) {
	$text_length = strlen($text);

	if($min_length > 0 && empty($text)) {
		error::add('' . $name . ' kann nicht leer sein.');
	} else if($text_length > $max_length) {
		error::add('' . $name . ' war ' . number_format($text_length - $max_length) . ' Zeichen zu lang (' . number_format($max_length) . ').');
	} else if($text_length < $min_length) {
		error::add('' . $name . ' war zu kurz.');
	}
}

function csrf_token() { // Prevent cross-site redirection forgeries, create token.
	if( ! isset($_SESSION['token'])) {
		$_SESSION['token'] = md5(SALT . mt_rand());
	}
	echo '<input type="hidden" name="CSRF_token" value="' . $_SESSION['token'] . '" class="noscreen" />' . "\n";
}

function check_token() { // Prevent cross-site redirection forgeries, token check.
	if($_POST['CSRF_token'] !== $_SESSION['token']) {
		error::add(m('Fehler: ungültiger Token'));
		return false;
	}
	return true;
}

/* Soft delets a topic with logging and notification */
function delete_topic($id, $notify = true) {
	global $db, $perm;
	
	$res = $db->q('SELECT author, namefag, tripfag FROM topics WHERE id = ?', $id);
	list($author_id, $author_name, $author_trip) = $res->fetch();
	$author_name = trim($author_name . ' ' . $author_trip);
			
	if($perm->is_admin($author_id) && $_SESSION['UID'] != $author_id) {
		error::fatal(m('Fehler: Zugriff verweigert.'));
	}
			
	/* Dump the image. */
	delete_image('topic', $id);
			
	/**
	 * Delete images of any replies. We keep the replies themselves for two reasons:
	 * - The topic author should be able to read non-deleted replies to their deleted topic.
	 * - It would be more difficult to reverse mod abuse otherwise.
	 */
	$reply_list = $db->q('SELECT id FROM replies WHERE parent_id = ?', $id);
	while($reply_id = $reply_list->fetchColumn()) {
		delete_image('reply', $reply_id);
	}

	$db->q("UPDATE topics SET deleted = '1' WHERE id = ?", $id);
	$db->q("DELETE FROM reports WHERE post_id = ? AND type = 'topic'", $id);
	$db->q("DELETE FROM citations WHERE topic = ?", $id);
	log_mod('delete_topic', $id, $author_name);
			
	if($author_id != $_SESSION['UID'] && $notify) {
		system_message($author_id, m('PN: gelöschter Faden.', $id));
	}
}

/* Soft deletes a reply with logging and notification */
function delete_reply($id, $notify = true) {
	global $db, $perm;
	
	$res = $db->q('SELECT author, namefag, tripfag, time FROM replies WHERE id = ?', $id);
	list($author_id, $author_name, $author_trip, $reply_time) = $res->fetch();
	$author_name = trim($author_name . ' ' . $author_trip);
			
	if($perm->is_admin($author_id) && $_SESSION['UID'] != $author_id) {
		error::fatal(m('Fehler: Zugriff verweigert.'));
	}
			
	$res = $db->q('SELECT parent_id, time FROM replies WHERE id = ?', $id);
	list($parent_id, $reply_time) = $res->fetch();

	if( ! $parent_id) {
		error::fatal('Antwort existiert nicht.');
	} else {
		delete_image('reply', $id);
	}
			
	$db->q("UPDATE replies SET deleted = '1' WHERE id = ?", $id);
	$db->q("DELETE FROM reports WHERE post_id = ? AND type = 'reply'", $id);
	$db->q("DELETE FROM citations WHERE reply = ?", $id);
						
	/* Reduce the parent's reply count. */
	$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $parent_id);
	
	/* Check if we need to fix the bump time */
	$res = $db->q('SELECT last_post, replies FROM topics WHERE id = ?', $parent_id);
	list($topic_bump, $topic_replies) = $res->fetch();
	if( ! $topic_replies) {
		$db->q('UPDATE topics SET last_post = time WHERE id = ?', $parent_id);
	} else if($topic_bump == $reply_time) {
		$db->q('UPDATE topics SET last_post = (SELECT time FROM replies WHERE parent_id = ? AND deleted = 0 ORDER BY time DESC LIMIT 1) WHERE id = ?', $parent_id, $parent_id);
	}
	
	log_mod('delete_reply', $id, $author_name);
			
	if($author_id != $_SESSION['UID'] && $notify) {
		system_message($author_id, m('PN: gelöschte Nachricht', $id));
	}
}

/* Removes an image from the database, and, if no other posts use it, from the file system */
function delete_image($mode = 'reply', $post_id, $hard_delete = false) {
	global $db;
	
	if($mode != 'reply' && $mode != 'topic') {
		error::fatal('Ungültiger Befehl für das Bild.');
	}
	
	$img = $db->q('SELECT COUNT(*), file_name FROM images WHERE md5 = (SELECT md5 FROM images WHERE '.$mode.'_id = ? LIMIT 1) AND deleted = 0', $post_id);
	list($img_usages, $img_filename) = $img->fetch();
	if($img_filename) {
		if($img_usages == 1) {
			/* Only one post uses this image. Delete the file. */
			if(file_exists(SITE_ROOT . '/img/' . $img_filename)) {
				unlink(SITE_ROOT . '/img/' . $img_filename);
			}
			if(file_exists(SITE_ROOT . '/thumbs/' . $img_filename)) {
				unlink(SITE_ROOT . '/thumbs/' . $img_filename);
			}
		}
		
		if($hard_delete) {
			$db->q('DELETE FROM images WHERE '.$mode.'_id = ? AND file_name = ? LIMIT 1', $post_id, $img_filename);
		} else {
			$db->q('UPDATE images SET deleted = 1 WHERE '.$mode.'_id = ? AND file_name = ? LIMIT 1', $post_id, $img_filename);
		}
	}
}

/* Logs a moderator action. */
function log_mod($action, $target, $param = '', $reason = '', $mod = null) {
	global $db;
	
	if( ! isset($mod)) {
		$mod = $_SESSION['UID'];
	}
	
	switch ($action) {
		case 'delete_image':
		case 'delete_topic':
		case 'delete_reply':
		case 'delete_bulletin':
		case 'undelete_topic':
		case 'undelete_reply':
		case 'nuke_ip':
		case 'nuke_id':
			$type = 'delete';
		break;
			
		case 'edit_topic':
		case 'edit_reply':
			$type = 'edit';
		break;
		
		case 'ban_ip':
		case 'ban_uid':
		case 'ban_cidr':
		case 'ban_wild':
			$type = 'ban';
		break;
		
		case 'unban_ip':
		case 'unban_uid':
		case 'unban_cidr':
		case 'unban_wild':
			$type = 'unban';
		break;
			
		case 'stick_topic':
		case 'unstick_topic':
			$type = 'stick';
		break;
		
		case 'lock_topic':
		case 'unlock_topic':
			$type = 'lock';
		break;
		
		case 'cms_new':
		case 'cms_edit':
		case 'delete_page':
		case 'undelete_page':
			$type = 'cms';
		break;
		
		case 'merge':
		case 'unmerge':
			$type = 'merge';
		break;
		
		case 'db_maintenance':
			$type = 'system';
		break;
		
		default:
			$type = $action;
	}
	
	$db->q
	(
		'INSERT INTO mod_actions 
		(action, type, target, mod_uid, mod_ip, reason, param, time) VALUES 
		(?, ?, ?, ?, ?, ?, ?, ?)',
		$action, $type, $target, $mod, $_SERVER['REMOTE_ADDR'], $reason, $param, $_SERVER['REQUEST_TIME']
	);
}

/* Sends a PM from the board. */
function system_message($uid, $message) {
	global $db;

	$db->q
	(
			'INSERT INTO private_messages 
			(source, destination, contents, parent, time) VALUES 
			(?, ?, ?, ?, ?)',
			'system', $uid, $message, '0', $_SERVER['REQUEST_TIME']
	);
	if($new_id = $db->lastInsertId()) {
		$db->q('UPDATE private_messages SET parent = ? WHERE id = ?', $new_id, $new_id);
		$db->q('INSERT INTO pm_notifications (uid, pm_id, parent_id) VALUES (?, ?, ?)', $uid, $new_id, $new_id);
		
		return true;
	}
	
	return false;
}

/* Gets an array of stylesheet names */
function get_styles() {
	$styles =  glob(SITE_ROOT . '/style/themes/*.css');
	foreach($styles as $key => $path) {
		$styles[$key] = basename($path, '.css');
	}
	return $styles;
}

?>
