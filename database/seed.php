<?php
// =====================================================================
// Demo data seeder — run from the terminal only:
//     php database/seed.php
//
// WARNING: wipes all users, windows, ratings and abuse cases, then loads
// fictional demo data. Labels are kept. Never run it on real data.
//
// It creates:
//   - 30 fictional students + 2 staff accounts
//   - a past (closed) window and the current open window
//   - normal background votes
//   - one planted example of EVERY abuse pattern, so the abuse page has
//     something real to show in the demo
//
// Times are relative to "now", so re-run it right before the demo.
// All names are invented.
// =====================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require __DIR__ . '/../config/db.php';
mt_srand(42); // same "random" data every run

const STUDENT_PASSWORD = 'Student42!';
const STAFF_PASSWORD   = 'Staff42!';

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (['abuse_flag_ratings', 'abuse_flag_students', 'abuse_flags', 'audit_logs', 'vote_attempts',
          'rating_labels', 'ratings', 'voting_windows', 'users'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ---------- users ----------
$names = [
    'Lina Haddad', 'Omar Khalil', 'Sara Nasser', 'Yazan Odeh', 'Khaled Mansour', 'Noor Azzam',
    'Hamza Qasem', 'Laith Barakat', 'Rawan Tamimi', 'Zaid Hourani', 'Haya Masri', 'Tala Hijazi',
    'Mohammad Shubaki', 'Aya Kurdi', 'Faisal Rifai', 'Dima Sabbagh', 'Yousef Nimri', 'Leen Daoud',
    'Fares Abed', 'Rana Jaber', 'Ahmad Zoubi', 'Mira Shami', 'Jana Abbadi', 'Basel Haddadin',
    'Tamer Salameh', 'Reem Awwad', 'Sami Khoury', 'Hala Bitar', 'Adam Qudah', 'Maya Fakhoury',
];

$studentHash = password_hash(STUDENT_PASSWORD, PASSWORD_DEFAULT);
$staffHash = password_hash(STAFF_PASSWORD, PASSWORD_DEFAULT);
$insertUser = $pdo->prepare(
    "INSERT INTO users (full_name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)"
);

$S = []; // index (1-based) => user id
$used = [];
foreach ($names as $i => $name) {
    [$first, $last] = explode(' ', $name);
    $login = strtolower($first[0] . substr($last, 0, 7));
    while (isset($used[$login])) {
        $login .= 'x';
    }
    $used[$login] = true;
    $insertUser->execute([$name, $login, "$login@student.demo", $studentHash, 'student']);
    $S[$i + 1] = (int) $pdo->lastInsertId();
}
$insertUser->execute(['Peer Review Staff', 'staff', 'staff@staff.demo', $staffHash, 'staff']);
$insertUser->execute(['Bootcamp Mentor', 'mentor', 'mentor@staff.demo', $staffHash, 'staff']);

// ---------- windows ----------
$now = strtotime($pdo->query("SELECT NOW()")->fetchColumn());
$dt = fn(int $ts) => date('Y-m-d H:i:s', $ts);

$insertWindow = $pdo->prepare(
    "INSERT INTO voting_windows (title, start_time, end_time, min_votes_per_student, max_votes_per_student, status)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$insertWindow->execute(['October peer review', $dt($now - 30 * 86400), $dt($now - 23 * 86400), 2, 5, 'closed']);
$past = (int) $pdo->lastInsertId();
$insertWindow->execute([
    'November peer review', $dt($now - 2 * 86400),
    date('Y-m-d 21:00:00', $now + 2 * 86400), 2, 5, 'active',
]);
$current = (int) $pdo->lastInsertId();

// ---------- labels by star level ----------
$labels = $pdo->query("SELECT id, title, category, min_stars, max_stars FROM labels WHERE status = 'active'")->fetchAll();
$L = [];
foreach ($labels as $l) {
    $L[$l['title']] = (int) $l['id'];
}
function labels_for(array $labels, int $stars, bool $allowSerious = false): array
{
    $ok = array_filter($labels, fn($l) => $stars >= $l['min_stars'] && $stars <= $l['max_stars']
        && ($allowSerious || $l['category'] !== 'serious'));
    $ok = array_values($ok);
    shuffle($ok);
    return array_map(fn($l) => (int) $l['id'], array_slice($ok, 0, mt_rand(1, 2)));
}

// ---------- vote helper ----------
$insRating = $pdo->prepare(
    "INSERT INTO ratings (voter_id, voter_to_id, voting_window_id, stars, created_at) VALUES (?, ?, ?, ?, ?)"
);
$insLabel = $pdo->prepare("INSERT INTO rating_labels (rating_id, label_id) VALUES (?, ?)");
$insAttempt = $pdo->prepare(
    "INSERT INTO vote_attempts (voter_id, target_id, voting_window_id, outcome, created_at) VALUES (?, ?, ?, ?, ?)"
);
$given = [];   // [window][voter] => count
$rated = [];   // [window]["a>b"] => true

$vote = function (int $a, int $b, int $w, int $stars, array $labelIds, int $ts)
    use ($pdo, $insRating, $insLabel, $insAttempt, $dt, &$given, &$rated) {
    $insRating->execute([$a, $b, $w, $stars, $dt($ts)]);
    $id = (int) $pdo->lastInsertId();
    foreach (array_unique($labelIds) as $l) {
        $insLabel->execute([$id, $l]);
    }
    $insAttempt->execute([$a, $b, $w, 'accepted', $dt($ts)]);
    $given[$w][$a] = ($given[$w][$a] ?? 0) + 1;
    $rated[$w]["$a>$b"] = true;
    return $id;
};

$h = 3600;
$m = 60;

// =====================================================================
// PLANTED CASES (current window) — one per abuse pattern
// =====================================================================

// 1. Friends boosting: Lina, Omar, Sara, Yazan all give each other 3 stars (yesterday evening).
$ring = [$S[1], $S[2], $S[3], $S[4]];
$base = $now - 26 * $h;
for ($round = 0; $round < 3; $round++) {
    foreach ($ring as $i => $a) {
        $b = $ring[($i + $round + 1) % 4];
        $vote($a, $b, $current, 3, [$L['Helpful'], $L['Good teammate']], $base + $round * 45 * $m + $i * 2 * $m);
    }
}

// 2. Pair voting / revenge: Khaled gives Noor 2 stars, 18 minutes later Noor answers with 1 star.
$t = $now - 20 * $h;
$vote($S[5], $S[6], $current, 2, [$L['Late']], $t);
$vote($S[6], $S[5], $current, 1, [$L['Disrespectful']], $t + 18 * $m);

// 3. Group voting: four students give Hamza 1 star within 7 minutes.
$t = $now - 30 * $h;
foreach ([8, 9, 10, 11] as $k => $i) {
    $vote($S[$i], $S[7], $current, 1, [$k % 2 ? $L['Disrespectful'] : $L['Aggressive behavior']], $t + $k * 2 * $m + 20);
}

// 4. Bulk votes: Tala receives 6 votes (mixed stars) within 22 minutes.
$t = $now - 14 * $h;
$bulk = [[13, 3, 0], [14, 2, 4], [15, 3, 9], [16, 1, 13], [17, 2, 17], [18, 3, 21]];
foreach ($bulk as [$i, $stars, $min]) {
    $vote($S[$i], $S[12], $current, $stars, $stars === 1 ? [$L['Late']] : labels_for($labels, $stars), $t + $min * $m);
}

// 5. Too fast: Fares submits 5 votes in about 100 seconds.
$t = $now - 5 * $h;
foreach ([[25, 3], [26, 2], [27, 3], [28, 2], [29, 3]] as $k => [$i, $stars]) {
    $vote($S[19], $S[$i], $current, $stars, labels_for($labels, $stars), $t + $k * 25);
}

// 6. Extreme voter: Rana gives 1 star to everyone she rates (5 people over a day).
$t = $now - 34 * $h;
foreach ([2, 9, 15, 24, 27] as $k => $i) {
    $vote($S[20], $S[$i], $current, 1, [$k % 2 ? $L['Rushed'] : $L['Late']], $t + $k * 5 * $h);
}

// 7. Repeated votes: Ahmad rates Mira, then tries 3 more times (rejected as duplicates).
$t = $now - 9 * $h;
$vote($S[21], $S[22], $current, 2, [$L['Rushed']], $t);
foreach ([3, 5, 8] as $min) {
    $insAttempt->execute([$S[21], $S[22], $current, 'duplicate', $dt($t + $min * $m)]);
}

// 8. Malicious serious vote: Jana is well rated, Basel gives her 1 star "Aggressive behavior".
foreach ([[25, 3, 40], [26, 3, 31], [28, 2, 22], [14, 3, 12]] as [$i, $stars, $hoursAgo]) {
    $vote($S[$i], $S[23], $current, $stars, labels_for($labels, $stars), $now - $hoursAgo * $h);
}
$vote($S[24], $S[23], $current, 1, [$L['Aggressive behavior']], $now - 3 * $h);

// =====================================================================
// BACKGROUND votes — normal behaviour, built so it does not trigger
// the detectors by accident (no mutual pairs, mixed stars, spread in time)
// =====================================================================
$protectedTargets = [$S[7], $S[12], $S[22], $S[23], $S[5], $S[6]];
$nonVoters = [$S[29], $S[30]]; // two students who never vote (participation stats)

$background = function (int $w, int $from, int $to, int $perVoterMin, int $perVoterMax, array $skipTargets, array $nonVoters)
    use ($S, $labels, $vote, &$given, &$rated) {
    $ids = array_values($S);
    foreach ($ids as $a) {
        if (in_array($a, $nonVoters, true)) {
            continue;
        }
        $want = mt_rand($perVoterMin, $perVoterMax);
        $starsUsed = [];
        $tries = 0;
        while (($given[$w][$a] ?? 0) < $want && $tries++ < 60) {
            $b = $ids[array_rand($ids)];
            if ($b === $a || in_array($b, $skipTargets, true) || in_array($b, $nonVoters, true)
                || isset($rated[$w]["$a>$b"]) || isset($rated[$w]["$b>$a"])) {
                continue;
            }
            $roll = mt_rand(1, 100);
            $stars = $roll <= 55 ? 3 : ($roll <= 88 ? 2 : 1);
            if (count($starsUsed) >= 2 && count(array_unique($starsUsed)) === 1) {
                $stars = $starsUsed[0] === 2 ? 3 : 2; // keep everyone's votes mixed
            }
            $starsUsed[] = $stars;
            $vote($a, $b, $w, $stars, labels_for($labels, $stars), mt_rand($from, $to));
        }
    }
};

$background($current, $now - 46 * $h, $now - 1 * $h, 2, 4, $protectedTargets, $nonVoters);
$background($past, $now - 30 * 86400 + $h, $now - 23 * 86400 - $h, 1, 3, [], []);

// A short audit trail so the log is not empty
$staffId = (int) $pdo->query("SELECT id FROM users WHERE username = 'staff'")->fetchColumn();
$pdo->prepare("INSERT INTO audit_logs (staff_id, action_type, target_id, description, created_at) VALUES (?, ?, ?, ?, ?)")
    ->execute([$staffId, 'window_create', $current, 'Created window "November peer review", votes 2-5', $dt($now - 2 * 86400)]);

// ---------- summary ----------
$count = fn($sql) => $pdo->query($sql)->fetchColumn();
echo "Seeded:\n";
echo "  students: " . $count("SELECT COUNT(*) FROM users WHERE role='student'") . "  (password: " . STUDENT_PASSWORD . ")\n";
echo "  staff:    staff, mentor  (password: " . STAFF_PASSWORD . ")\n";
echo "  ratings:  " . $count("SELECT COUNT(*) FROM ratings WHERE voting_window_id=$current") . " in the open window, "
    . $count("SELECT COUNT(*) FROM ratings WHERE voting_window_id=$past") . " in the past window\n";
echo "  try student logins: lhaddad (ring member), rjaber (extreme voter), mfakhour (has not voted yet)\n";
