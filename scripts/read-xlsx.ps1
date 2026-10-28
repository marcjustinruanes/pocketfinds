param([Parameter(Mandatory=$true)][string]$Folder)

[xml]$sharedXml = Get-Content -LiteralPath (Join-Path $Folder 'xl\sharedStrings.xml') -Raw
$ns = New-Object Xml.XmlNamespaceManager($sharedXml.NameTable)
$ns.AddNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main')
$shared = @()
foreach ($si in $sharedXml.SelectNodes('//x:si',$ns)) {
    $parts = @($si.SelectNodes('.//x:t',$ns) | ForEach-Object { $_.'#text' })
    $shared += ($parts -join '')
}

function Column-Number([string]$ref) {
    $letters = $ref -replace '[^A-Z]',''
    $n = 0
    foreach ($ch in $letters.ToCharArray()) { $n = $n * 26 + ([int]$ch - [int][char]'A' + 1) }
    return $n
}

$files = Get-ChildItem -LiteralPath (Join-Path $Folder 'xl\worksheets') -Filter 'sheet*.xml' | Sort-Object { [int]($_.BaseName -replace 'sheet','') }
foreach ($file in $files) {
    [xml]$xml = Get-Content -LiteralPath $file.FullName -Raw
    $m = New-Object Xml.XmlNamespaceManager($xml.NameTable)
    $m.AddNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main')
    Write-Output "### $($file.BaseName)"
    foreach ($row in $xml.SelectNodes('//x:sheetData/x:row',$m)) {
        $values = @{}
        $max = 0
        foreach ($cell in $row.SelectNodes('./x:c',$m)) {
            $col = Column-Number $cell.r
            $max = [Math]::Max($max,$col)
            $v = $cell.SelectSingleNode('./x:v',$m)
            $value = if ($null -eq $v) { '' } elseif ($cell.t -eq 's') { $shared[[int]$v.InnerText] } else { $v.InnerText }
            $values[$col] = $value -replace "`r?`n", ' / '
        }
        $line = for($i=1;$i -le $max;$i++){ if($values.ContainsKey($i)){$values[$i]}else{''} }
        Write-Output (($line | ForEach-Object {[string]$_}) -join "`t")
    }
}
