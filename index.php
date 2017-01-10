<?
include(__DIR__ . '/../lib/include.php');
include(__DIR__ . '/include.php');

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
?>			<form>
				<fieldset class="poll">
					<legend>Should Blacker president be decided by a congress of pigeons? Should Blacker president be decided by a congress of pigeons?</legend>
					<div class="form-control">
						<div class="input-group">
							<input id="option" type="number" />
						</div>
						<label for="option">Option 1</label>
					</div>
					<div class="form-control">
						<div class="input-group">
							<input id="writein" type="number" />
						</div>
						<div class="input-group input-group-right">
							<input name="writein" />
						</div>
					</div>
				</fieldset>
			</form>
		</div>
<?
print_footer(
	'Copyright &copy; 2017 Will Yu',
	'A service of Blacker House'
);
?>	</body>
</html>
