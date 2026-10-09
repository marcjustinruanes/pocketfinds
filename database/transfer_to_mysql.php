<?php

// One-time transfer; private backups and records are never printed.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
function qi(string $s): string { return '`'.str_replace('`','``',$s).'`'; }
function rewriteNested(mixed $value): mixed {
    global $urlReplacements;
    if (is_string($value)) return str_replace(array_keys($urlReplacements), array_values($urlReplacements), $value);
    if (is_array($value)) foreach ($value as &$child) $child = rewriteNested($child);
    return $value;
}
function normalized(mixed $value, string $type): mixed {
    if ($value === null) return null;
    if ($type === 'boolean') return in_array($value, [true, 1, '1', 't', 'true'], true) ? '1' : '0';
    if (str_starts_with($type, 'timestamp')) return (new DateTimeImmutable((string)$value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format(str_contains($type, '(0)') ? 'Y-m-d H:i:s' : 'Y-m-d H:i:s.u');
    if (in_array($type, ['json', 'jsonb', 'vector(48)'])) {
        $decoded = rewriteNested(json_decode((string)$value, true, flags: JSON_THROW_ON_ERROR));
        $sort = function (&$v) use (&$sort) { if (!is_array($v)) return; if (!array_is_list($v)) ksort($v); foreach ($v as &$child) $sort($child); };
        $sort($decoded);
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
    if (str_starts_with($type, 'numeric')) {
        $str = (string)$value;
        if (str_contains($str,'.')) $str = rtrim(rtrim($str, '0'), '.');
        return $str === '-0' ? '0' : $str;
    }
    return rewriteNested((string)$value);
}
$backup = storage_path('app/private/mysql-migration');
if (!is_dir($backup)) mkdir($backup, 0700, true);
$mode = $argv[1] ?? '--check';
$urlReplacements = file_exists($backup.'/url-replacements.json') ? json_decode(file_get_contents($backup.'/url-replacements.json'),true,flags:JSON_THROW_ON_ERROR) : [];
try {
    $target = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', env('MYSQL_MIGRATION_PASSWORD', env('DB_PASSWORD')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
    echo 'MySQL '.$target->query('SELECT VERSION()')->fetchColumn().' on port 3307'.PHP_EOL;
    $inventory = json_decode(file_get_contents(__DIR__.'/schema_inventory.json'), true, flags: JSON_THROW_ON_ERROR);
    $tables = [];
    foreach ($inventory['columns'] as $column) $tables[$column['table_name']][] = $column;
    if ($mode === '--check') {
        echo 'pocketfinds exists: '.($target->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='pocketfinds'")->fetchColumn() ? 'yes' : 'no').PHP_EOL;
    } elseif ($mode === '--export') {
        if (file_exists($backup.'/manifest.json')) throw new RuntimeException('Snapshot already exists; refusing to overwrite');
        if (DB::connection()->getDriverName() !== 'pgsql') throw new RuntimeException('Source must still be PostgreSQL');
        copy(base_path('.env'), $backup.'/source.env');
        file_put_contents($backup.'/source-config.json', json_encode(['database' => config('database.connections.pgsql'), 'disks' => config('filesystems.disks')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $pdo = DB::connection()->getPdo();
        $pdo->exec('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        $pdo->exec("SET LOCAL TIME ZONE 'UTC'");
        $actual = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        if ($actual !== array_keys($tables)) throw new RuntimeException('Live tables changed since inventory; refresh schema');
        $manifest = ['captured_at' => gmdate('c'), 'tables' => [], 'files' => []];
        foreach ($tables as $name => $columns) {
            $query = $pdo->query('SELECT * FROM "'.$name.'"');
            $file = fopen($backup.'/'.$name.'.jsonl', 'w');
            $count = 0;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                if (array_keys($row) !== array_column($columns,'column_name')) throw new RuntimeException('Columns changed for '.$name);
                fwrite($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
            fclose($file);
            $manifest['tables'][$name] = ['rows' => $count, 'sha256' => hash_file('sha256', $backup.'/'.$name.'.jsonl')];
            echo 'Backed up '.$name.': '.$count.' rows'.PHP_EOL;
        }
        foreach (['indexes' => "SELECT tablename, indexname, indexdef FROM pg_indexes WHERE schemaname='public'", 'triggers' => "SELECT event_object_table, trigger_name, action_statement FROM information_schema.triggers WHERE trigger_schema='public'"] as $label => $sql) file_put_contents($backup.'/'.$label.'.json', json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
        $pdo->exec('COMMIT');
        copy(__DIR__.'/schema_inventory.json', $backup.'/schema_inventory.json');
        file_put_contents($backup.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo 'Snapshot complete.'.PHP_EOL;
    } elseif ($mode === '--import') {
        $manifest = json_decode(file_get_contents($backup.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if ($target->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='pocketfinds'")->fetchColumn()) throw new RuntimeException('Target database exists; refusing to overwrite');
        $schema = str_replace('`pocketfinds_review`', '`pocketfinds`', file_get_contents(__DIR__.'/pocketfinds-workbench-schema.sql'));
        file_put_contents(__DIR__.'/pocketfinds-mysql-schema.sql', $schema);
        $target->exec($schema);
        $target->exec('USE pocketfinds');
        $target->exec('SET FOREIGN_KEY_CHECKS=0');
        $target->beginTransaction();
        foreach ($tables as $name => $columns) {
            if (hash_file('sha256', $backup.'/'.$name.'.jsonl') !== $manifest['tables'][$name]['sha256']) throw new RuntimeException('Backup checksum mismatch');
            $stmt = $target->prepare('INSERT INTO '.qi($name).' ('.implode(',',array_map('qi',array_column($columns,'column_name'))).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');
            $file = fopen($backup.'/'.$name.'.jsonl','r');
            $count = 0;
            while (($line = fgets($file)) !== false) {
                $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $values = [];
                foreach ($columns as $column) $values[] = normalized($row[$column['column_name']], $column['data_type']);
                $stmt->execute($values);
                $count++;
            }
            fclose($file);
            echo 'Imported '.$name.': '.$count.' rows'.PHP_EOL;
        }
        $target->commit();
        $target->exec('SET FOREIGN_KEY_CHECKS=1');
        file_put_contents($backup.'/import-complete', gmdate('c'));
    } elseif ($mode === '--verify') {
        $target->exec('USE pocketfinds');
        $manifest = json_decode(file_get_contents($backup.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $total = 0;
        foreach ($tables as $name => $columns) {
            $count = (int)$target->query('SELECT COUNT(*) FROM '.qi($name))->fetchColumn();
            $expectedCount = $manifest['tables'][$name]['rows'];
            if ($name === 'migrations') {
                $expectedCount += (int)$target->query("SELECT COUNT(*) FROM migrations WHERE migration IN ('2026_10_04_000000_create_mysql_baseline','2026_10_04_000001_create_mysql_queue_tables')")->fetchColumn();
            }
            if ($count !== $expectedCount) throw new RuntimeException('Row count differs: '.$name);
            $key = $name === 'cache' || $name === 'cache_locks' ? 'key' : 'id';
            $stmt = $target->prepare('SELECT * FROM '.qi($name).' WHERE '.qi($key).' = ?');
            $file = fopen($backup.'/'.$name.'.jsonl','r');
            while (($line=fgets($file)) !== false) {
                $source = json_decode($line,true,flags:JSON_THROW_ON_ERROR);
                $stmt->execute([$source[$key]]);
                $row=$stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Missing row: '.$name);
                foreach ($columns as $c) if (normalized($source[$c['column_name']],$c['data_type']) !== normalized($row[$c['column_name']],$c['data_type'])) throw new RuntimeException('Value differs: '.$name.'.'.$c['column_name']);
                $total++;
            }
            fclose($file);
        }
        foreach ($inventory['constraints'] as $c) if ($c['constraint_type'] === 'f') {
            preg_match('/FOREIGN KEY \(([^)]+)\) REFERENCES ([a-z_]+)\(([^)]+)\)/',$c['definition'],$m);
            $orphans = $target->query('SELECT COUNT(*) FROM '.qi($c['table_name']).' c LEFT JOIN '.qi($m[2]).' p ON c.'.qi($m[1]).'=p.'.qi($m[3]).' WHERE c.'.qi($m[1]).' IS NOT NULL AND p.'.qi($m[3]).' IS NULL')->fetchColumn();
            if ($orphans) throw new RuntimeException('Orphaned FK: '.$c['constraint_name']);
        }
        file_put_contents($backup.'/database-verified.json', json_encode(['verified_at'=>gmdate('c'),'tables'=>count($tables),'rows'=>$total,'foreign_keys'=>71], JSON_PRETTY_PRINT));
        echo 'Verified all values in '.count($tables).' tables / '.$total.' rows and all foreign keys.'.PHP_EOL;
    } elseif ($mode === '--rewrite-urls') {
        if (!file_exists($backup.'/database-verified.json') || !file_exists($backup.'/files-verified')) throw new RuntimeException('Verify database and files first');
        $target->exec('USE pocketfinds');
        $source = json_decode(file_get_contents($backup.'/source-config.json'),true,flags:JSON_THROW_ON_ERROR);
        $replacements = [
            rtrim($source['disks']['supabase']['url'],'/').'/' => '/storage/',
            rtrim($source['disks']['supabase_messages']['url'],'/').'/' => '/message-media/',
            rtrim($source['disks']['profile_images']['url'],'/').'/' => '/storage/profiles/',
        ];
        $rewrite = function ($value) use (&$rewrite,$replacements) {
            if (is_string($value)) return str_replace(array_keys($replacements),array_values($replacements),$value);
            if (is_array($value)) foreach ($value as &$child) $child=$rewrite($child);
            return $value;
        };
        $changed=0;
        $target->beginTransaction();
        foreach ($tables as $name=>$columns) {
            $key=in_array($name,['cache','cache_locks']) ? 'key' : 'id';
            foreach ($target->query('SELECT * FROM '.qi($name))->fetchAll(PDO::FETCH_ASSOC) as $row) foreach ($columns as $column) {
                $value=$row[$column['column_name']];
                if (!is_string($value) || !str_contains($value,'.supabase.co')) continue;
                $isJson=in_array($column['data_type'],['json','jsonb']);
                $new=$isJson ? json_encode($rewrite(json_decode($value,true,flags:JSON_THROW_ON_ERROR)),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) : $rewrite($value);
                if ($new!==$value) {
                    $stmt=$target->prepare('UPDATE '.qi($name).' SET '.qi($column['column_name']).'=? WHERE '.qi($key).'=?');
                    $stmt->execute([$new,$row[$key]]);
                    $changed++;
                }
            }
        }
        $target->commit();
        // Older profile pictures were stored in the product bucket. Preserve them
        // in the local profile disk too, without changing their saved paths.
        foreach ($target->query('SELECT profile_picture FROM users WHERE profile_picture IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $path) {
            if (Storage::disk('public')->exists($path) && !Storage::disk('profile_images')->exists($path)) Storage::disk('profile_images')->put($path,Storage::disk('public')->get($path));
        }
        file_put_contents($backup.'/url-replacements.json',json_encode($replacements,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        echo 'Replaced old storage URLs in '.$changed.' column values; profile paths reconciled.'.PHP_EOL;
    } elseif ($mode === '--indexes') {
        $target->exec('USE pocketfinds');
        $target->exec(file_get_contents(__DIR__.'/mysql-extra-indexes.sql'));
        echo 'Preserved source lookup and conditional unique indexes.'.PHP_EOL;
    } elseif ($mode === '--files') {
        $source = json_decode(file_get_contents($backup.'/source-config.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest = json_decode(file_get_contents($backup.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (['supabase' => 'app/public', 'supabase_messages' => 'app/private/messages', 'profile_images' => 'app/public/profiles'] as $disk => $root) {
            $remote = Storage::build($source['disks'][$disk]);
            $local = Storage::build(['driver'=>'local','root'=>storage_path($root),'throw'=>true]);
            $count = 0;
            foreach ($remote->allFiles() as $path) {
                $stream = $remote->readStream($path);
                if (!is_resource($stream)) throw new RuntimeException('Cannot read remote file');
                $bytes = stream_get_contents($stream);
                fclose($stream);
                if ($local->exists($path) && hash('sha256',$local->get($path)) !== hash('sha256',$bytes)) throw new RuntimeException('Local file conflict on '.$disk);
                if (!$local->exists($path)) $local->put($path,$bytes);
                if (hash('sha256',$local->get($path)) !== hash('sha256',$bytes)) throw new RuntimeException('File checksum differs');
                $manifest['files'][$disk][$path] = ['bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)];
                $count++;
            }
            file_put_contents($backup.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            echo 'Copied and SHA-256 verified '.$count.' files from '.$disk.PHP_EOL;
        }
        file_put_contents($backup.'/files-verified',gmdate('c'));
    } else throw new RuntimeException('Unknown mode');
} catch (Throwable $e) {
    $safe = get_class($e) === RuntimeException::class;
    fwrite(STDERR, $safe ? $e->getMessage().PHP_EOL : 'Migration failed: '.get_class($e).' code '.$e->getCode().PHP_EOL);
    if ($e instanceof PDOException && isset($e->errorInfo[1])) fwrite(STDERR,'MySQL error number: '.(int)$e->errorInfo[1].PHP_EOL);
    exit(1);
}
