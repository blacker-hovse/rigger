<?
function rigger_closed($closed) {
  return $closed ? 'Closed ' . $closed : 'Accepting responses';
}

function rigger_count($pdo, $election, $exclusions) {
  $graph = array();
  $result = $pdo->prepare(str_replace(':exclusions', rigger_stringify($pdo, $exclusions), file_get_contents('tally.sql')));

  $result->execute(array(
    ':id' => $election
  ));

  while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $c1 = (int) $row['c1'];
    $c2 = (int) $row['c2'];

    if (!array_key_exists($c1, $graph)) {
      $graph[$c1] = array();
    }

    if (!array_key_exists($c2, $graph)) {
      $graph[$c2] = array();
    }

    if (!rigger_dfs($graph, $c2, $c1, array())) {
      $graph[$c1][$c2] = true;
    }
  }

  $candidates = array_keys($graph);

  foreach ($graph as $c1 => $c2) {
    $candidates = array_diff($candidates, array_keys($c2));
  }

  return $candidates[0];
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

function rigger_stringify($pdo, $arr) {
  return '(' . implode(', ', array_map(array($pdo, 'quote'), $arr)) . ')';
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
