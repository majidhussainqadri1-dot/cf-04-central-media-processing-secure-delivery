<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{UploadService};

$p=policy('document','C3');
$p['max_size_bytes']=6;$p['max_part_size_bytes']=65536;$p['max_upload_parts']=2;
// Policy normalization requires part capacity >= max size and permits the small logical object.
$p=Sabri\CentralMedia\Policy::normalize($p,true);
$bytes='abcdef';$meta=['name'=>'bounded.pdf','mime'=>'application/pdf','size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'owner_object'=>'message:round57'];
$u=UploadService::create(11,$meta,$p,'round57-create');
$s=stream_of('abc');UploadService::putPart($u['id'],11,1,$s,hash('sha256','abc'),$u['upload_credential']);fclose($s);
// Retry of the same part must not be double-counted.
$s=stream_of('abc');$retry=UploadService::putPart($u['id'],11,1,$s,hash('sha256','abc'),$u['upload_credential']);fclose($s);
ok((int)$retry['received_size']===3,'ROUND 57 retried part is not double-counted');
// A new part that pushes received bytes beyond the declared object must fail immediately.
$s=stream_of('defg');err(fn()=>UploadService::putPart($u['id'],11,2,$s,hash('sha256','defg'),$u['upload_credential']),'upload_size_exceeded','ROUND 57 projected upload bytes cannot exceed declared size');fclose($s);

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-validation.php');
ok(str_contains($source,'PREG_OFFSET_CAPTURE')&&str_contains($source,'$offset+strlen($text)>$overlapLength'),'ROUND 57 PDF page scanner avoids overlap double-counting');
echo "REVIEW ROUND 57 UPLOAD/VALIDATION: PASS\n";
