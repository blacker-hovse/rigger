<?
function rigger_ballot($pdo, $election, $user) {
  $result = $pdo->prepare(<<<EOF
SELECT `candidates`.`id` AS `id`,
  `candidates`.`name` AS `name`,
  `votes`.`rank` AS `rank`
FROM `candidates`
LEFT JOIN `votes` ON `candidates`.`id` = `votes`.`candidate`
  AND `votes`.`user` = :user
WHERE `candidates`.`election` = :election
EOF
    );

  $result->execute(array(
    ':election' => $election,
    ':user' => $user
  ));

  return $result;
}

function rigger_closed($closed) {
  return $closed ? 'Closed ' . $closed : 'Accepting responses';
}

function rigger_count($pdo, $election) {
  $result = $pdo->prepare(<<<EOF
SELECT `winners`
FROM `elections`
WHERE `id` = :id
EOF
    );

  $result->execute(array(
    ':id' => $_GET['id']
  ));

  $winners = $result->fetch(PDO::FETCH_COLUMN);
  $result = $pdo->prepare(file_get_contents('count.sql'));

  $result->execute(array(
    ':id' => $election
  ));

  $wins = $result->fetchAll(PDO::FETCH_ASSOC);
  $wins = array_slice($wins, 0, count($wins) / 2);
  $graph = array();

  foreach ($wins as $win) {
    $c1 = (int) $win['c1'];
    $graph[$c1] = @$graph[$c1] + 1;
  }

  $wins = array_reverse($wins);
  arsort($graph);

  if ($winners < count($graph)) {
    while (true) {
      $keys = array_keys($graph);
      $pre = $keys[$winners - 1];
      $post = $keys[$winners];

      if ($graph[$pre] != $graph[$post]) {
        break;
      }

      foreach ($wins as $win) {
        $c1 = (int) $win['c1'];

        if ($c1 == $graph[$pre]) {
          $graph[$pre]--;
          break;
        } elseif ($c1 == $graph[$post]) {
          $graph[$post]--;
          break;
        }
      }

      arsort($graph);
    }
  }

  return array_slice($graph, 0, $winners, true);
}

function rigger_init($db) {
  $create = !file_exists($db);
  $pdo = new PDO('sqlite:' . $db);

  if ($create) {
    $pdo->exec(<<<EOF
CREATE TABLE `elections` (
  `id` integer PRIMARY KEY ASC,
  `name` varchar(255) NOT NULL,
  `winners` int NOT NULL,
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

function rigger_intval($arr) {
  return "('" . implode("', '", array_map('intval', array_keys($arr))) . "')";
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
