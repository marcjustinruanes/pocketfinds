<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/api/bootstrap.php';
if (!extension_loaded('gd')) throw new RuntimeException('Enable PHP GD to compute image signatures.');
$write=in_array('--write',$argv,true); $updated=0; $skipped=0;
$root=realpath(storage_path('app/public'));
foreach (Illuminate\Support\Facades\DB::table('products')->where('status','active')->whereNull('image_signature')->get(['id','image']) as $product) {
    $relative=preg_replace('#^/?storage/#','',(string)$product->image);
    // Read only local storage; no cloud credentials or remote image fetches.
    $file=realpath($root.DIRECTORY_SEPARATOR.$relative);
    if (!$file || !str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))) { $skipped++; continue; }
    $image=@imagecreatefromstring(file_get_contents($file));
    if (!$image) { $skipped++; continue; }
    $signature=[];
    // Match ImageSignatureService's default nearest-neighbor 4x4 resize.
    for ($y=0;$y<4;$y++) for ($x=0;$x<4;$x++) {
        $pixel=imagecolorsforindex($image,imagecolorat($image,(int)floor($x*imagesx($image)/4),(int)floor($y*imagesy($image)/4)));
        foreach (['red','green','blue'] as $channel) $signature[]=$pixel[$channel]/255;
    }
    if ($write) Illuminate\Support\Facades\DB::table('products')->where('id',$product->id)->whereNull('image_signature')->update(['image_signature'=>json_encode($signature)]);
    $updated++;
}
echo ($write?'Updated ':'Dry run: ').$updated.' local product signatures; '.$skipped." unavailable images skipped.\n";
if (!$write) echo "Run with --write to save the signatures in pocketfinds.\n";
