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
?>  </head>
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
  $ranks = array();
  $ranked = rigger_intval($candidates);

  $result = $pdo->prepare(<<<EOF
SELECT `candidates`.`id` AS `candidate`,
  `elections`.`id` AS `election`
FROM `candidates`
INNER JOIN `elections` ON `elections`.`id` = `candidates`.`election`
WHERE `candidates`.`id` IN $ranked
EOF
    );

  $result->execute();
  $rows = $result->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

  foreach ($candidates as $candidate => $rank) {
    @$ranks[(int) $rows[$candidate]][$rank]++;
  }

  array_walk($writeins, function(&$writein, $election, $names) {
    global $ranks;

    @$ranks[(int) $election][$writein]++;

    $writein = array(
      $writein,
      $names[$election]
    );
  }, $writein_names);

  $elections = rigger_intval($ranks);

  foreach ($ranks as $rank) {
    $keys = array_keys($rank);

    if (min($rank) != 1) {
      $error = 'Ranks must start from 1.';
    } elseif (max($rank) >= 2) {
      $error = 'Two candidates may not be given the same rank.';
    } elseif (count($keys) != max($keys)) {
      $error = 'Ranks must be consecutive.';
    }
  }

  if (!$error) {
    $result = $pdo->prepare(<<<EOF
SELECT `elections`.`name` AS `name`
FROM `elections`
WHERE `elections`.`id` IN $elections
  AND `elections`.`closed` IS NOT NULL
EOF
      );

    $result->execute();
    $closed = $result->fetchAll(PDO::FETCH_COLUMN);

    switch (count($closed)) {
      case 0:
        break;
      case 1:
        $error = 'Poll <b>' . blacker_encode($closed[0]) . '</b> has already closed.';
        break;
      case 2:
        $error = 'Polls <b>' . blacker_encode($closed[0]) . '</b> and <b>' . blacker_encode($closed[1]) . '</b> have already closed.';
        break;
      default:
        $error = 'Polls <b>' . implode('</b>, <b>', array_map('blacker_encode', array_slice($closed, 0, -1))) . '</b>, and <b>' . $closed[count($closed) - 1] . '</b> have already closed.';
        break;
    }
  }

  if (!$error) {
    $parameters = array(
      ':user' => $user
    );

    $result = $pdo->prepare(<<<EOF
DELETE FROM `votes`
WHERE `rowid` IN (
  SELECT `votes`.`rowid` FROM `votes`
  INNER JOIN `candidates` ON `candidates`.`id` = `votes`.`candidate`
  WHERE `candidates`.`election` IN $elections
    AND `user` = :user
)
EOF
      );

    $result->execute($parameters);

    $result = $pdo->prepare(<<<EOF
DELETE FROM `writeins`
WHERE `writeins`.`election` IN $elections
  AND `user` = :user
EOF
      );

    $result->execute($parameters);

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

    echo <<<EOF
      <div class="success">Ballot cast successfully.</div>

EOF;
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

$result = $pdo->prepare(<<<EOF
SELECT `elections`.`id` AS `id`,
  `elections`.`name` AS `election`,
  `elections`.`writeins` AS `writeins`,
  `writeins`.`name` AS `writein`,
  `writeins`.`rank` AS `rank`
FROM `elections`
LEFT JOIN `writeins` ON `elections`.`id` = `writeins`.`election`
  AND `writeins`.`user` = :user
WHERE `closed` IS NULL
EOF
  );

$result->execute(array(
  ':user' => $user
));

$c = '';

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
  $title = blacker_encode($row['election']);
  $subrows = rigger_ballot($pdo, $row['id'], $user)->fetchAll(PDO::FETCH_ASSOC);
  $max = count($subrows) + 1;

  $c .= <<<EOF
        <fieldset class="poll">
          <legend>$title</legend>

EOF;

  foreach ($subrows as $subrow) {
    $name = blacker_encode($subrow['name']);

    if (array_key_exists($subrow['id'], $candidates)) {
      $rank = ' value="' . (int) $candidates[$subrow['id']] . '"';
    } elseif ($subrow['rank']) {
      $rank = " value=\"$subrow[rank]\"";
    } else {
      $rank = '';
    }

    $c .= <<<EOF
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
      $name = ' value="' . blacker_encode($writeins[$row['id']][1]) . '"';
      $rank = ' value="' . (int) $writeins[$row['id']][0] . '"';
    } elseif ($row['rank']) {
      $name = ' value="' . blacker_encode($row['writein']) . '"';
      $rank = " value=\"$row[rank]\"";
    } else {
      $name = '';
      $rank = '';
    }

    $c .= <<<EOF
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

  $c .= <<<EOF
        </fieldset>

EOF;
}

if ($c) {
  echo <<<EOF
      <form action="./" method="post">
$c        <div class="form-control">
          <div class="input-group">
            <input type="hidden" name="action" value="vote" />
            <input type="submit" value="Cast Ballots" />
          </div>
        </div>
      </form>
EOF;
} else {
  echo <<<EOF
      <p>There are no polls currently open.</p>

EOF;
}
?>    </div>
<?
print_footer(
  'Copyright &copy; 2017 Will Yu',
  'A service of Blacker House'
);
?>  </body>
</html>
