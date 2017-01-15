<?
include(__DIR__ . '/../../lib/include.php');
include(__DIR__ . '/../include.php');

$pdo = rigger_init('../rigger.db');

if (array_key_exists('title', $_POST)) {
	$candidates = array_filter($_POST['candidates']);

	if ($_POST['title'] and $candidates) {
		$result = $pdo->prepare(<<<EOF
INSERT INTO `elections` (
	`name`,
	`writeins`,
	`created`
)
VALUES (
	:name,
	:writeins,
	DATETIME('now')
)
EOF
			);

		$result->execute(array(
			':name' => $_POST['title'],
			':writeins' => (bool) @$_POST['writeins']
		));

		$election = $pdo->lastInsertId();

		foreach ($candidates as $candidate) {
			$result = $pdo->prepare(<<<EOF
INSERT INTO `candidates` (
	`election`,
	`name`
)
VALUES (
	:election,
	:name
)
EOF
				);

			$result->execute(array(
				':election' => $election,
				':name' => $candidate
			));
		}

		header('Location: ./');
		die();
	}
}
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
<?
print_head('Vote Rigger');
?>		<script type="text/javascript" src="/lib/js/jquery.min.js"></script>
		<script type="text/javascript">// <![CDATA[
			var e = 0;
			var f = $('<a class="btn btn-lg add">+</a>');

			function addCandidate() {
				$('<div class="form-control"><label for="candidate' + e++ + '">Candidate ' + e + '</label><div class="input-group input-group-left"><input type="text" id="candidate' + (e - 1) + '" name="candidates[]" maxlength="255" /></div><div class="input-group input-group-right"><a class="btn btn-lg del">&times;</a></div></div>').children('.input-group-right').append(f).end().insertBefore('#writeins-control');
			}

			$(function() {
				f.click(addCandidate);
				addCandidate();
				addCandidate();

				$('#poll').on('click', '.del', function() {
					if (e > 1) {
						$(this).closest('.form-control').nextUntil('#writeins-control').addBack().slice(0, -1).each(function() {
							$(this).find('input').val($(this).next().find('input').val());
						}).end().last().prev().children('.input-group-right').append(f).end().next().remove();

						e--;
					}
				});
			});
		// ]]></script>
	</head>
	<body>
		<div id="main">
			<h1>Vote Rigger</h1>
<?
$subtitle = rigger_subtitle();

echo <<<EOF
			<h2>$subtitle</h2>

EOF;

if (@$_GET['action'] == 'edit') {
	echo <<<EOF
			<form id="poll" action="?action=edit" method="post">
				<div class="form-control">
					<label for="title">Title</label>
					<div class="input-group">
						<input type="text" id="title" name="title" maxlength="255" />
					</div>
				</div>
				<div id="writeins-control" class="form-control">
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
			<ul class="list-group">

EOF;

	$result = $pdo->prepare(<<<EOF
SELECT `id`,
	`name`,
	`created`,
	`closed`,
	COUNT(DISTINCT `user`) AS `ballots`
FROM (
	SELECT `elections`.`id` AS `id`,
		`elections`.`name` AS `name`,
		`elections`.`created` AS `created`,
		`elections`.`closed` AS `closed`,
		`votes`.`user`
	FROM `elections`
		LEFT JOIN `candidates`
			ON `elections`.`id` = `candidates`.`election`
		LEFT JOIN `votes`
			ON `candidates`.`id` = `votes`.`candidate`
	UNION SELECT `elections`.`id` AS `id`,
		`elections`.`name` AS `name`,
		`elections`.`created` AS `created`,
		`elections`.`closed` AS `closed`,
		`writeins`.`user`
	FROM `elections`
		LEFT JOIN `writeins`
			ON `elections`.`id` = `writeins`.`election`
)
GROUP BY `id`
EOF
		);

	$result->execute();

	while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
		$title = htmlentities($row['name'], NULL, 'UTF-8');
		$closed = $row['closed'] ? 'Closed ' . $row['closed'] : 'Accepting responses';
		$active = $row['closed'] ? '' : ' active';

		echo <<<EOF
				<li class="list-group-item">
					<div class="close toggle$active"></div>
					<h4>$title <small>$row[ballots] cast</small></h4>
					<div class="clearfix pull-right">
						<a class="btn btn-sm" href="?action=count&id=$row[id]">View Results</a>
						<a class="btn btn-sm" href="?action=burn&id=$row[id]">Destroy Poll</a>
					</div>
					<p>Created $row[created]&nbsp;&nbsp;&nbsp;&nbsp;&middot;&nbsp;&nbsp;&nbsp;&nbsp;$closed</p>
					<div class="clearfix"></div>
				</li>

EOF;
	}

	echo <<<EOF
			</ul>

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
