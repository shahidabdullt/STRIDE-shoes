<?php

/**
 * Spike S6: MySQL 8 InnoDB FULLTEXT behaviour for product/FAQ search (BLUEPRINT §1.3).
 *
 *   php spikes/s6_fulltext.php [host] [port] [db] [user] [password]
 *   defaults: 127.0.0.1 3307 stride_testing root <SPIKE_MYSQL_PASSWORD env or "secret">
 *
 * Creates and drops a throwaway table `spike_ft_products`.
 */
[$host, $port, $db, $user] = [$argv[1] ?? '127.0.0.1', $argv[2] ?? '3307', $argv[3] ?? 'stride_testing', $argv[4] ?? 'root'];
$password = $argv[5] ?? (getenv('SPIKE_MYSQL_PASSWORD') ?: 'secret');

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$version = $pdo->query('SELECT VERSION()')->fetchColumn();
echo "Server: $version\n";
foreach (['innodb_ft_min_token_size', 'innodb_ft_enable_stopword', 'ft_boolean_syntax', 'sql_mode'] as $var) {
    $row = $pdo->query("SHOW VARIABLES LIKE '$var'")->fetch(PDO::FETCH_NUM);
    echo "  $var = ".($row[1] ?? '?')."\n";
}

$pdo->exec('DROP TABLE IF EXISTS spike_ft_products');
$pdo->exec('CREATE TABLE spike_ft_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    FULLTEXT KEY ft_name_desc (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$rows = [
    ['Trailblazer GTX', 'Waterproof trail running shoe with a rock plate for rugged trails.'],
    ['Cloudrunner', 'Lightweight road running shoe with a cushioned ride.'],
    ['Summit Hiker', 'Hiking boot for mountain trails, waterproof membrane.'],
    ['City Walker', 'Casual walking shoe for everyday city use. Wide fit available.'],
    ['Pace 5K', 'Racing flat for 5K and 10K runs.'],
    ['Zen Trainer', 'Gym training shoe; flat, stable and grippy.'],
];
$insert = $pdo->prepare('INSERT INTO spike_ft_products (name, description) VALUES (?, ?)');
foreach ($rows as $r) {
    $insert->execute($r);
}

$cases = [
    'natural: trail' => ['NATURAL LANGUAGE MODE', 'trail'],
    'natural: trails' => ['NATURAL LANGUAGE MODE', 'trails'],
    'boolean: trail*' => ['BOOLEAN MODE', 'trail*'],
    'boolean: +waterproof +trail*' => ['BOOLEAN MODE', '+waterproof +trail*'],
    'boolean: run* (running/runner/runs)' => ['BOOLEAN MODE', 'run*'],
    'short token: 5K' => ['BOOLEAN MODE', '5K'],
    'short token: gym' => ['BOOLEAN MODE', 'gym'],
    'stopword: "for"' => ['BOOLEAN MODE', 'for'],
    'hyphen/special chars: "rock-plate"' => ['BOOLEAN MODE', 'rock-plate'],
    'user input with operators: trail) (' => ['BOOLEAN MODE', 'trail) ('],
];

$stmt = fn ($mode) => $pdo->prepare("SELECT name, ROUND(MATCH(name, description) AGAINST (? IN $mode), 3) AS score
    FROM spike_ft_products WHERE MATCH(name, description) AGAINST (? IN $mode) ORDER BY score DESC");

foreach ($cases as $label => [$mode, $q]) {
    try {
        $s = $stmt($mode);
        $s->execute([$q, $q]);
        $hits = array_map(fn ($r) => "{$r['name']} ({$r['score']})", $s->fetchAll(PDO::FETCH_ASSOC));
        echo str_pad($label, 40).' -> '.($hits ? implode(', ', $hits) : '(no rows)')."\n";
    } catch (PDOException $e) {
        echo str_pad($label, 40).' -> ERROR '.$e->getCode().': '.$e->getMessage()."\n";
    }
}

$like = $pdo->prepare('SELECT name FROM spike_ft_products WHERE name LIKE ? OR description LIKE ?');
$like->execute(['%5K%', '%5K%']);
echo str_pad('LIKE fallback: %5K%', 40).' -> '.implode(', ', $like->fetchAll(PDO::FETCH_COLUMN))."\n";

$pdo->exec('DROP TABLE spike_ft_products');
echo "dropped spike_ft_products\n";
