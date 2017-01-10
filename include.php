<?
function rigger_init($db) {
	$create = !file_exists($db);
	$pdo = new PDO('sqlite:' . $db);

	if ($create) {
		$pdo->exec(<<<EOF
CREATE TABLE `elections` (
	`id` integer PRIMARY KEY ASC,
	`name` varchar(255) NOT NULL,
	`writeins` int NOT NULL,
	`created` datetime NOT NULL,
	`closed` datetime
)
EOF
			);

		$pdo->exec(<<<EOF
CREATE TABLE `candidates` (
	`id` integer PRIMARY KEY ASC,
	`election` int NOT NULL,
	`name` varchar(255) NOT NULL
)
EOF
			);

		$pdo->exec(<<<EOF
CREATE TABLE `votes` (
	`candidate` int NOT NULL,
	`user` varchar(64) NOT NULL,
	`rank` int NOT NULL
)
EOF
			);

		$pdo->exec(<<<EOF
CREATE TABLE `writeins` (
	`election` int NOT NULL,
	`name` varchar(255) NOT NULL,
	`user` varchar(64) NOT NULL,
	`rank` int NOT NULL
)
EOF
			);
	}

	return $pdo;
}

function rigger_subtitle() {
	$subtitles = array(
		'Benevolent Dictatorship',
		'Democracy is an Illusion',
		"Vive l'Anarchie",
		'Your Vote Doesn\'t Count'
	);

	return $subtitles[mt_rand(0, count($subtitles) - 1)];
}
?>
