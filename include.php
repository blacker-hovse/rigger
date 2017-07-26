<?
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
  $result = $pdo->prepare(file_get_contents('tally.sql'));

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
    while ($graph[$winners - 1] == $graph[$winners]) {
      foreach ($wins as $win) {
        $c1 = (int) $win['c1'];

        if ($c1 == $graph[$winners - 1]) {
          $graph[$winners - 1]--;
          break;
        } elseif ($c1 == $graph[$winners]) {
          $graph[$winners]--;
          break;
        }
      }

      arsort($graph);
    }
  }

  return array_slice($graph, 0, $winners, true);
}

function rigger_dfs($graph, $v, $t, $discovered) {
  if ($v == $t) {
    return true;
  }

  $discovered[$v] = true;

  foreach ($graph[$v] as $w => $i) {
    if (!array_key_exists($w, $discovered)) {
      if (rigger_dfs($graph, $w, $t, $discovered)) {
        return true;
      }
    }
  }

  return false;
}

function rigger_escape($str) {
  return htmlentities($str, NULL, 'UTF-8');
}

function rigger_init($db) {
  $create = !file_exists($db);
  $pdo = new PDO('sqlite:' . $db);

  if ($create) {
    $pdo->exec(<<<EOF
CREATE TABLE `elections` (
  `id` integer PRIMARY KEY ASC,
  `name` varchar(255) NOT NULL,
  `winner` int NOT NULL,
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
