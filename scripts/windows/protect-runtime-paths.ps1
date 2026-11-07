param([switch]$Apply)
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$identity=[Security.Principal.WindowsIdentity]::GetCurrent()
$operatorSid=$identity.User
$paths=@('.env','storage/app/private','storage/logs','bootstrap/cache')
$items=@()
Add-Type @'
using System;
using System.Runtime.InteropServices;
public static class PocketFindsReparse {
 [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)] public struct Data {
  public uint Attributes; public System.Runtime.InteropServices.ComTypes.FILETIME Creation,Access,Write;
  public uint SizeHigh,SizeLow,Tag,Reserved;
  [MarshalAs(UnmanagedType.ByValTStr,SizeConst=260)] public string Name;
  [MarshalAs(UnmanagedType.ByValTStr,SizeConst=14)] public string Alternate;
 }
 [DllImport("kernel32.dll",CharSet=CharSet.Unicode,SetLastError=true)] public static extern IntPtr FindFirstFile(string path,out Data data);
 [DllImport("kernel32.dll")] public static extern bool FindClose(IntPtr handle);
 public static uint Tag(string path) { Data data; var handle=FindFirstFile(path,out data); if(handle==new IntPtr(-1)) throw new System.ComponentModel.Win32Exception(); try{return data.Tag;}finally{FindClose(handle);} }
}
'@
foreach($relative in $paths){
    $path=Join-Path $root $relative
    if(!(Test-Path -LiteralPath $path)){throw "Required path missing: $relative"}
    $item=Get-Item -LiteralPath $path -Force
    $items+=$item
    if($item.PSIsContainer){$items+=@(Get-ChildItem -LiteralPath $path -Force -Recurse)}
}
foreach($item in $items){
    if(($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
        $tag=[PocketFindsReparse]::Tag($item.FullName)
        # OneDrive cloud placeholders are not path redirections. Refuse junctions/symlinks and all other tags.
        if(($tag -band [Convert]::ToUInt32('ffff0fff',16)) -ne [Convert]::ToUInt32('9000001a',16)){throw 'Unknown reparse point requires review; no changes made.'}
    }
    if(!$item.FullName.StartsWith($root+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Path outside workspace'}
}
if(!$Apply){[pscustomobject]@{Operator=$identity.Name;Items=$items.Count;ChangesApplied=$false}|ConvertTo-Json;exit}
$snapshot=Join-Path $root ('storage/app/private/runtime-acl-before-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'.json')
$before=foreach($item in $items){[pscustomobject]@{Path=$item.FullName;Sddl=(Get-Acl -LiteralPath $item.FullName).Sddl}}
$before|ConvertTo-Json -Depth 3|Set-Content -LiteralPath $snapshot -Encoding UTF8
$sids=@($operatorSid,[Security.Principal.SecurityIdentifier]::new('S-1-5-18'),[Security.Principal.SecurityIdentifier]::new('S-1-5-32-544'))
$count=0
foreach($item in $items){
    $acl=Get-Acl -LiteralPath $item.FullName
    $acl.SetAccessRuleProtection($true,$false)
    foreach($rule in @($acl.GetAccessRules($true,$false,[Security.Principal.SecurityIdentifier]))){[void]$acl.RemoveAccessRuleSpecific($rule)}
    $inheritance=if($item.PSIsContainer){[Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'}else{[Security.AccessControl.InheritanceFlags]::None}
    foreach($sid in $sids){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($sid,[Security.AccessControl.FileSystemRights]::FullControl,$inheritance,[Security.AccessControl.PropagationFlags]::None,[Security.AccessControl.AccessControlType]::Allow))}
    Set-Acl -LiteralPath $item.FullName -AclObject $acl
    $count++
    if(($count % 5000)-eq 0){Write-Output "Secured $count items"}
}
[pscustomobject]@{Operator=$identity.Name;Items=$count;ChangesApplied=$true;RecoveryAclSnapshot=$snapshot}|ConvertTo-Json
# Future service identities must be granted the documented scoped permissions by an Administrator.
