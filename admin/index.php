<?
include(__DIR__ . '/../../lib/include.php');
include(__DIR__ . '/../include.php');

$pdo = rigger_init('../rigger.db');
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

if (@$_GET['action'] == 'edit') {
	echo <<<EOF
			<form action="./" method="post">
				<div class="form-control">
					<label for="title">Title</label>
					<div class="input-group">
						<input type="text" id="title" name="title" maxlength="255" />
					</div>
				</div>
				<div class="form-control">
					<label for="candidate0">Candidate 1</label>
					<div class="input-group input-group-left">
						<input type="text" id="candidate0" name="candidate[]" maxlength="255" />
					</div>
					<div class="input-group input-group-right">
						<a class="btn btn-lg del">&times;</a>
					</div>
				</div>
				<div class="form-control">
					<label for="candidate1">Candidate 2</label>
					<div class="input-group input-group-left">
						<input type="text" id="candidate1" name="candidate[]" maxlength="255" />
					</div>
					<div class="input-group input-group-right">
						<a class="btn btn-lg del">&times;</a>
						<a class="btn btn-lg add">+</a>
					</div>
				</div>
				<div class="form-control">
					<div class="input-group">
						<input type="checkbox" id="writeins" name="writeins" value="writeins" checked="checked" />
						<label for="writeins">Allow write-ins</label>
					</div>
				</div>
				<div class="form-control">
					<div class="input-group">
						<input type="submit" value="Submit" />
					</div>
				</div>
			</form>

EOF;
} else {
	echo <<<EOF
			<p class="text-center">
				<a class="btn btn-lg" href="?action=edit">Create Poll</a>
			</p>

EOF;
}
?>		</div>
<?
print_footer(
	'Copyright &copy; 2017 Will Yu',
	'A service of Blacker House'
);
?>	</body>
</html>
