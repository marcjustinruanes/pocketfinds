<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
$backup=storage_path('app/private/mysql-migration');
$manifest=json_decode(file_get_contents($backup.'/manifest.json'),true,flags:JSON_THROW_ON_ERROR);
$count=0;
$issues=[];
foreach (['supabase'=>'public','supabase_messages'=>'messages','profile_images'=>'profile_images'] as $source=>$disk) foreach ($manifest['files'][$source] ?? [] as $path=>$metadata) {
    if (!Storage::disk($disk)->exists($path) || hash('sha256',Storage::disk($disk)->get($path))!==$metadata['sha256']) $issues[]='Copied file checksum mismatch on '.$disk;
    else $count++;
}
$check = function (?string $path,string $disk,string $field) use (&$issues): void {
    if (!$path) return;
    if (str_contains($path,'.supabase.co')) { $issues[]='Old remote URL remains in '.$field; return; }
    if (str_starts_with($path,'http')) {
        $url=Storage::disk($disk)->url('');
        if (!str_starts_with($path,$url)) return; // External Google avatars etc.
        $path=substr($path,strlen($url));
    }
    $path=preg_replace('#^/storage/(profiles/)?#','',$path);
    if (!Storage::disk($disk)->exists(ltrim($path,'/'))) {
        // Legacy chat uploads sometimes shared the product bucket. Preserve them
        // in private storage so the existing authenticated route still works.
        if ($disk==='messages' && Storage::disk('public')->exists($path)) Storage::disk('messages')->put($path,Storage::disk('public')->get($path));
        else $issues[]='Missing referenced local file in '.$field;
    }
};
foreach (App\Models\Product::all() as $p) {
    foreach (array_merge([$p->image,$p->video],$p->images ?? []) as $path) $check($path,'public','products.image/video/gallery');
    foreach ($p->variations ?? [] as $variation) foreach ($variation['options'] ?? [] as $option) $check($option['image'] ?? null,'public','products.variations');
}
foreach (App\Models\User::all() as $u) {
    $check($u->profile_picture,'profile_images','users.profile_picture');
    foreach (['id_file','selfie_file','business_permit_file','or_file','cr_file','license_file','resume_file','company_logo'] as $field) $check($u->$field,'public','users.'.$field);
}
foreach (App\Models\Message::whereNotNull('attachment_path')->get() as $m) $check($m->attachment_path,'messages','messages.attachment_path');
foreach (App\Models\Complaint::whereNotNull('evidence_path')->get() as $c) $check($c->evidence_path,'public','complaints.evidence_path');
$check(App\Models\Setting::current()->hero_image,'public','settings.hero_image');
$remoteValues=0;
foreach (array_keys($manifest['tables']) as $table) foreach (DB::table($table)->get() as $row) foreach ((array)$row as $value) if (is_string($value) && str_contains($value,'.supabase.co')) $remoteValues++;
if ($remoteValues) $issues[]='Remote URLs remain in '.$remoteValues.' stored values';
$result=['verified_at'=>gmdate('c'),'copied_files_verified'=>$count,'remaining_remote_urls'=>$remoteValues,'issues'=>array_count_values($issues)];
file_put_contents($backup.'/assets-verified.json',json_encode($result,JSON_PRETTY_PRINT));
echo 'Verified '.$count.' migrated file checksums; remote URLs remaining: '.$remoteValues.'.'.PHP_EOL;
foreach (array_count_values($issues) as $issue=>$n) echo $issue.': '.$n.PHP_EOL;
exit($issues ? 1 : 0);
