<?php
require './includes/bootstrap.php';
force_id();

if( ! $perm->get('view_profile')) {
	error::fatal(MESSAGE_ACCESS_DENIED);
}

if (!isset($_GET['id']) || !strlen($_GET['id'])) {
	error::fatal('Invalid user ID.');
}

$sth = $db->q('SELECT COUNT(*) FROM users WHERE uid = ?', $_GET['id']);
if (!$sth->fetchColumn())
	error::fatal('No such user exists.');

system_message($_GET['id'], 'This is a sponsored message.', true);

redirect('PM spam sent. <a href="/spam_user.php?id='.$_GET['id'].'">Send another?</a>',
	'profile/' . $_GET['id']
	);
