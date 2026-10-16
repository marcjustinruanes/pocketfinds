<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
$result = ['driver' => config('database.default'), 'host' => $connection['host'], 'port' => $connection['port'],
    'database' => $connection['database'], 'verified' => false];
try {
    $pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';dbname='.$connection['database'].';charset=utf8mb4',
        $connection['username'], $connection['password'], [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $result['server_version'] = $pdo->query('SELECT VERSION()')->fetchColumn();
    $result['verified'] = true;
} catch (PDOException $error) {
    $result['sqlstate'] = $error->getCode();
    $result['driver_error'] = $error->errorInfo[1] ?? null;
    $result['reason'] = 'The configured MySQL connection failed. No credentials or data were changed.';
}
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/mysql-connection.json', $json);
echo $json.PHP_EOL;
exit($result['verified'] ? 0 : 1);
