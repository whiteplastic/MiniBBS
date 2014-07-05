<?php
require './includes/bootstrap.php';
force_id();

/* Check DEFCON */
if( ! $perm->is_admin() && ! $perm->is_mod()) {
	if(DEFCON < 3) {
		error::fatal(m('DEFCON 2'));
	}
	if(DEFCON < 4 && $_SESSION['post_count'] < POSTS_TO_DEFY_DEFCON_3) {
		error::fatal(m('DEFCON 3'));
	}
}

$topic_id = (empty($_GET['reply']) ? 0 : (int) $_GET['reply']);

if ($topic_id) {
	/* This is a reply. */
	
	if( ! $perm->get('post_reply')) {
		error::fatal('Du hast keine Berechtigung zum Antworten.');
	}
		
	$res = $db->q('SELECT headline, author, replies, deleted, locked, last_post FROM topics WHERE id = ?', $topic_id);
	$topic = $res->fetchObject();
	
	if( ! $topic) {
		$template->title = 'Faden existiert nicht.';
		error::fatal('Es gibt keinen solchen Faden. Vielleicht wurde er gelöscht.');
	}
		
	if($topic->deleted) {
		error::fatal('Du kannst auf keinen gelöschten Faden antworten.');
	}
	
	if(AUTOLOCK && ($_SERVER['REQUEST_TIME'] - $topic->last_post) > AUTOLOCK && $topic->author != $_SESSION['UID']) {
		$topic->locked = true;
	}
	
	update_activity('replying', $topic_id);
	$reply = true;
	$template->onload = "focusId('body');";
	$template->title = 'Neue Antwort im Faden: <a href="'.DIR.'topic/' . $topic_id . '">' . htmlspecialchars($topic->headline) . '</a>';
	
	$check_watchlist = $db->q('SELECT 1 FROM watchlists WHERE uid = ? AND topic_id = ?', $_SESSION['UID'], $topic_id);
	if ($check_watchlist->fetchColumn()) {
		$watching_topic = true;
	}
	
} else {
	/* This is a topic. */
	
	if( ! $perm->get('post_topic')) {
		error::fatal('Du hast keine Berechtigung, einen Faden zu erstellen.');
	}
	
	update_activity('new_topic');
	$reply = false;
	$template->onload = "focusId('headline')";
	$template->head = '<script type="text/javascript" src="' . DIR . 'javascript/polls.js"></script>';
	$template->title = 'Neuer Faden';
	
	if ( ! empty($_POST['headline'])) {
		$template->title .= ': ' . htmlspecialchars($_POST['headline']);
	}
	
}

$edit_id = (empty($_GET['edit']) ? false : (int) $_GET['edit']);

if ($edit_id) {
	/* We're editing a post. */
	$editing = true;
	
	if( ! $perm->get('edit')) {
		error::fatal('Du hast keine Berechtigung, Beiträge zu bearbeiten.');
	}
	
	if ($reply) {
		$fetch_edit = $db->q('SELECT author, time, body, edit_mod AS `mod` FROM replies WHERE id = ?', $edit_id);
		$template->title = 'Bearbeite <a href="'.DIR.'topic/' . $topic_id . '#reply_' . $edit_id . '">Antwort</a> auf den Faden <a href="'.DIR.'topic/' . $topic_id . '">' . htmlspecialchars($topic->headline) . '</a>';
	} else {
		$fetch_edit = $db->q('SELECT author, time, body, edit_mod AS `mod`, headline FROM topics WHERE id = ?', $edit_id);
		$template->title = 'Bearbeite Faden';
	}
	
	$edit_data = $fetch_edit->fetchObject();
	
	if ( ! $edit_data) {
		error::fatal('Es gibt diesen Beitrag nicht. Vielleicht wurde er gelöscht.');
	}
		
	if ($edit_data->author === $_SESSION['UID']) {
		$edit_mod = 0;
		
		if ($perm->get('edit_limit') != 0 && ($_SERVER['REQUEST_TIME'] - $edit_data->time > $perm->get('edit_limit'))) {
			error::fatal('Du kannst diesen Beitrag nicht mehr bearbeiten.');
		}
		if ($edit_data->mod) {
			error::fatal('Du kannst diesen Beitrag nicht bearbeiten, weil ein Moderator dies schon getan hat.');
		}
	} else if ($perm->get('edit_others')) {
		$edit_mod = 1;
	} else {
		error::fatal('Du darfst diesen Beitrag nicht bearbeiten.');
	}
	
	/* Fill in the form. */
	if ( ! $_POST['form_sent']) {
		$body = $edit_data->body;
		
		if ( ! $reply) {
			$headline = $edit_data->headline;
			$template->title .= ': <a href="'.DIR.'topic/' . $edit_id . '">' . htmlspecialchars($edit_data->headline) . '</a>';
		}
	} else if ( ! empty($_POST['headline'])) {
		$template->title .= ':  <a href="'.DIR.'topic/' . $edit_id . '">' . htmlspecialchars($_POST['headline']) . '</a>';
	}
}

