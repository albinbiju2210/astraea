<?php
// Shared header: outputs the document <head> and opens <body>.
// Asset URLs are absolute to the app path so clients fetch the correct files.
$cssUrl = 'css/style.css';
$jsUrl  = 'js/theme.js';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Astraea</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES); ?>">
    <script src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES); ?>" defer></script>
</head>
<body>

<div class="theme-bar">
    <button class="theme-btn" data-theme="light" title="Light theme">L</button>
    <button class="theme-btn" data-theme="blue" title="Blue theme">B</button>
    <button class="theme-btn" data-theme="dark" title="Dark theme">D</button>
</div>

