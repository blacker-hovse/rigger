<?
include(__DIR__ . '/../lib/include.php');

$db = 'vote.db';
$create = !file_exists($db);
$short = false;
$pdo = new PDO('sqlite:' . $db);

if ($create) {
	$pdo->exec(<<<EOF
CREATE TABLE `rigger` (
	`id` integer PRIMARY KEY ASC,
	`name` varchar(64) NOT NULL,
	`created` datetime NOT NULL
	`closed` datetime NULL
)
EOF
		);
}
?>
