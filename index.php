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
if (@$_POST['action'] == 'vote') {
	$candidates = array_filter($_POST['candidates']);
	$writein_ranks = array_filter($_POST['writein-ranks']);
	$writein_names = array_filter($_POST['writein-names']);
	$writeins = array_intersect_key($writein_ranks, $writein_names);

	array_walk($writeins, function(&$writein, $election, $names) {
		$writein = array(
			$writein,
			$names[$election]
		);
	}, $writein_names);

	$ranked = rigger_intval($candidates);
	$written = rigger_intval($writeins);

	$result = $pdo->prepare(<<<EOF
SELECT `elections`.`name` AS `name`
FROM `elections`
	INNER JOIN `candidates`
		ON `elections`.`id` = `candidates`.`election`
WHERE `elections`.`closed` IS NOT NULL
	AND `candidates`.`id` IN $ranked
UNION SELECT `elections`.`name` AS `name`
FROM `elections`
WHERE `elections`.`closed` IS NOT NULL
	AND `elections`.`id` IN $written
EOF
		);

	$result->execute();
	$closed = $result->fetchAll(PDO::FETCH_COLUMN);
	$error = '';

	switch (count($closed)) {
		case 0:
			break;
		case 1:
			$error = 'Poll <b>' . htmlentities($closed[0], NULL, 'UTF-8') . '</b> has already closed.';
			break;
		case 2:
			$error = 'Polls <b>' . rigger_escape($closed[0]) . '</b> and <b>' . rigger_escape($closed[1]) . '</b> have already closed.';
			break;
		default:
			$error = 'Polls <b>' . implode('</b>, <b>', array_map('rigger_escape', array_slice($closed, 0, -1))) . '</b>, and <b>' . $closed[count($closed) - 1] . '</b> have already closed.';
			break;
	}

	if (!$error) {
		$pdo->exec(<<<EOF
DELETE FROM `votes`
WHERE `rowid` IN (
	SELECT `votes`.`rowid` FROM `votes`
		INNER JOIN `candidates`
			ON `candidates`.`id` = `votes`.`candidate`
		INNER JOIN `elections`
			ON `elections`.`id` = `candidates`.`election`
	WHERE `elections`.`id` IN (
		SELECT DISTINCT `election`
		FROM `candidates`
		WHERE `id` IN $ranked
	)
)
EOF
			);

		$pdo->exec(<<<EOF
DELETE FROM `writeins`
WHERE `rowid` IN (
	SELECT `writeins`.`rowid` FROM `writeins`
		INNER JOIN `elections`
			ON `elections`.`id` = `writeins`.`election`
	WHERE `elections`.`id` IN (
		SELECT DISTINCT `election`
		FROM `writeins`
		WHERE `id` IN $written
	)
)
EOF
			);

		$parameters = array(
			':user' => $_SERVER['PHP_AUTH_USER']
		);

		$votes = $candidates;

		array_walk($votes, function(&$rank, $candidate) {
			$candidate = (int) $candidate;
			$rank = (int) $rank;

			$rank = <<<EOF
(
	$candidate,
	:user,
	$rank
)
EOF;
		});

		$votes = implode(', ', $votes);

		$statement = <<<EOF
INSERT INTO `votes` (
	`candidate`,
	`user`,
	`rank`
)
VALUES $votes
EOF;

		$result = $pdo->prepare($statement);
		$result->execute($parameters);

		print_r($statement);
		print_r($parameters);
		print_r($pdo->errorInfo());

		$votes = $writeins;

		array_walk($votes, function(&$writein, $election) {
			global $parameters;

			$election = (int) $election;
			$parameters[':name' . $election] = $writein[1];
			$rank = (int) $writein[0];

			$writein = <<<EOF
(
	$election,
	:name$election,
	:user,
	$rank
)
EOF;
		});

		$votes = implode(', ', $votes);

		$statement = <<<EOF
INSERT INTO `writeins` (
	`election`,
	`name`,
	`user`,
	`rank`
)
VALUES $votes
EOF;

		$result = $pdo->prepare($statement);
		$result->execute($parameters);

		print_r($statement);
		print_r($parameters);
		print_r($pdo->errorInfo());
	}
}

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
							<input id="rank$subrow[id]" name="candidates[$subrow[id]]" type="number" min="1" max="$max" />
						</div>
						<label for="rank$subrow[id]">$name</label>
					</div>

EOF;
	}

	if ($row['writeins']) {
		echo <<<EOF
					<div class="form-control">
						<div class="input-group">
							<input name="writein-ranks[$row[id]]" type="number" min="1" max="$max" />
						</div>
						<div class="input-group input-group-right">
							<input name="writein-names[$row[id]]" type="text" placeholder="Write-in" />
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
						<input type="hidden" name="action" value="vote" />
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
