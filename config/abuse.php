<?php
// =====================================================================
// Abuse detection — our own approach
// ---------------------------------------------------------------------
// How it works:
//   1. Load every vote (and every vote ATTEMPT) of one voting window.
//   2. Run 8 small detectors over that data in PHP. Each detector looks for
//      one pattern from the spec and returns "cases".
//   3. Every case says, in plain language, WHAT happened and WHO is involved
//      (suspected = did the suspicious voting, affected = received it).
//   4. Cases are saved in abuse_flags (with their students and votes) so the
//      staff decision (confirmed / false alarm) is remembered.
//
// The system never punishes anyone automatically: it only points staff at
// unusual patterns. Staff decide whether to invalidate the votes.
//
// All thresholds are here so they are easy to explain and to tune.
// =====================================================================

const ABUSE = [
    'pair_fast_minutes'   => 10,  // two students rate each other within 10 min
    'group_min_voters'    => 3,   // 3+ students give the SAME stars to one student...
    'group_minutes'       => 15,  // ...within 15 minutes
    'bulk_min_votes'      => 5,   // 5+ votes land on one student...
    'bulk_minutes'        => 30,  // ...within 30 minutes
    'fast_min_votes'      => 4,   // one student casts 4+ votes...
    'fast_seconds'        => 180, // ...within 3 minutes
    'often_extra_attempts'=> 3,   // attempts > max votes + 3 = "too often"
    'repeat_min_attempts' => 3,   // 3+ attempts on the same student
    'extreme_min_votes'   => 4,   // 4+ votes that are ALL 1 star or ALL 3 stars
    'serious_min_others'  => 3,   // a 1-star "serious" vote is suspicious when 3+ others...
    'serious_min_avg'     => 2.5, // ...rated the same student 2.5+ on average
];

const ABUSE_TYPES = [
    'friends_boosting' => ['label' => 'Friends boosting',     'icon' => 'ring'],
    'pair_voting'      => ['label' => 'Pair voting / revenge', 'icon' => 'pair'],
    'group_voting'     => ['label' => 'Group voting',         'icon' => 'group'],
    'bulk_votes'       => ['label' => 'Bulk votes',           'icon' => 'bulk'],
    'too_fast'         => ['label' => 'Too fast / too often', 'icon' => 'clock'],
    'repeated_votes'   => ['label' => 'Repeated votes',       'icon' => 'repeat'],
    'extreme_voter'    => ['label' => 'Extreme voter',        'icon' => 'bolt'],
    'serious_vote'     => ['label' => 'Malicious serious vote','icon' => 'flag'],
];

// ---------- small formatting helpers ----------

function ab_name(array $u): string
{
    return $u['full_name'] . ' (' . $u['username'] . ')';
}

function ab_time(string $dt): string
{
    return date('D H:i', strtotime($dt));
}

function ab_minutes(int $seconds): string
{
    if ($seconds < 120) {
        return $seconds . ' seconds';
    }
    $m = (int) round($seconds / 60);
    return $m . ($m === 1 ? ' minute' : ' minutes');
}

function ab_stars(int $n): string
{
    return $n . ($n === 1 ? ' star' : ' stars');
}

// ---------- data loading ----------

