<?php
/**
 * This file is included from class.template.php's load() function.
 * $this references variables and functions within that class.
 */
global $timer;
$show_cwc = $_SERVER["REQUEST_TIME"] > 1364781600 && $_SERVER["REQUEST_TIME"] < 1364868000;
$this->gzhandler();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="google-site-verification" content="aibwhzQXqQkYGXDHFuEUFjE7JsdE67OEPx2rGteBrzc" />
	<title><?php echo strip_tags($this->title) . ' — ' . SITE_TITLE; echo $_SERVER['HTTP_HOST'] !== HOSTNAME ? " (via a proxy)" : "" ?></title>
	<link rel="icon" type="image/png" href="<?php echo DIR ?>favicon.png" />

	<!-- remove this in june -->
	<link rel="stylesheet" type="text/css" href="//img.tinychan.<?php echo stristr($_SERVER['HTTP_HOST'], 'org') ? 'org' : 'net' ?>/kill-cookies.php">

<?php	
	if($_SESSION['settings']['style'] != 'Custom only'): 
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR ?>style/main.css?14" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR . 'style/themes/' . ($_le_style = $this->get_stylesheet()) . '.css?9' ?>" />
<?php
	endif;
	
	if($_SESSION['settings']['custom_style'] && ! $this->disable_custom):
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR; ?>custom_style<?php echo (isset($_SESSION['style_last_modified']) ? '/' . (int) $_SESSION['style_last_modified'] : '' ) ?>" />
<?php
	endif;
	
	if(MOBILE_MODE):
?>
	<meta name="viewport" content="width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR . 'style/mobile.css?2' ?>" />
<?php 
	elseif(FANCY_IMAGE):
?>
	<link rel="stylesheet" type="text/css" media="screen" href="<?php echo DIR; ?>style/thickbox.css" />
	<script type="text/javascript" src="<?php echo DIR; ?>javascript/thickbox.js"></script>
	<script type="text/javascript">var tb_pathToImage = "<?php echo URL; ?>javascript/img/loading.gif"</script>
<?php
	endif;
?>
	<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
	<script type="text/javascript" src="<?php echo DIR; ?>javascript/main.js?6"></script>
<?php
	echo $this->head
?>
	<style>
		tr.adrow td, tr.adrow:hover td {
			background: transparent;
			overflow: hidden;
		}

		pre.ansi {
			line-height: 100%;
			font-weight: bold;
		}

<?php // if ($show_cwc) echo @file_get_contents("april1_style.css") ?>
	</style>
</head>

<body<?php echo ( ! empty($this->onload) ? ' onload="' . $this->onload . '"' : '' ) ?>>
<span id="top"></span>
<?php if ($_le_style == 'CWC') echo @file_get_contents("april1.html") ?>
<?php
if( ! empty($_SESSION['notice'])):
?>
<div id="notice" ondblclick="this.parentNode.removeChild(this);"><strong>Notice</strong>: <?php echo $_SESSION['notice'] ?></div>
<?php
	unset($_SESSION['notice']);
endif;
?>
	<h1 id="logo"><a rel="index" href="<?php echo DIR ?>"><?php echo SITE_TITLE ?></a><?php echo $_SERVER['HTTP_HOST'] !== HOSTNAME ? '<sup>via a proxy</sup>' : '' ?></h1>
	
	<ul id="main_menu" class="menu">
<?php
/* Print the main menu. */
foreach($this->get_user_menu() as $linked_text => $path):
?>
		<li id="menu_<?php echo $path ?>">
			<a href="<?php echo (strpos($path, 'http') === 0 ? '' : DIR) . $path ?>"><?php echo $this->mark_new($linked_text, $path); if(isset($this->menu_children[$linked_text])) echo '<span class="dropdown">▼</span>' ?></a>
<?php
	if(isset($this->menu_children[$linked_text])):
?>
			<ul>
<?php
		foreach($this->menu_children[$linked_text] as $linked_text => $path):
?>
				<li><a href="<?php echo (strpos($path, 'http') === 0 ? '' : DIR) . $path ?>"><?php echo $this->mark_new($linked_text, $path) ?></a></li>
<?php
		endforeach;
?>
			</ul>
<?php
	endif;
?>
		</li>
<?php
endforeach;
?>
	</ul>

<h2><?php echo $this->title ?></h2>
<?php
echo $this->content;
$timer->stop();
?>

<noscript><br />Note: Your browser's JavaScript is disabled; some site features may not fully function.</noscript>

<!-- Generated in <?php echo round($timer->total, 5) ?> seconds. <?php echo round($timer->sql_total*100/$timer->total) ?>% of that was spent running <?php echo (int) $timer->sql_count ?> SQL queries. -->

<form id="quick_action" action="" method="post" class="noscreen">
	<?php csrf_token() ?>
	<input type="hidden" name="confirm" value="1" />
</form>

<span id="bottom"></span>
</body>
</html>
