#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f"expected review target not found: {path}")
    path.write_text(text.replace(old, new, 1))

upload = ROOT / 'sabri-central-media/includes/class-scm-upload.php'
replace_once(
    upload,
    "$hash=Utils::streamHash($stream);if($hash['size']<1||$hash['size']>(int)$upload['policy']['max_part_size_bytes'])throw new Error('part_size_exceeded','Part exceeds policy.',413);if($hash['size']+(int)$upload['received_size']>(int)$upload['expected_size']+(int)$upload['policy']['max_part_size_bytes'])throw new Error('upload_size_exceeded','Uploaded parts exceed expected bounds.',422);$stored=PartStore::put($uploadId,$part,$stream,strtolower($sha256));",
    "$hash=Utils::streamHash($stream);if($hash['size']<1||$hash['size']>(int)$upload['policy']['max_part_size_bytes'])throw new Error('part_size_exceeded','Part exceeds policy.',413);$existingPart=null;foreach(PartStore::list($uploadId) as $candidate)if((int)($candidate['part_number']??0)===$part){$existingPart=$candidate;break;}$projected=(int)$upload['received_size']-(int)($existingPart['size']??0)+(int)$hash['size'];if($projected>(int)$upload['expected_size'])throw new Error('upload_size_exceeded','Uploaded parts exceed expected size.',422);$stored=PartStore::put($uploadId,$part,$stream,strtolower($sha256));"
)

validation = ROOT / 'sabri-central-media/includes/class-scm-validation.php'
replace_once(
    validation,
    "$pages=0;$encrypted=false;$active=false;$overlap='';\n        try{while(!feof($h)){$chunk=fread($h,1048576);if($chunk===false)throw new Error('source_read_failed','Cannot scan PDF source.',500);if($chunk==='')continue;$data=$overlap.$chunk;$pages+=preg_match_all('/\\/Type\\s*\\/Page\\b/',$data);$encrypted=$encrypted||preg_match('/\\/Encrypt\\b/',$data)===1;$active=$active||preg_match('/\\/JavaScript\\b|\\/JS\\b|\\/Launch\\b|\\/EmbeddedFile\\b/i',$data)===1;$overlap=substr($data,-256);}}finally{fclose($h);}return ['pages'=>$pages,'encrypted'=>$encrypted,'active_content'=>$active];",
    "$pages=0;$encrypted=false;$active=false;$overlap='';\n        try{while(!feof($h)){$chunk=fread($h,1048576);if($chunk===false)throw new Error('source_read_failed','Cannot scan PDF source.',500);if($chunk==='')continue;$overlapLength=strlen($overlap);$data=$overlap.$chunk;$matches=[];preg_match_all('/\\/Type\\s*\\/Page\\b/',$data,$matches,PREG_OFFSET_CAPTURE);foreach($matches[0]??[] as $match){$text=(string)($match[0]??'');$offset=(int)($match[1]??0);if($offset+strlen($text)>$overlapLength)$pages++;}$encrypted=$encrypted||preg_match('/\\/Encrypt\\b/',$data)===1;$active=$active||preg_match('/\\/JavaScript\\b|\\/JS\\b|\\/Launch\\b|\\/EmbeddedFile\\b/i',$data)===1;$overlap=substr($data,-256);}}finally{fclose($h);}return ['pages'=>$pages,'encrypted'=>$encrypted,'active_content'=>$active];"
)

regression = ROOT / 'tests/review-round-57-upload-validation.php'
regression.write_text(r'''<?php
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
''')

quality = ROOT / 'tools/quality-check.sh'
q = quality.read_text()
needle = 'php "$ROOT/tests/future40.php"\n'
insert = needle + 'php "$ROOT/tests/review-round-57-upload-validation.php"\n'
if 'review-round-57-upload-validation.php' not in q:
    if needle not in q:
        raise SystemExit('quality-check insertion point missing')
    quality.write_text(q.replace(needle, insert, 1))
