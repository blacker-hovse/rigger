<?
include(__DIR__ . '/../../lib/include.php');
include(__DIR__ . '/../include.php');

$pdo = rigger_init('rigger.db');
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
<?
print_head('Rigger');
?>	</head>
	<body>
		<div id="main">
			<h1>Rigger</h1>
<?
$subtitle = rigger_subtitle();

echo <<<EOF
			<h2>$subtitle</h2>

EOF;
?>
