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
?>			<form action="./" method="post">
<?
$result = $pdo->prepare(<<<EOF
SELECT `id`,
	`name`,
	`writeins`
FROM `elections`
WHERE `closed` IS NULL
EOF
	);

$result->execute();

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
	$title = htmlentities($row['name'], NULL, 'UTF-8');

	$subresult = $pdo->prepare(<<<EOF
SELECT `id`,
	`name`
FROM `candidates`
WHERE `election` = :election
EOF
		);

	$subresult->execute(array(
		':election' => $row['id']
	));

	$subrows = $subresult->fetchAll(PDO::FETCH_ASSOC);
	$max = count($subrows) + 1;

	echo <<<EOF
				<fieldset class="poll">
					<legend>$title</legend>

EOF;

	foreach ($subrows as $subrow) {
		$name = htmlentities($subrow['name'], NULL, 'UTF-8');

		echo <<<EOF
					<div class="form-control">
						<div class="input-group">
							<input id="rank$subrow[id]" name="candidate$subrow[id]" type="number" min="1" max="$max" />
						</div>
						<label for="rank$subrow[id]">$name</label>
					</div>

EOF;
	}

	if ($row['writeins']) {
		echo <<<EOF
					<div class="form-control">
						<div class="input-group">
							<input name="writein-rank$row[id]" type="number" min="1" max="$max" />
						</div>
						<div class="input-group input-group-right">
							<input name="writein$row[id]" type="text" placeholder="Write-in" />
						</div>
					</div>
EOF;
	}

	echo <<<EOF
				</fieldset>

EOF;
}
?>				<div class="form-control">
					<div class="input-group">
						<input type="submit" value="Cast Ballots" />
					</div>
				</div>
			</form>
		</div>
<?
print_footer(
	'Copyright &copy; 2017 Will Yu',
	'A service of Blacker House'
);
?>	</body>
</html>
