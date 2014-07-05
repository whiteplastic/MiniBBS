<?php
require './includes/bootstrap.php';
force_id();
header('X-Frame-Options: SAMEORIGIN');

if(isset($_POST['confirm'])) {
	if( ! check_token()) {
		error::fatal('Sitzung ist abgelaufen.');
	}
}

// Take the action.
switch($_GET['action']) {
	// Normal actions.
	case 'watch_topic':
	
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Faden beobachten';
		
		if(isset($_POST['confirm'])) {
			$check_watchlist = $db->q('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $id);
			if( ! $check_watchlist->fetchColumn()) {
				$db->q('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)', $_SESSION['UID'], $id);
			}
			redirect('Faden wurde der Beobachtungsliste hinzugefügt');
		}
		
	break;
	
	case 'unwatch_topic':
	
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID');
		}
		
		$id = $_GET['id'];
		$template->title = 'Faden nicht mehr beobachten';
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $id);
			redirect('Faden wurde von der Beobachtungsliste entfernt');
		}
		
	break;
	
	case 'hide_image':
		if(strlen($_GET['id']) != 32) {
			error::fatal('Das sieht nicht nach einem gültigen MD5-Hash aus.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Bild verstecken.';
		
		if(isset($_POST['confirm'])) {
			$db->q('INSERT INTO ignore_lists (uid, ignored_phrases) VALUES (?, ?) ON DUPLICATE KEY UPDATE ignored_phrases = CONCAT(ignored_phrases, ?)', $_SESSION['UID'], $id, "\n" . $id);
			unset($_SESSION['ignored_phrases']);
			redirect('Bild wird ignoriert.');
		}
	break;
	
	case 'unhide_image':
		if(strlen($_GET['id']) != 32) {
			error::fatal('Das sieht nicht nach einem gültigen MD5-Hash aus.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Bild aufdecken.';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT ignored_phrases FROM ignore_lists WHERE uid = ?', $_SESSION['UID']);
			$ignored_phrases = str_replace($id, '', $res->fetchColumn());
			$ignored_phrases = preg_replace("/\n\r?\n/", "\n", $ignored_phrases);
			$db->q('UPDATE ignore_lists SET ignored_phrases = ? WHERE uid = ?', $ignored_phrases, $_SESSION['UID']);
			unset($_SESSION['ignored_phrases']);
			
			redirect('Bild wird nicht mehr ignoriert.');
		}
	break;
	
	case 'cast_vote':
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Stimme abgeben';
		
		if($_POST['show_results']) {
			$option_id = null;
		} else {
			if( ! ctype_digit($_POST['option_id'])) {
				redirect("Keine Option ausgewählt.", 'topic/' . $id);
			}
			$option_id = (int) $_POST['option_id'];
		}

		if(check_token()) {
			$check_votes = $db->q('SELECT 1 FROM poll_votes WHERE (ip = ? OR uid = ?) AND parent_id = ?', $_SERVER['REMOTE_ADDR'], $_SESSION['UID'], $id);
			if( ! $check_votes->fetchColumn()) {
				$db->q('INSERT INTO poll_votes (uid, ip, parent_id, option_id) VALUES (?, ?, ?, ?)', $_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $id, $option_id);
				if( ! is_null($option_id)) {
					$db->q('UPDATE poll_options SET votes = votes + 1 WHERE id = ?', $option_id);
					redirect(m('Hinweis: abgestimmt'), 'topic/' . $id);
				} else {
					redirect(null, 'topic/' . $id);
				}
			}
			else {
				error::fatal('Du hast hier bereits abgestimmt.');
			}
		}
	break;
	
	case 'revert_change':
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Änderung rückgängig machen';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT type, foreign_key, text FROM revisions WHERE id = ?', $id);
			if( ! $res) {
				error::fatal('Keine Revision mit der ID gefunden');
			}
			$revision = $res->fetchObject();
			
			switch($revision->type) {
				case 'page':
					if( ! $perm->get('cms')) {
						error::fatal(m('Fehler: zugriff verweigert.'));
					}
					
					$previous = $db->q('SELECT content FROM pages WHERE id = ?', $revision->foreign_key);
					$db->q('UPDATE pages SET content = ? WHERE id = ?', $revision->text, $revision->foreign_key);
					$redirect = 'CMS';
				break;
				
				case 'topic':
					if( ! $perm->get('edit_others')) {
						error::fatal(m('Fehler: Zugriff verweigert'));
					}

					$previous = $db->q('SELECT body FROM topics WHERE id = ?', $revision->foreign_key);
					$db->q('UPDATE topics SET body = ? WHERE id = ?', $revision->text, $revision->foreign_key);
					$redirect = 'topic/' . $revision->foreign_key;
				break;
				
				case 'reply':
					if( ! $perm->get('edit_others')) {
						error::fatal(m('Fehler: Zugriff verweigert'));
					}

					$previous = $db->q('SELECT body FROM replies WHERE id = ?', $revision->foreign_key);
					$db->q('UPDATE replies SET body = ? WHERE id = ?', $revision->text, $revision->foreign_key);
					$redirect = 'reply/' . $revision->foreign_key;
				break;
			}
			
			$unreverted_text = $previous->fetchColumn();
			$db->q('INSERT INTO revisions (type, foreign_key, text) VALUES (?, ?, ?)', $revision->type, $revision->foreign_key, $unreverted_text);
			log_mod('revert_' . $revision->type, $revision->foreign_key, $db->lastInsertId());
			redirect('Änderung rückgängig gemacht.', $redirect);
		}
	break;
	
	case 'dismiss_all_PMs':
		$template->title = 'Alle Nachrichten als gelesen markieren.';
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM pm_notifications WHERE uid = ?', $_SESSION['UID']);
			redirect('Alle Nachrichten markiert.');
		}
	break;
	
	case 'ignore_pm':
		if($_GET['id'] == '*') {
			$template->title = 'Alle Nachrichten ignorieren.';
			$source = '*';
		}
		else {
			$template->title = 'Absender der Nachricht ignorieren.';
			
			if( ! ctype_digit($_GET['id'])) {
				error::fatal('Ungültige ID.');
			}
			
			$res = $db->q('SELECT source, destination FROM private_messages WHERE id = ?', $_GET['id']);
			list($source, $destination) = $res->fetch();
			
			if($destination != $_SESSION['UID']) {
				error::fatal('Du kannst nur Nachrichten ignorieren, die an dich gerichtet sind.');
			}

			if($source == 'mods' || $source == 'admins') {
				error::fatal('Diesen Benutzer kannst du nicht ignorieren.');
			}
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			 $db->q('INSERT into pm_ignorelist (uid, ignored_uid) VALUES (?, ?)', $_SESSION['UID'], $source);
			 if($id == '*') {
				// Mark all messages as read.
				$db->q('DELETE FROM pm_notifications WHERE uid = ?', $_SESSION['UID']);
			 } else {
				// Mark the offending message as ignored.
				$db->q('UPDATE private_messages SET ignored = 1 WHERE id = ?', $id);
			 }
			 redirect('Ignoreliste gespeichert.', 'private_messages');
		}
	break;
	
	case 'unignore_pm':
		if($_GET['id'] == '*') {
			$template->title = 'Nachrichten nicht mehr ignorieren.';
			$source = '*';
		}
		else {
			$template->title = 'Absender nicht mehr ignorieren';
			
			if( ! ctype_digit($_GET['id'])) {
				error::fatal('Ungültige Nachrichten-ID.');
			}
			
			$res = $db->q('SELECT source, destination FROM private_messages WHERE id = ?', $_GET['id']);
			list($source, $destination) = $res->fetch();
			
			if($destination != $_SESSION['UID']) {
				error::fatal('Die Nachricht ist nicht für dich.');
			}
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE private_messages SET ignored = 0 WHERE id = ?', $id);
			$db->q('DELETE from pm_ignorelist WHERE uid = ? AND ignored_uid = ?', $_SESSION['UID'], $source);
			 
			redirect('Ignoreliste gespeichert.', 'private_messages');
		}
	break;
	
	case 'dismiss_pm':
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		$id = $_GET['id'];
		
		if( ! $perm->get('read_mod_pms')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM pm_notifications WHERE parent_id = ?', $id);
			/* If bootstrap.php set $new_parent, we have another PM; redirect to that. */
			if(isset($new_parent)) {
				$redirect_to = 'private_message/' . $new_parent. ($new_pm != $new_parent ? '#reply_'.$new_pm : '');
			} else {
				$redirect_to = 'private_messages';
			}
			redirect('Nachricht abgelehnt, Moderatoren werden nicht länger darüber benachrichtigt', $redirect_to);
		}
	break;
	
	case 'delete_pm':
		if( ! $perm->get('delete')){
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT parent FROM private_messages WHERE id = ?', $_GET['id']);
			$parent = $res->fetchColumn();
			if($id == $parent) {
				$db->q('DELETE FROM private_messages WHERE parent = ?', $id);
				$db->q('DELETE FROM pm_notifications WHERE parent_id = ?', $id);
			}
			else {
				$db->q('DELETE FROM private_messages WHERE id = ?', $id);
				$db->q('DELETE FROM pm_notifications WHERE pm_id = ?', $id);
			}
			redirect('Nachricht gelöscht.', 'private_messages');
		}
	break;
	
	case 'delete_all_pms':
		if( ! $perm->get('delete_all_pms')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! id_exists($_GET['id'])) {
			error::fatal('Benutzer existiert nicht.');
		}
		if($perm->is_admin($_GET['id']) || ($perm->is_mod($_GET['id']) && ! $perm->is_admin())) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		$id = $_GET['id'];
		
		if(isset($_POST['confirm'])) {
			// Delete notifications and messages from source.
			$db->q('DELETE private_messages, pm_notifications FROM private_messages LEFT OUTER JOIN pm_notifications ON private_messages.id = pm_notifications.pm_id WHERE private_messages.source = ?', $id);
			redirect('Nachrichten und Benachrichtigungen gelöscht', 'profile/'.$id);
		}
	break;
	
	case 'delete_image':

		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Bild löschen.';
		
		if(isset($_GET['topic'])) {
			$type = 'reply';
			$redirect_to = 'topic/' . (int) $_GET['topic'] . '#reply_' . $id;
		} else {
			$type = 'topic';
			$redirect_to = 'topic/' . $id;
		}
		
		if($type == 'topic') {
			$res = $db->q('SELECT author, time FROM topics WHERE id = ?', $id);
		} else {
			$res = $db->q('SELECT author, time FROM replies WHERE id = ?', $id);
		}
		if( ! $res) {
			error::fatal('Es gibt keinen Beitrag mit der ID.');
		}
		
		$post = $res->fetchObject();
		
		if(isset($_POST['confirm'])) {
			if($perm->get('delete')) {
				$res = $db->q('SELECT original_name FROM images WHERE ' . $type . '_id = ?', $id);
				$original_name = $res->fetchColumn();
				log_mod('delete_image', $id, $original_name);
			} else if($post->author != $_SESSION['UID']) {
				error::fatal('Du bist nicht der Autor.');
			} else if( $perm->get('edit_limit') != 0 && ($_SERVER['REQUEST_TIME'] - $post->time > $perm->get('edit_limit')) ) {
				error::fatal('Bild kann nicht mehr gelöscht werden.');
			}

			delete_image($type, $id);
			redirect('Image deleted.', $redirect_to);
		}
	
	break;
	
	case 'delete_page':
	
		if( ! $perm->get('cms')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Seite löschen.';
		
		if(isset($_POST['confirm'])) {
			log_mod('delete_page', $id);
			$db->q('UPDATE pages SET deleted = 1 WHERE id = ?', $id);
			redirect('Seite gelöscht.', 'CMS');
		}
		
	break;
	
	case 'undelete_page':
		if( ! $perm->get('cms')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Seite wiederherstellen';	
		
		if(isset($_POST['confirm'])) {
			log_mod('undelete_page', $id);
			$db->q('UPDATE pages SET deleted = 0 WHERE id = ?', $id);
			redirect('Seite wiederhergestellt', 'CMS');
		}
	break;
	
	
	case 'delete_bulletin':
	
		if( ! $perm->get('delete')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Mitteilung löschen.';
		
		if(isset($_POST['confirm'])) {
			log_mod('delete_bulletin', $id);
			$db->q('DELETE FROM bulletins WHERE id = ?', $id);
			redirect('Mitteilung gelöscht.', 'bulletins');
		}
		
	break;
	
	case 'undo_merge':
		if( ! $perm->get('merge')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Zerteilen';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT id FROM replies WHERE original_parent = ? ORDER BY time ASC LIMIT 1', $id);
			$merged_op = $res->fetchColumn();
			
			if( ! $merged_op) {
				error::fatal('Kann keine Antworten mit dem Ursprung finden.');
			}
			
			$db->q('DELETE FROM replies WHERE id = ?', $merged_op);
			$db->q('UPDATE images SET topic_id = ? WHERE reply_id = ?', $id, $merged_op);
			$db->q('UPDATE replies SET parent_id = original_parent, original_parent = null WHERE original_parent = ?', $id);
			$db->q('UPDATE topics SET deleted = 0 WHERE id = ?', $id);
			
			log_mod('unmerge', $id);
			redirect('Faden zerteilt.', 'topic/' . $id);
		}
	break;
		
	case 'unban_uid':
	
		if( ! $perm->get('ban')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! id_exists($_GET['id'])) {
			error::fatal('Benutzer existiert nicht.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Benutzer entbannen: ' . $id;
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');

			log_mod('unban_uid', $id);
			redirect('Benutzer-ID entbannt..');
		}
		
	break;
		
	case 'unban_ip':
	
		if( ! $perm->get('ban')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP)) {
			error::fatal('Ungültige IP-Adresse');
		}
		
		$id = $_GET['id'];
		$template->title = 'IP entbannen: ' . $id;
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');
			
			log_mod('unban_ip', $id);
			redirect('IP entbannt.', 'IP_address/'.$id);
		}
		
	break;
	
	case 'unban_cidr':
		if( ! $perm->get('ban')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if($perm->get_ban_type($_GET['id']) !== 'cidr') {
			error::fatal('Keine gültige CIDR-Adresse.');
		}
		
		$id = $_GET['id'];
		$template->title = 'CIDR-Range entbannen ' . htmlspecialchars($id);
		
		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');
			
			log_mod('unban_cidr', $id);
			redirect('CIDR-Range entbannt.', 'mod_log');
		}
	break;
	
	case 'unban_wild':
		if( ! $perm->get('ban')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if($perm->get_ban_type($_GET['id']) !== 'wild') {
			error::fatal('Keine gültige Wildcard-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Wildcard-Range entbannen ' . htmlspecialchars($id);

		if(isset($_POST['confirm'])) {
			$db->q('DELETE FROM bans WHERE target = ?', $id);
			cache::clear('bans');
			
			log_mod('unban_wild', $id);
			redirect('Wildcard-Range entbannt.', 'mod_log');
		}
	break;

	case 'stick_topic':
	
		if( ! $perm->get('stick')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `sticky` = ? WHERE `topics`.`id` = ? LIMIT 1', 1, $id);
			log_mod('stick_topic', $id);
			redirect('Faden ist jetzt sticky.', 'topic/' . $id);
		}
		
	break;
	
	case 'unstick_topic':
	
		if( ! $perm->get('stick')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `sticky` = ? WHERE `topics`.`id` = ? LIMIT 1', 0, $id);
			log_mod('unstick_topic', $id);
			redirect('Faden ist nicht länger sticky.', 'topic/' . $id);
		}
		
	break;
	
	case 'lock_topic':
	
		if( ! $perm->get('lock')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Faden schließen.';
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `locked` = ? WHERE `topics`.`id` = ? LIMIT 1', 1, $id);
			log_mod('lock_topic', $id);
			redirect('Faden wurde geschlossen.', 'topic/' . $id);
		}
		
	break;
	
	case 'unlock_topic':
	
		if( ! $perm->get('lock')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Faden öffnen.';
	
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE `topics` SET `locked` = ? WHERE `topics`.`id` = ? LIMIT 1', 0, $id);
			log_mod('unlock_topic', $id);
			redirect('Faden wurde geöffnet.', 'topic/' . $id);
		}
		
	break;
	
	case 'delete_topic':
	
		if( ! $perm->get('delete')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Faden löschen.';
	
		if(isset($_POST['confirm'])) {	
			delete_topic($id);
			
			redirect('Faden archiviert und gelöscht.', '');
		}
		
	break;
	
	case 'undelete_topic':
		if( ! $perm->get('undelete')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Faden-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Faden wiederherstellen.';
		
		if(isset($_POST['confirm'])) {
			$db->q("UPDATE topics SET deleted = '0' WHERE id = ?", $id);
			log_mod('undelete_topic', $id);
			redirect('Faden wiederhergestellt.');
		}
	break;
		
	case 'delete_reply':
	
		if( ! $perm->get('delete')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Antwort-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Antowrt löschen.';
	
		if(isset($_POST['confirm'])) {
			delete_reply($id);

			redirect('Antwort archiviert und gelöscht.');
		}
		
	break;
	
	case 'undelete_reply':
		if( ! $perm->get('undelete')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige Antwort-ID.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Antwort wiederherstellen.';
		
		if(isset($_POST['confirm'])) {
			$db->q("UPDATE replies SET deleted = '0' WHERE id = ?", $id);
			log_mod('undelete_reply', $id);
			redirect('Antwort wiederhergestellt.');
		}
	break;
	
	case 'delete_ip_ids':
	
		if( ! $perm->get('delete_ip_ids')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP)) {
			error::fatal('Das ist keine gültige IP-Adresse..');
		}
		
		$id = $_GET['id'];
		$template->title = 'IDs, die zu <a href="'.DIR.'IP_address/' . $id . '">' . $id . '</a> gehören löschen?';
		
		if(isset($_POST['confirm'])) {
			$res = $db->q('SELECT uid FROM users WHERE ip_address = ?', $id);
			
			while($uid = $res->fetchColumn()) {
				if($perm->is_admin($uid)) {
					error::fatal(m('Fehler: Zugriff verweigert'));
				}
				
				if($perm->is_mod($uid) && ! $perm->is_admin()) {
					error::fatal(m('Fehler: Zugriff verweigert'));
				}
			}
			$db->q('DELETE users, user_settings FROM users LEFT OUTER JOIN user_settings ON users.uid=user_settings.uid WHERE users.ip_address = ?', $id);
			log_mod('delete_ip_ids', $id);
			redirect('IDs gelöscht.', 'IP_address/' . $id);
		}
		
	break;
	
	case 'nuke_id':
	
		if( ! $perm->get('nuke_id')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if($perm->is_admin($_GET['id'])) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if($perm->is_mod($_GET['id']) && ! $perm->is_admin()) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! id_exists($_GET['id'])) {
			error::fatal('Den Benutzer gibt es nicht.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Alle Beiträge von <a href="'.DIR.'profile/' . $id . '">' . $id . '</a> atomisieren?';
		
		if(isset($_POST['confirm'])) {
			// Delete replies.
			$fetch_parents = $db->q('SELECT parent_id, id FROM replies WHERE author = ?', $id);
			
			$victim_parents = array();
			while(list($parent_id, $reply_id) = $fetch_parents->fetch()) {
				$victim_parents[] = $parent_id;
				delete_image('reply', $reply_id);
			}
			
			// Dump images which belong to topics.
			$fetch_topics = $db->q('SELECT id FROM topics WHERE author = ?', $id);
			
			while($topic_id = $fetch_topics->fetchColumn()) {
				delete_image('topic', $topic_id);
				$fetch_replies = $db->q('SELECT id FROM replies WHERE parent_id = ?', $topic_id);
				while($reply_id = $fetch_replies->fetch()) {
					delete_image('reply', $reply_id);
				}
			}
			
			$db->q("UPDATE replies SET deleted = '1' WHERE author = ?", $id);
			
			foreach($victim_parents as $parent_id) {
				$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $id);
			}
			
			// Delete topics.
			$db->q("UPDATE topics SET deleted = '1' WHERE author = ?", $id);
			log_mod('nuke_id', $id);
			redirect('Alle Fäden und Antworten von ' . $id . ' wurden gelöscht.', 'profile/'.$id);
		}
		
	break;
	
	case 'nuke_ip':
	
		if( ! $perm->get('nuke_ip')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! filter_var($_GET['id'], FILTER_VALIDATE_IP)) {
			error::fatal('Ungültige IP-Adresse.');
		}
		
		$id = $_GET['id'];
		$template->title = 'Alle Beiträge von <a href="'.DIR.'IP_address/' . $id . '">' . $id . '</a> atomisieren.';
		
		if(isset($_POST['confirm'])) {		
			$res = $db->q('SELECT uid FROM users WHERE ip_address = ?', $id);
			while($uid = $res->fetchColumn()){
				if($perm->is_admin($uid)) {
					error::fatal(m('Fehler: Zugriff verweigert'));
				}
				
				if($perm->is_mod($uid) && ! $perm->is_admin()) {
					error::fatal(m('Fehler: Zugriff verweigert'));
				}
			}
			
			// Delete replies.
			$fetch_parents = $db->q('SELECT parent_id, id FROM replies WHERE author_ip = ?', $id);
			$victim_parents = array();
			while(list($parent_id, $reply_id) = $fetch_parents->fetch()) {
				$victim_parents[] = $parent_id;
				delete_image('reply', $reply_id);
			}
			
			// Nuke the images and delete replies.
			$fetch_topics = $db->q('SELECT id FROM topics WHERE author_ip = ?', $id);
			while($topic_id = $fetch_topics->fetchColumn()) {
				delete_image('topic', $topic_id);
				$fetch_replies = $db->q('SELECT id FROM replies WHERE parent_id = ?', $topic_id);
				while($reply_id = $fetch_replies->fetchColumn()) {
					delete_image('reply', $reply_id);
				}
			}

			$db->q("UPDATE replies SET deleted = '1' WHERE author_ip = ?", $id);
			foreach($victim_parents as $parent_id) {
				$db->q('UPDATE topics SET replies = replies - 1 WHERE id = ?', $parent_id);
			}
			
			// Delete topics.
			$db->q("UPDATE topics SET deleted = '1' WHERE author_ip = ?", $id);
			log_mod('nuke_ip', $id);
			redirect('Alle Fäden und Antworten von ' . $id . ' wurden gelöscht.', 'IP_address/'.$id);
		}
	break;
	
	case 'hide_log':
		if( ! $perm->get('hide_log')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$template->title = 'Log verbergen.';
		
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE mod_actions SET hidden = 1 WHERE id = ?', $_GET['id']);
			redirect('Log wurde versteckt.', 'mod_log');
		}
	break;
	
	case 'unhide_log':
		if( ! $perm->get('hide_log')) {
			error::fatal(m('Fehler: Zugriff verweigert'));
		}
		
		if( ! ctype_digit($_GET['id'])) {
			error::fatal('Ungültige ID.');
		}
		
		$template->title = 'Log anzeigen.';
		
		if(isset($_POST['confirm'])) {
			$db->q('UPDATE mod_actions SET hidden = 0 WHERE id = ?', $_GET['id']);
			redirect('Log wird angezeigt.', 'mod_log');
		}
	break;
	
	default:
		error::fatal('Keine gültige Aktion angegeben.');	
}

echo '<p>Sicher?</p> <form action="" method="post">';
csrf_token();
echo '<div> <input type="hidden" name="confirm" value="1" /> <input type="submit" value="Tu es!" /> </div>';

$template->render();
?>