if (isset($_POST['form_sent'])) {

	$headline = super_trim($_POST['headline']);
	$body     = super_trim($_POST['body']);
	$name     = (isset($_POST['name']) && ( ! FORCED_ANON || $perm->get('link'))  ? super_trim($_POST['name']) : '');
	$trip     = '';
	if ( ! empty($name)) {
		list($name, $trip) = tripcode($name);
	}

	/* Parse for mass quote tag ([quote]). */
	$body = preg_replace_callback
	(
		'/\[quote\](.+?)\[\/quote\]/s', 
		create_function('$matches', 'return preg_replace(\'/.*[^\s]$/m\', \'> $0\', $matches[1]);'), $body
	);
	
	$user_link = $perm->get('link');
	if(isset($_POST['post_as_group'])) {
		$_SESSION['show_group'] = true;
	} else {
		unset($_SESSION['show_group']);
		$user_link = '';
	}
	
	if ($_POST['post']) {
		/* Check for poorly made bots. */
		if ( ! $editing && $_SERVER['REQUEST_TIME'] - $_POST['start_time'] < 3) {
			error::add('Warte ein wenig, bis du einen Beitrag abschickst.');
		}
		
		if ( ! empty($_POST['e-mail'])) {
			error::add('Bot detektiert.');
		}
		
		if(strpos($body, 'http') !== false) {
			if( ! $perm->get('post_link')) {
				error::add('Du darfst keine Links posten.');
			} else if( ! $_SESSION['post_count'] && RECAPTCHA_ENABLE) {
				show_captcha('Dein erster Beitrag enthält einen Link. Um zu beweisen, dass du ein Mensch bist, bitte fülle dieses CAPTCHA aus.');
			}
		}
		
		check_token();
		
		$min_body = MIN_LENGTH_BODY;
		if( ! empty($_FILES['image']['name']) || ! empty($_POST['imgur']) || $editing) {
			$min_body = 0;
		}
		
		check_length($body, 'body', $min_body, MAX_LENGTH_BODY);
		check_length($name, 'name', 0, 30);
		
		if( ! $reply) {
			check_length($headline, 'headline', MIN_LENGTH_HEADLINE, MAX_LENGTH_HEADLINE);
		}
		
		if (count(explode("\n", $body)) > MAX_LINES) {
			error::add('Beitrag hat zu viele Zeilen.');
		}
		
		if(ALLOW_IMAGES && $perm->get('post_image') && ! empty($_FILES['image']['name'])) {
			try {
				$image = new Upload($_FILES['image']);
			} catch (Exception $e) {
				error::add($e->getMessage());
			}
		}
				
		$imgur = '';
		if( ! isset($image) && ! empty($_POST['imgur'])) {
			$_POST['imgur'] = trim($_POST['imgur']);
			if( ! preg_match('/imgur\.com\/([a-zA-Z0-9]{3,10})/', $_POST['imgur'], $matches)) {
				error::add('Das ist keine gültige Imgur-URL.');
			} else {
				$imgur = $matches[1];
			}
		}
		
		
		if($editing && error::valid()) {
			
			if($reply) {
				/* Editing a reply. */
				
				$db->q
				(
					'UPDATE replies 
					SET body = ?, edit_mod = ?, edit_time = ? 
					WHERE id = ?', 
					$body, $edit_mod, $_SERVER['REQUEST_TIME'], 
					$edit_id
				);
										
				$congratulation = m('Hinweis: Beitrag bearbeitet.');
				
			} else {
				/* Editing a topic. */
				
				$db->q
				(
					'UPDATE topics 
					SET headline = ?, body = ?, edit_mod = ?, edit_time = ? 
					WHERE id = ?',
					$headline, $body, $edit_mod, $_SERVER['REQUEST_TIME'], 
					$edit_id
				);
					
				$congratulation = m('Hinweis: Faden bearbeitet.');
			}
			
			if($edit_mod) {
				/* Log the changes. */
				$type = ($reply ? 'reply' : 'topic');
				
				$db->q
				(
					'INSERT INTO revisions 
					(type, foreign_key, text) VALUES 
					(?, ?, ?)', 
					$type, $edit_id, $edit_data->body
				);
					
				log_mod('edit_' . $type, $edit_id, $db->lastInsertId());
			}
		} 
		else if ($reply) {
			/* Posting a reply. */

			if($topic->locked != 0 && ! $perm->get('lock')) {
				error::add('Du kannst auf keinen geschlossenen Faden antworten.');
			}
			
			/* Lurk more. */
			if ($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_REPLY) {
				error::add('Du musst ' . REQUIRED_LURK_TIME_REPLY . ' Sekunden lauern, bis du deine erste Antwort verfasst.');
			}
			
			/* Flood control. */
			$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_REPLY;
			$res = $db->q('SELECT 1 FROM replies WHERE author_ip = ? AND time > ?', $_SERVER['REMOTE_ADDR'], $too_early);

			if ($res->fetchColumn()) {
				error::add('Warte mindestens ' . FLOOD_CONTROL_REPLY . ' Sekunden vor jeder Antwort.');
			}
				
			if(error::valid()) {
				$db->q
				(
					'INSERT INTO replies 
					(author, author_ip, parent_id, body, namefag, tripfag, link, time, imgur) VALUES 
					(?, ?, ?, ?, ?, ?, ?, ?, ?)',
					$_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $topic_id, $body, $name, $trip, $user_link, $_SERVER['REQUEST_TIME'], $imgur
				);
				$inserted_id = $db->lastInsertId();
			
				/* Notify cited posters. */					
				preg_match_all('/@([0-9,]+)/m', $body, $matches);
				/* Needs to filter before array_unique in case of @11, @1,1 etc. */
				$citations = filter_var_array($matches[0], FILTER_SANITIZE_NUMBER_INT);
				$citations = array_unique($citations);
				$citations = array_slice($citations, 0, 9);
					
				foreach ($citations as $citation) {
					/* Note that nothing is inserted unless the SELECT returns a row. */
					$db->q
					(
						'INSERT INTO citations 
						(reply, topic, uid) 
						SELECT ?, ?, `author` FROM replies WHERE replies.id = ? AND replies.parent_id = ?',
						$inserted_id, $topic_id, (int) $citation, $topic_id
					);
				}
				if(strpos($body, '@OP') !== false) {
					$db->q('INSERT INTO citations (reply, topic, uid) VALUES (?, ?, ?)', $inserted_id, $topic_id, $topic->author);
				}
					
				/* Update watchlists. */
				$db->q('UPDATE watchlists SET new_replies = 1 WHERE topic_id = ? AND uid != ?', $topic_id, $_SESSION['UID']);
				
				$congratulation = m('Hinweis: Antwort gespeichert.');
			}
		} else {
			/* Posting a topic. */
				
			/* Do we need to lurk some more? */
			if ($_SERVER['REQUEST_TIME'] - $_SESSION['first_seen'] < REQUIRED_LURK_TIME_TOPIC) {
				error::add('Lauere mindestens ' . REQUIRED_LURK_TIME_TOPIC . ' Sekunden, bevor du deinen ersten Faden erstellst.');
			}
			
			/* Flood control. */
			$too_early = $_SERVER['REQUEST_TIME'] - FLOOD_CONTROL_TOPIC;
			$res = $db->q('SELECT 1 FROM topics WHERE author_ip = ? AND time > ?', $_SERVER['REMOTE_ADDR'], $too_early);
			
			if ($res->fetchColumn()) {
				error::add('Warte mindestens ' . FLOOD_CONTROL_TOPIC . ' Sekunden, bevor du einen neuen Faden erstellst.');
			}
			
			/* Is this a valid poll? */
			$poll = 0;
			if($_POST['enable_poll'] && isset($_POST['option'][0])) {
				if(count($_POST['option']) > 10) {
					$_POST['option'] = array_slice($_POST['option'], 0, 9);
				}
				
				foreach($_POST['option'] as $id => $text) {
					if($text === '') {
						unset($_POST['option'][$id]);
					}
					else if(strlen($text) > 80) {
						error::add('Option ' . ($id + 1) . ' ist länger als 80 Zeichen.');
					}
				}
				
				if(count($_POST['option']) > 1) {
					$poll = 1;
				}
			}
			
			$hide_results = (isset($_POST['hide_results']) ? 1 : 0);
			$sticky       = (isset($_POST['sticky']) && $perm->get('stick') ? 1 : 0);
			$locked       = (isset($_POST['locked']) && $perm->get('lock') ? 1 : 0);
			
			if(error::valid()) {
				$db->q
				(
					'INSERT INTO topics 
					(author, author_ip, headline, body, last_post, time, namefag, tripfag, link, sticky, locked, poll, poll_hide, imgur) VALUES 
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
					$_SESSION['UID'], $_SERVER['REMOTE_ADDR'], $headline, $body, $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'], $name, $trip, $user_link, $sticky, $locked, $poll, $hide_results, $imgur
				);
				$inserted_id = $db->lastInsertId();
				
				if($poll) {
					foreach($_POST['option'] as $option) {
						$db->q('INSERT INTO poll_options (`parent_id`, `option`) VALUES (?, ?)', $inserted_id, $option);
					}
				}
				
				$congratulation = m('Hinweis: Faden erstellt.');
			}
		}
		
		
		if (error::valid()) {
			/* We successfully submitted or edited the post. */
			
			if ( ! $editing) {
				$raw_name = (isset($_POST['name']) ? super_trim($_POST['name']) : '');
				$db->q('UPDATE users SET post_count = post_count + 1, namefag = ? WHERE uid = ?', $raw_name, $_SESSION['UID']);
				$_SESSION['post_count']++;
				$_SESSION['poster_name'] = $raw_name;

				setcookie('last_bump', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
				
				if ($reply) {
					$db->q("UPDATE last_actions SET time = ? WHERE feature = 'last_bump'", $_SERVER['REQUEST_TIME']);
					$db->q('UPDATE topics SET replies = replies + 1, last_post = ? WHERE id = ?', $_SERVER['REQUEST_TIME'], $topic_id);
					
					$target_topic = $edit_id;
					$redir_loc    = $topic_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $inserted_id;
				} else { # If topic.
					setcookie('last_topic', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
					$db->q("UPDATE last_actions SET time = ? WHERE feature = 'last_topic' OR feature = 'last_bump'", $_SERVER['REQUEST_TIME']);
					
					$target_topic = $inserted_id;
					$redir_loc    = $inserted_id;
				}
			} else { # If editing.
				if ($reply) {
					$target_topic = $topic_id;
					$redir_loc    = $topic_id . ($_SESSION['settings']['posts_per_page'] ? '/reply/' : '#reply_') . $edit_id;
				} else { # If topic.
					$target_topic = $edit_id;
					$redir_loc    = $edit_id;
				}
			}
			
			// Take care of the upload.
			if(isset($image) && $image->success) {
				$post_type = ($reply ? 'reply' : 'topic');
				
				if($editing) {
					delete_image($post_type, $edit_id, true);
					$image_target = $edit_id;
				} else {
					$image_target = $inserted_id;
				}
				
				$image->move($post_type, $image_target);
			}
			
			/* Add topic to watchlist if desired. */
			if (isset($_POST['watch_topic']) && ! $watching_topic) {
				$db->q('INSERT INTO watchlists (uid, topic_id) VALUES (?, ?)', $_SESSION['UID'], $target_topic);
			}
			
			/* Set the congratulation notice and redirect to the affected post. */
			redirect($congratulation, 'topic/' . $redir_loc);
			
		} else {
			/* If an error occured, insert this into failed postings. */
			if ($reply) {
				$db->q('INSERT INTO failed_postings (time, uid, reason, body) VALUES (?, ?, ?, ?)', $_SERVER['REQUEST_TIME'], $_SESSION['UID'], serialize(error::$errors), substr($body, 0, MAX_LENGTH_BODY));
			} else {
				$db->q('INSERT INTO failed_postings (time, uid, reason, body, headline) VALUES (?, ?, ?, ?, ?)', $_SERVER['REQUEST_TIME'], $_SESSION['UID'], serialize(error::$errors), substr($body, 0, MAX_LENGTH_BODY), substr($headline, 0, MAX_LENGTH_HEADLINE));
			}
		}
	}
}

error::output();

/* For the bot check. */
$start_time = $_SERVER['REQUEST_TIME'];
if (ctype_digit($_POST['start_time'])) {
	$start_time = $_POST['start_time'];
}

/* Get name and tripcode. */
if($_POST['form_sent']) {
	$set_name = $_POST['name'];
} else {
	$set_name = $_SESSION['poster_name'];
}

/* Get cited or original post and prepare body. */
if($reply) {
	if( ! isset($_GET['cite']) && ! isset($_GET['quote_reply'])) {
		$cited_reply = false;
	} else {
		$cited_reply = (isset($_GET['cite']) ? (int) $_GET['cite'] : (int) $_GET['quote_reply']);
	}

	if ($cited_reply) {
		$new_body = '@' . number_format($cited_reply) . "\n\n";
		$res = $db->q('SELECT body, namefag, tripfag FROM replies WHERE id = ? AND deleted = 0', $cited_reply);
	} else {
		$res = $db->q('SELECT body, namefag, tripfag FROM topics WHERE id = ? AND deleted = 0', $topic_id);
	}
	
	list($cited_text, $cited_name, $cited_trip) = $res->fetch();
	
	if(isset($_GET['quote_topic']) || isset($_GET['quote_reply'])) {
		/* Snip citations from quote. */
		$quoted_text = trim(preg_replace('/^@([0-9,]+|OP)/m', '', $cited_text));

		/* Prefix newlines with > */
		$quoted_text = preg_replace('/^/m', '> ', $cited_text);
		$new_body .= $quoted_text . "\n\n";
	}
	
	/* $body may already be set from previewing or error */
	if( ! isset($body)) {
		$body = $new_body;
	}
	
	$cited_text = parser::parse($cited_text);
	$cited_text = preg_replace('/^@([0-9]+|OP),?([0-9]+)?/m', '<span class="unimportant"><a href="'.DIR.'topic/'. (int) $topic_id.'#reply_$1$2">$0</a></span>', $cited_text);
}

echo '<div>';

/* Check if OP. */
if ($reply && ! $editing) {
	echo '<p>Du <strong>bist';
	if ($_SESSION['UID'] !== $topic->author) {
		echo ' nicht';
	}
	echo '</strong> der OP dieses Fadens.</p>';
}

/* Print deadline for edit submission. */
if ($editing && $perm->get('edit_limit') != 0) {
	echo '<p>Du hast noch <strong>' . age($_SERVER['REQUEST_TIME'], $edit_data->time + $perm->get('edit_limit')) . '</strong> um diesen Beitrag zu bearbeiten.</p>';
}

/* Print preview. */
if ($_POST['preview'] && ! empty($body)) {
	$preview_body = parser::parse($body, $_SESSION['UID']);
	$preview_body = preg_replace('/^@([0-9]+|OP),?([0-9]+)?/m', '<span class="unimportant"><a href="'.DIR.'topic/'.(int)$topic_id.'#reply_$1$2">$0</a></span>', $preview_body);
	echo '<h3 id="preview">Vorschau</h3><div class="body standalone">' . $preview_body . '</div>';
}

/* Check if any new replies have been posted since we last viewed the topic. */
if ($reply && isset($_SESSION['topic_visits'][$topic_id]) && $_SESSION['topic_visits'][$topic_id] < $topic->replies) {
	$new_replies = $topic->replies - $_SESSION['topic_visits'][$topic_id];
	echo '<p><a href="'.DIR.'topic/' . $topic_id . '#new"><strong>' . $new_replies . '</strong> Neue Antwort' . ($new_replies == 1 ? '</a> wurde' : 'en</a> wurden') . ' wurden geschrieben, seit du den Faden besucht hast! </p>';
}

?>
<form action="" method="post"<?php if(ALLOW_IMAGES) echo ' enctype="multipart/form-data"' ?>>
	<?php csrf_token() ?>
	<div class="noscreen">
		<input name="form_sent" type="hidden" value="1" />
		<input name="e-mail" type="hidden" />
		<input name="start_time" type="hidden" value="<?php echo $start_time ?>" />
	</div>
	<?php if( ! $reply): ?>
	<div class="row">
		<label for="headline">Headline</label> <script type="text/javascript"> printCharactersRemaining('headline_remaining_characters', 100); </script>.
		<input id="headline" name="headline" tabindex="1" type="text" size="124" maxlength="100" onkeydown="updateCharactersRemaining('headline', 'headline_remaining_characters', 100);" onkeyup="updateCharactersRemaining('headline', 'headline_remaining_characters', 100);" value="<?php if($_POST['form_sent'] || $editing) echo htmlspecialchars($headline) ?>">
	</div>
	<?php endif; ?>
	<?php if( ! $editing && ( ! FORCED_ANON || $perm->get('link'))): ?>
			<div class="row"><label for="name">Name</label>: <input id="name" name="name" type="text" size="30" maxlength="30" tabindex="2" value="<?php echo htmlspecialchars($set_name) ?>" class="inline">
		<?php if($perm->get('link')): ?>
			<input type="checkbox" name="post_as_group" id="post_as_group" value="1" class="inline" <?php if(isset($_SESSION['show_group'])) echo ' checked="checked"' ?> />
			<label for="post_as_group" class="inline"> Post as <?php echo htmlspecialchars($perm->get('name')) ?></label>
		<?php endif; ?>
	<?php endif; ?>
	<div class="row">
		<label for="body" class="noscreen">Post body</label> 
		<textarea name="body" cols="120" rows="18" tabindex="2" id="body"><?php if(isset($body)) echo htmlspecialchars($body) ?></textarea>
	<?php if (ALLOW_IMAGES && $perm->get('post_image')): ?>
		<label for="image" class="noscreen">Image</label> <input type="file" name="image" id="image" />
	<?php endif; ?>
	

	<?php if(IMGUR_KEY && ! $editing): ?>
		<div>
			<?php if(ALLOW_IMAGES) echo 'Oder benutze' ?> imgur URL: 
			<input type="text" name="imgur" id="imgur" class="inline" size="21" placeholder="http://i.imgur.com/wDizy.gif" />
			<a href="http://imgur.com/" id="imgur_status" onclick="$('#imgur_file').click(); return false;">[upload]</a>
			<input type="file" id="imgur_file" class="noscreen" onchange="imgurUpload(this.files[0], '<?php echo IMGUR_KEY ?>')" />
		</div>
	<?php endif; ?>
		<p><?php echo m('Beitrag: Hilfe') ?></p>
	</div>
	<?php if ( ! $watching_topic): ?>
		<div class="row">
			<input type="checkbox" name="watch_topic" id="watch_topic" class="inline"<?php if(isset($_POST['watch_topic'])) echo ' checked="checked"' ?> />
			<label for="watch_topic" class="inline"> Beobachten</label>
		</div>
	<?php endif; ?>
	<?php if( ! $reply && ! $editing): ?>
		<?php if($perm->get('stick')): ?>
			<div>
				<input type="checkbox" name="sticky" value="1" class="inline"/>
				<label for="sticky" class="inline"> Sticky</label>
			</div>
		<?php endif; ?>
		<?php if($perm->get('lock')): ?>
			<div class="row">
				<input type="checkbox" name="locked" value="1" class="inline"/>
				<label for="locked" class="inline"> Schließen</label>
			</div>
		<?php endif;?>
	<?php endif; ?>

<?php
if( ! $reply && ! $editing):
?>
		
	<input type="hidden" id="enable_poll" name="enable_poll" value="1" />
	<ul class="menu"><li><a id="poll_toggle" onclick="showPoll(this);">Abstimmungsoptionen</a></li></ul>
		
	<table id="topic_poll">
		<tr class="odd">
			<th colspan="2"><input type="checkbox" name="hide_results" id="hide_results" value="1" class="inline"<?php if($_POST['hide_results']) echo ' checked="checked"' ?>/><label for="hide_results" class="inline help" title="Wenn aktiv, werden die Ergebnisse der Abstimmung versteckt, bis abgestimmt oder 'Ergebnisse anzeigen' geklickt wurde."> Ergebnisse vor der Stimmabgabge verstecken.</label></td>
		</tr>
<?php
	/* Print at least two, or as many as were submitted (in case of preview/error) */
	for($i = 1, $s = count($_POST['option']); ($i <= 2 || $i <= $s); ++$i):
?>
	<tr>
		<td class="minimal">
			<label for="poll_option_<?php echo $i ?>"Abstimmungsoption Nr.<?php echo $i ?></label>
		</td>
		<td>
			<input type="text" size="50" maxlength="80" id="poll_option_<?php echo $i ?>" name="option[]" value="<?php if(isset($_POST['form_sent'])) echo htmlspecialchars($_POST['option'][$i - 1]) ?>" class="poll_input" />
		</td>
	</tr>
<?php
	endfor;
endif; 
?>
	</table>
		
	<div class="row">
	<input type="submit" name="preview" tabindex="3" value="Vorschau" class="inline"<?php if(ALLOW_IMAGES) echo ' onclick="document.getElementById(\'image\').value=\'\'"' ?> /> 
		<input type="submit" name="post" tabindex="4" value="<?php echo ($editing) ? 'Aktualisieren' : 'Post' ?>" class="inline">
	</div>
</form>
</div>

<?php if( ! empty($cited_text)): ?>
	<h3 id="replying_to">Antwort auf <?php echo format_name($cited_name, $cited_trip) ?>&hellip;</h3> 
	<div class="body standalone"><?php echo $cited_text ?></div>
<?php endif; ?>

<?php
$template->render();
?>
