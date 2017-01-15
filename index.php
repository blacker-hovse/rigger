<?
include(__DIR__ . '/../lib/include.php');
include(__DIR__ . '/include.php');

$pdo = rigger_init('rigger.db');
$user = $_SERVER['PHP_AUTH_USER'];
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
<?
print_head('Vote Rigger');
?>	</head>
	<body>
		<div id="main">
			<h1>Vote Rigger</h1>
<?
$candidates = array();
$writeins = array();

if (@$_POST['action'] == 'vote') {
	$candidates = array_filter($_POST['candidates']);
	$writein_ranks = array_filter($_POST['writein-ranks']);
	$writein_names = array_filter($_POST['writein-names']);
	$writeins = array_intersect_key($writein_ranks, $writein_names);
	$error = '';

	array_walk($writeins, function(&$writein, $election, $names) {
		$writein = array(
			$writein,
			$names[$election]
		);
	}, $writein_names);

	$ranks = array_merge(array_values($candidates), array_values($writein_ranks));
	sort($ranks);

	if (max(array_count_values($ranks)) >= 2) {
		$error = 'Two candidates may not be given the same rank.';
	} elseif ($ranks and min($ranks) < 1) {
		$error = 'Invalid ranking.';
	} else {
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
			':user' => $user
		);

		if ($candidates) {
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
		}

		if ($writeins) {
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
		}
	} else {
		echo <<<EOF
			<div class="error">$error</div>

EOF;
	}
}

$subtitle = rigger_subtitle();

echo <<<EOF
			<h2>$subtitle</h2>

EOF;
?>			<form action="./" method="post">
<?
$result = $pdo->prepare(<<<EOF
SELECT `elections`.`id` AS `id`,
	`elections`.`name` AS `election`,
	`elections`.`writeins` AS `writeins`,
	`writeins`.`name` AS `writein`,
	`writeins`.`rank` AS `rank`
FROM `elections`
	LEFT JOIN `writeins`
		ON `elections`.`id` = `writeins`.`election`
			AND `writeins`.`user` = :user
WHERE `closed` IS NULL
EOF
	);

$result->execute(array(
	':user' => $user
));

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
	$title = htmlentities($row['election'], NULL, 'UTF-8');

	$subresult = $pdo->prepare(<<<EOF
SELECT `candidates`.`id` AS `id`,
	`candidates`.`name` AS `name`,
	`votes`.`rank` AS `rank`
FROM `candidates`
	LEFT JOIN `votes`
		ON `candidates`.`id` = `votes`.`candidate`
			AND `votes`.`user` = :user
WHERE `candidates`.`election` = :election
EOF
		);

	$subresult->execute(array(
		':election' => $row['id'],
		':user' => $user
	));

	$subrows = $subresult->fetchAll(PDO::FETCH_ASSOC);
	$max = count($subrows) + 1;

	echo <<<EOF
				<fieldset class="poll">
					<legend>$title</legend>

EOF;

	foreach ($subrows as $subrow) {
		$name = htmlentities($subrow['name'], NULL, 'UTF-8');

		if (array_key_exists($subrow['id'], $candidates)) {
			$rank = ' value="' . (int) $candidates[$subrow['id']] . '"';
		} elseif ($subrow['rank']) {
			$rank = " value=\"$subrow[rank]\"";
		} else {
			$rank = '';
		}

		echo <<<EOF
					<div class="form-control">
						<div class="input-group">
							<input id="rank$subrow[id]" name="candidates[$subrow[id]]" type="number" min="1" max="$max"$rank />
						</div>
						<label for="rank$subrow[id]">$name</label>
					</div>

EOF;
	}

	if ($row['writeins']) {
		if (array_key_exists($row['id'], $writeins)) {
			$name = ' value="' . htmlentities($writeins[$row['id']][1], NULL, 'UTF-8') . '"';
			$rank = ' value="' . (int) $writeins[$row['id']][0] . '"';
		} elseif ($row['rank']) {
			$name = ' value="' . htmlentities($row['writein'], NULL, 'UTF-8') . '"';
			$rank = " value=\"$row[rank]\"";
		} else {
			$name = '';
			$rank = '';
		}

		echo <<<EOF
					<div class="form-control">
						<div class="input-group">
							<input name="writein-ranks[$row[id]]" type="number" min="1" max="$max"$rank />
						</div>
						<div class="input-group input-group-right">
							<input name="writein-names[$row[id]]" type="text" placeholder="Write-in"$name />
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