function ab_load(PDO $pdo, int $windowId): array
{
    $users = [];
    foreach ($pdo->query("SELECT id, full_name, username FROM users") as $u) {
        $u['id'] = (int) $u['id'];
        $users[$u['id']] = $u;
    }

    $stmt = $pdo->prepare(
        "SELECT r.id, r.voter_id, r.voter_to_id, r.stars, r.created_at, r.status,
                GROUP_CONCAT(l.title ORDER BY l.title SEPARATOR '|') AS labels,
                MAX(l.category = 'serious') AS has_serious
         FROM ratings r
         LEFT JOIN rating_labels rl ON rl.rating_id = r.id
         LEFT JOIN labels l ON l.id = rl.label_id
         WHERE r.voting_window_id = ?
         GROUP BY r.id
         ORDER BY r.created_at, r.id"
    );
    $stmt->execute([$windowId]);
    $ratings = [];
    foreach ($stmt as $r) {
        $ratings[] = [
            'id'          => (int) $r['id'],
            'voter'       => (int) $r['voter_id'],
            'target'      => (int) $r['voter_to_id'],
            'stars'       => (int) $r['stars'],
            'created_at'  => $r['created_at'],
            'ts'          => strtotime($r['created_at']),
            'status'      => $r['status'],
            'labels'      => $r['labels'] ? explode('|', $r['labels']) : [],
            'has_serious' => (bool) $r['has_serious'],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT voter_id, target_id, outcome, created_at
         FROM vote_attempts WHERE voting_window_id = ? ORDER BY created_at"
    );
    $stmt->execute([$windowId]);
    $attempts = $stmt->fetchAll();

    return [$users, $ratings, $attempts];
}

// Build one case in the shape the API returns.
function ab_case(string $type, string $key, string $severity, string $summary,
                 array $suspected, array $affected, array $ratings, array $users): array
{
    $students = [];
    foreach (array_unique($suspected) as $id) {
        $students[$id] = ['id' => $id, 'role' => 'suspected'] + $users[$id];
    }
    foreach (array_unique($affected) as $id) {
        if (!isset($students[$id])) {
            $students[$id] = ['id' => $id, 'role' => 'affected'] + $users[$id];
        }
    }

    $times = array_column($ratings, 'ts');
    $rows = [];
    foreach ($ratings as $r) {
        $rows[] = [
            'id'         => $r['id'],
            'voter'      => $users[$r['voter']]['username'],
            'target'     => $users[$r['target']]['username'],
            'stars'      => $r['stars'],
            'labels'     => $r['labels'],
            'created_at' => $r['created_at'],
            'status'     => $r['status'],
        ];
    }

    return [
        'case_key'   => $key,
        'type'       => $type,
        'type_label' => ABUSE_TYPES[$type]['label'],
        'severity'   => $severity,
        'summary'    => $summary,
        'students'   => array_values($students),
        'ratings'    => $rows,
        'time_from'  => $times ? date('Y-m-d H:i:s', min($times)) : null,
        'time_to'    => $times ? date('Y-m-d H:i:s', max($times)) : null,
    ];
}

// ---------- the detectors ----------

// Find groups of 3+ students who all gave each other 3 stars (a "ring").
function detect_friends_boosting(array $ratings, array $users, int $w, array &$ringPairs): array
{
    $given = [];
    foreach ($ratings as $r) {
        if ($r['stars'] === 3) {
            $given[$r['voter']][$r['target']] = $r;
        }
    }
    // mutual 3-star edges
    $adj = [];
    foreach ($given as $a => $targets) {
        foreach ($targets as $b => $r) {
            if (isset($given[$b][$a])) {
                $adj[$a][$b] = true;
            }
        }
    }
    // connected groups of mutual 3-star edges
    $seen = [];
    $cases = [];
    foreach (array_keys($adj) as $start) {
        if (isset($seen[$start])) {
            continue;
        }
        $group = [];
        $stack = [$start];
        while ($stack) {
            $n = array_pop($stack);
            if (isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            $group[] = $n;
            foreach (array_keys($adj[$n] ?? []) as $m) {
                $stack[] = $m;
            }
        }
        if (count($group) < 3) {
            continue;
        }
        sort($group);
        $rs = [];
        foreach ($group as $a) {
            foreach ($group as $b) {
                if ($a !== $b && isset($given[$a][$b])) {
                    $rs[] = $given[$a][$b];
                    $ringPairs[min($a, $b) . '-' . max($a, $b)] = true;
                }
            }
        }
        usort($rs, fn($x, $y) => $x['ts'] <=> $y['ts']);
        $names = array_map(fn($id) => $users[$id]['full_name'], $group);
        $last = array_pop($names);
        $summary = count($group) . ' students — ' . implode(', ', $names) . ' and ' . $last
            . ' — gave each other 3 stars (' . count($rs) . ' votes in total). '
            . 'A closed circle of friends rating each other at the top can inflate their averages.';
        $cases[] = ab_case('friends_boosting', "ring:$w:" . implode('-', $group), 'high',
            $summary, $group, [], $rs, $users);
    }
    return $cases;
}

// Two students who rated each other: mutual 3 stars, revenge, or suspiciously close in time.
function detect_pair_voting(array $ratings, array $users, int $w, array $ringPairs): array
{
    $byPair = [];
    foreach ($ratings as $r) {
        $byPair[$r['voter'] . '>' . $r['target']] = $r;
    }
    $cases = [];
    $done = [];
    foreach ($ratings as $r1) {
        $a = $r1['voter'];
        $b = $r1['target'];
        $key = min($a, $b) . '-' . max($a, $b);
        if (isset($done[$key]) || !isset($byPair["$b>$a"])) {
            continue;
        }
        $done[$key] = true;
        $r2 = $byPair["$b>$a"];
        [$first, $second] = $r1['ts'] <= $r2['ts'] ? [$r1, $r2] : [$r2, $r1];
        $gap = $second['ts'] - $first['ts'];
        $A = $users[$first['voter']];
        $B = $users[$second['voter']];

        if ($second['stars'] === 1 && $first['stars'] <= 2) {
            $summary = "Possible revenge: {$A['full_name']} gave {$B['full_name']} "
                . ab_stars($first['stars']) . ', and ' . ab_minutes($gap) . " later {$B['full_name']} "
                . 'answered with 1 star' . ($second['labels'] ? ' ("' . implode('", "', $second['labels']) . '")' : '') . '.';
            $cases[] = ab_case('pair_voting', "pair:$w:$key", 'high', $summary,
                [$second['voter']], [$first['voter']], [$first, $second], $users);
        } elseif ($first['stars'] === 3 && $second['stars'] === 3) {
            if (isset($ringPairs[$key])) {
                continue; // already reported as part of a friends-boosting ring
            }
            $summary = "{$A['full_name']} and {$B['full_name']} gave each other 3 stars ("
                . ab_minutes($gap) . ' apart). Mutual top ratings between two students can be a trade.';
            $cases[] = ab_case('pair_voting', "pair:$w:$key", 'medium', $summary,
                [$first['voter'], $second['voter']], [], [$first, $second], $users);
        } elseif ($gap <= ABUSE['pair_fast_minutes'] * 60) {
            $summary = "{$A['full_name']} and {$B['full_name']} rated each other within "
                . ab_minutes($gap) . ' (' . ab_stars($first['stars']) . ' and ' . ab_stars($second['stars']) . ').';
            $cases[] = ab_case('pair_voting', "pair:$w:$key", 'low', $summary,
                [$first['voter'], $second['voter']], [], [$first, $second], $users);
        }
    }
    return $cases;
}

// For one target, find clusters of votes inside a sliding time window.
function ab_clusters(array $list, int $seconds, int $min): array
{
    $clusters = [];
    $n = count($list);
    $i = 0;
    while ($i < $n) {
        $j = $i;
        while ($j + 1 < $n && $list[$j + 1]['ts'] - $list[$i]['ts'] <= $seconds) {
            $j++;
        }
        if ($j - $i + 1 >= $min) {
            $clusters[] = array_slice($list, $i, $j - $i + 1);
            $i = $j + 1; // do not report overlapping clusters twice
        } else {
            $i++;
        }
    }
    return $clusters;
}

// Several students giving the same stars to one student in a short time.
function detect_group_voting(array $ratings, array $users, int $w, array &$groupSets): array
{
    $byTargetStars = [];
    foreach ($ratings as $r) {
        $byTargetStars[$r['target']][$r['stars']][] = $r;
    }
    $cases = [];
    foreach ($byTargetStars as $target => $byStars) {
        foreach ($byStars as $stars => $list) {
            foreach (ab_clusters($list, ABUSE['group_minutes'] * 60, ABUSE['group_min_voters']) as $c) {
                $voters = array_column($c, 'voter');
                $span = end($c)['ts'] - $c[0]['ts'];
                $T = $users[$target];
                $summary = count($c) . ' students gave ' . $T['full_name'] . ' exactly ' . ab_stars($stars)
                    . ' within ' . ab_minutes(max($span, 60)) . ' (' . ab_time($c[0]['created_at']) . ' – '
                    . date('H:i', end($c)['ts']) . '). Votes this coordinated look organised.';
                $ids = array_column($c, 'id');
                sort($ids);
                $groupSets[implode(',', $ids)] = true;
                $cases[] = ab_case('group_voting', "group:$w:$target:$stars:" . $c[0]['id'],
                    $stars === 1 ? 'high' : 'medium', $summary, $voters, [$target], $c, $users);
            }
        }
    }
    return $cases;
}

// Many votes (any stars) landing on one student in a short time.
function detect_bulk_votes(array $ratings, array $users, int $w, array $groupSets): array
{
    $byTarget = [];
    foreach ($ratings as $r) {
        $byTarget[$r['target']][] = $r;
    }
    $cases = [];
    foreach ($byTarget as $target => $list) {
        foreach (ab_clusters($list, ABUSE['bulk_minutes'] * 60, ABUSE['bulk_min_votes']) as $c) {
            $ids = array_column($c, 'id');
            sort($ids);
            if (isset($groupSets[implode(',', $ids)])) {
                continue; // identical to a group-voting case
            }
            $span = end($c)['ts'] - $c[0]['ts'];
            $avg = round(array_sum(array_column($c, 'stars')) / count($c), 1);
            $summary = $users[$target]['full_name'] . ' received ' . count($c) . ' votes within '
                . ab_minutes(max($span, 60)) . ' (average ' . $avg . ' stars). '
                . 'That is far more attention than students usually get at once.';
            $cases[] = ab_case('bulk_votes', "bulk:$w:$target:" . $c[0]['id'], 'medium', $summary,
                array_column($c, 'voter'), [$target], $c, $users);
        }
    }
    return $cases;
}

// One student voting very fast, or trying to vote far more than allowed.
function detect_too_fast(array $ratings, array $attempts, array $users, int $w, int $maxVotes): array
{
    $byVoter = [];
    foreach ($ratings as $r) {
        $byVoter[$r['voter']][] = $r;
    }
    $cases = [];
    foreach ($byVoter as $voter => $list) {
        foreach (ab_clusters($list, ABUSE['fast_seconds'], ABUSE['fast_min_votes']) as $c) {
            $span = end($c)['ts'] - $c[0]['ts'];
            $summary = $users[$voter]['full_name'] . ' submitted ' . count($c) . ' ratings in only '
                . ab_minutes(max($span, 1)) . '. That is too fast to have thought about each person.';
            $cases[] = ab_case('too_fast', "fast:$w:$voter:" . $c[0]['id'], 'medium', $summary,
                [$voter], array_column($c, 'target'), $c, $users);
        }
    }
    // too often: many attempts compared with the vote limit
    $count = [];
    foreach ($attempts as $a) {
        $count[(int) $a['voter_id']] = ($count[(int) $a['voter_id']] ?? 0) + 1;
    }
    foreach ($count as $voter => $n) {
        if ($n > $maxVotes + ABUSE['often_extra_attempts']) {
            $summary = $users[$voter]['full_name'] . " tried to vote $n times in this window, "
                . "while the limit is $maxVotes votes. Most of the attempts were rejected.";
            $cases[] = ab_case('too_fast', "often:$w:$voter", 'medium', $summary,
                [$voter], [], $byVoter[$voter] ?? [], $users);
        }
    }
    return $cases;
}

// The same student trying again and again to rate the same student.
function detect_repeated(array $ratings, array $attempts, array $users, int $w): array
{
    $pairs = [];
    foreach ($attempts as $a) {
        if ($a['target_id'] === null) {
            continue;
        }
        $k = $a['voter_id'] . '>' . $a['target_id'];
        $pairs[$k][] = $a;
    }
    $cases = [];
    foreach ($pairs as $k => $list) {
        if (count($list) < ABUSE['repeat_min_attempts']) {
            continue;
        }
        [$voter, $target] = array_map('intval', explode('>', $k));
        $rejected = count(array_filter($list, fn($a) => $a['outcome'] !== 'accepted'));
        $rs = array_values(array_filter($ratings, fn($r) => $r['voter'] === $voter && $r['target'] === $target));
        $summary = $users[$voter]['full_name'] . ' tried to rate ' . $users[$target]['full_name'] . ' '
            . count($list) . " times ($rejected rejected), between " . ab_time($list[0]['created_at'])
            . ' and ' . date('H:i', strtotime(end($list)['created_at'])) . '. Only one vote per person is allowed.';
        $cases[] = ab_case('repeated_votes', "repeat:$w:$voter-$target", 'medium', $summary,
            [$voter], [$target], $rs, $users);
    }
    return $cases;
}

// Someone who only ever gives 1 star, or only ever 3 stars.
function detect_extreme(array $ratings, array $users, int $w): array
{
    $byVoter = [];
    foreach ($ratings as $r) {
        $byVoter[$r['voter']][] = $r;
    }
    $cases = [];
    foreach ($byVoter as $voter => $list) {
        if (count($list) < ABUSE['extreme_min_votes']) {
            continue;
        }
        $stars = array_unique(array_column($list, 'stars'));
        if (count($stars) !== 1 || !in_array($stars[0], [1, 3], true)) {
            continue;
        }
        $s = $stars[0];
        $summary = $users[$voter]['full_name'] . ' gave ' . ab_stars($s) . ' to every one of the '
            . count($list) . ' students they rated. '
            . ($s === 1 ? 'Rating everyone at the bottom suggests the votes are not honest.'
                        : 'Rating everyone at the top makes their votes meaningless.');
        $cases[] = ab_case('extreme_voter', "extreme:$w:$voter", $s === 1 ? 'high' : 'low', $summary,
            [$voter], array_column($list, 'target'), $list, $users);
    }
    return $cases;
}

// A 1-star vote with a serious label that nobody else backs up.
function detect_serious(array $ratings, array $users, int $w): array
{
    $byTarget = [];
    foreach ($ratings as $r) {
        if ($r['status'] !== 'cancelled') {
            $byTarget[$r['target']][] = $r;
        }
    }
    $cases = [];
    foreach ($ratings as $r) {
        if ($r['stars'] !== 1 || !$r['has_serious']) {
            continue;
        }
        $others = array_filter($byTarget[$r['target']] ?? [], fn($o) => $o['voter'] !== $r['voter']);
        if (count($others) < ABUSE['serious_min_others']) {
            continue;
        }
        $avg = array_sum(array_column($others, 'stars')) / count($others);
        $backed = array_filter($others, fn($o) => $o['has_serious']);
        if ($avg < ABUSE['serious_min_avg'] || $backed) {
            continue;
        }
        $summary = $users[$r['voter']]['full_name'] . ' gave ' . $users[$r['target']]['full_name']
            . ' 1 star for "' . implode('", "', $r['labels']) . '", but ' . count($others)
            . ' other students rated them ' . number_format($avg, 1) . ' on average and nobody else reported a serious issue.';
        $cases[] = ab_case('serious_vote', "serious:$w:" . $r['id'], 'high', $summary,
            [$r['voter']], [$r['target']], [$r], $users);
    }
    return $cases;
}

// ---------- run everything ----------

function detect_abuse(PDO $pdo, array $window): array
{
    $w = (int) $window['id'];
    [$users, $ratings, $attempts] = ab_load($pdo, $w);

    $ringPairs = [];
    $groupSets = [];
    $cases = array_merge(
        detect_friends_boosting($ratings, $users, $w, $ringPairs),
        detect_pair_voting($ratings, $users, $w, $ringPairs),
        detect_group_voting($ratings, $users, $w, $groupSets),
        detect_bulk_votes($ratings, $users, $w, $groupSets),
        detect_too_fast($ratings, $attempts, $users, $w, (int) $window['max_votes_per_student']),
        detect_repeated($ratings, $attempts, $users, $w),
        detect_extreme($ratings, $users, $w),
        detect_serious($ratings, $users, $w)
    );

    // newest first
    usort($cases, fn($a, $b) => strcmp((string) $b['time_to'], (string) $a['time_to']));
    return $cases;
}

// Save detected cases in abuse_flags (+ students and votes) and attach the
// staff decision that was stored earlier for the same case.
function sync_cases(PDO $pdo, array $cases): array
{
    if (!$cases) {
        return [];
    }
    $insertFlag = $pdo->prepare(
        "INSERT INTO abuse_flags (case_key, abuse_type, reason_explanation)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE reason_explanation = VALUES(reason_explanation)"
    );
    $getFlag = $pdo->prepare(
        "SELECT f.id, f.review_status, f.reviewed_at, u.username AS reviewed_by
         FROM abuse_flags f LEFT JOIN users u ON u.id = f.reviewed_by_staff_id
         WHERE f.case_key = ?"
    );
    $addStudent = $pdo->prepare(
        "INSERT IGNORE INTO abuse_flag_students (abuse_flag_id, student_id, role_in_activity) VALUES (?, ?, ?)"
    );
    $addRating = $pdo->prepare(
        "INSERT IGNORE INTO abuse_flag_ratings (abuse_flag_id, rating_id) VALUES (?, ?)"
    );

    foreach ($cases as &$c) {
        $insertFlag->execute([$c['case_key'], $c['type'], $c['summary']]);
        $getFlag->execute([$c['case_key']]);
        $flag = $getFlag->fetch();
        $c['flag_id'] = (int) $flag['id'];
        $c['review_status'] = $flag['review_status'];
        $c['reviewed_by'] = $flag['reviewed_by'];
        $c['reviewed_at'] = $flag['reviewed_at'];
        foreach ($c['students'] as $s) {
            $addStudent->execute([$c['flag_id'], $s['id'], $s['role']]);
        }
        foreach ($c['ratings'] as $r) {
            $addRating->execute([$c['flag_id'], $r['id']]);
        }
    }
    return $cases;
}
