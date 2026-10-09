<?php

require __DIR__.'/../vendor/autoload.php';
$root=dirname(__DIR__);
$backup=$root.'/storage/app/private/mysql-migration';
foreach (['database-verified.json','files-verified','app-verified.json'] as $proof) {
    if (!file_exists($backup.'/'.$proof)) { fwrite(STDERR,"Verification incomplete; no environment change made.\n"); exit(1); }
}
$text=file_get_contents($root.'/.env');
if (!preg_match('/^MYSQL_MIGRATION_PASSWORD=(.*)$/m',$text,$m) || trim($m[1])==='') { fwrite(STDERR,"Saved MySQL migration password missing.\n"); exit(1); }
$password=trim($m[1]);
copy($root.'/.env',$backup.'/before-cutover.env');
$updates=['DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3307','DB_DATABASE'=>'pocketfinds','DB_USERNAME'=>'root','DB_PASSWORD'=>$password,'FILESYSTEM_DISK'=>'public'];
$out=[];
foreach (preg_split('/\r?\n/',$text) as $line) {
    if (preg_match('/^(AWS_[A-Z_]+|SUPABASE_[A-Z_]+|MYSQL_MIGRATION_PASSWORD|DB_SSLMODE|DB_URL)=/',$line)) continue;
    if (str_starts_with($line,'#') && preg_match('/Supabase|supabase|migration password/i',$line)) continue;
    if (preg_match('/^([A-Z_]+)=/',$line,$match) && array_key_exists($match[1],$updates)) {
        $out[]=$match[1].'='.$updates[$match[1]];
        unset($updates[$match[1]]);
    } else $out[]=$line;
}
foreach ($updates as $key=>$value) $out[]=$key.'='.$value;
file_put_contents($root.'/.env',implode(PHP_EOL,$out));
echo "Saved local MySQL and local storage settings; active Supabase credentials removed.\n";
