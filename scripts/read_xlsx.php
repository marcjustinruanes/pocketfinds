<?php
$folder = $argv[1];
$sharedXml = simplexml_load_file($folder.'/xl/sharedStrings.xml');
$sharedXml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$shared = [];
foreach ($sharedXml->xpath('//x:si') as $si) {
    $si->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $parts = [];
    foreach ($si->xpath('.//x:t') as $t) $parts[] = (string)$t;
    $shared[] = implode('', $parts);
}
function colnum(string $ref): int {
    preg_match('/[A-Z]+/', $ref, $m); $n=0;
    foreach (str_split($m[0]) as $c) $n=$n*26+ord($c)-64;
    return $n;
}
$files=glob($folder.'/xl/worksheets/sheet*.xml');
usort($files, fn($a,$b)=>intval(preg_replace('/\D/','',basename($a))) <=> intval(preg_replace('/\D/','',basename($b))));
foreach($files as $file){
    if (isset($argv[2]) && basename($file, '.xml') !== 'sheet'.$argv[2]) continue;
    echo '### '.pathinfo($file,PATHINFO_FILENAME).PHP_EOL;
    $xml=simplexml_load_file($file); $xml->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    foreach($xml->xpath('//x:sheetData/x:row') as $row){
        $row->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values=[];$max=0;
        foreach($row->xpath('./x:c') as $cell){
            $col=colnum((string)$cell['r']);$max=max($max,$col);
            $cell->registerXPathNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $vn=$cell->xpath('./x:v');$value=$vn?(string)$vn[0]:'';
            if((string)$cell['t']==='s' && $value!=='')$value=$shared[(int)$value];
            $values[$col]=preg_replace('/\R/',' / ',$value);
        }
        $line=[];for($i=1;$i<=$max;$i++)$line[]=$values[$i]??'';
        echo implode("\t",$line).PHP_EOL;
    }
}
