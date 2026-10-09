<?php

// Migration helper. Never prints passwords or application records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $password = env('MYSQL_MIGRATION_PASSWORD');
    if (!is_string($password) || $password === '') throw new RuntimeException('MYSQL_MIGRATION_PASSWORD is missing.');
    $port = 3307;
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        $process = proc_open([
            'C:\\Program Files\\MySQL\\MySQL Server 26.7\\bin\\mysql.exe',
            '--host=127.0.0.1', '--port='.$port, '--user=root', '--connect-timeout=5',
            '--batch', '--execute=SELECT VERSION() AS version, @@port AS port;',
        ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['MYSQL_PWD' => $password]));
        if (!is_resource($process)) throw new RuntimeException('Could not start MySQL client.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            preg_match('/ERROR (\d+)/', $error, $matches);
            throw new RuntimeException('MySQL client connection failed.', (int) ($matches[1] ?? $code));
        }
        echo $output;
        echo 'Server connection verified. PHP PDO MySQL driver still needs enabling.'.PHP_EOL;
        exit(0);
    }
    $target = new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4', 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    echo 'Connected to local MySQL '.$target->query('SELECT VERSION()')->fetchColumn().' on port '.$target->query('SELECT @@port')->fetchColumn().PHP_EOL;
    $names = $target->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'Target pocketfinds exists: '.(in_array('pocketfinds', $names) ? 'yes' : 'no').PHP_EOL;
} catch (Throwable $e) {
    // Connection exception messages may contain credentials; report only safe details.
    fwrite(STDERR, 'Migration connection failed: '.get_class($e).' (code '.$e->getCode().').'.PHP_EOL);
    if ($e instanceof PDOException && isset($e->errorInfo[1])) {
        fwrite(STDERR, 'MySQL error number: '.(int) $e->errorInfo[1].PHP_EOL);
    }
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        fwrite(STDERR, 'PHP PDO MySQL driver is not enabled.'.PHP_EOL);
    }
    exit(1);
}
