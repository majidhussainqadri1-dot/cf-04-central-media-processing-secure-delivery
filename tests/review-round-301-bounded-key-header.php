<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderRegistry,Utils};
$store=ProviderRegistry::get('source-private');
$key=hash('sha256','r301-private-key');
$input=Utils::tempStream();
Utils::writeAll($input,'r301-verified-encrypted-source');
rewind($input);
try{$store->putStream($key,$input);}finally{fclose($input);}
ok($store->objectEncryptionKeyId($key)==='test-v1',
    'R301 canonical encrypted-object header yields original physical key ID');
$path=SCM_PRIVATE_ROOT.'/source/'.substr($key,0,2).'/'.$key.'.scm';
$original=file_get_contents($path);
ok(is_string($original)&&$original!=='',
    'R301 private source fixture exists outside public webroot');
file_put_contents($path,str_repeat('X',70000));
err(fn()=> $store->objectEncryptionKeyId($key),'object_envelope_invalid',
    'R301 overlarge malicious header denied without unbounded line read');
$headerEnd=strpos($original,"\n");
ok($headerEnd!==false,'R301 canonical encrypted header is line-delimited');
$forged=json_encode(['format'=>'INCORRECT-FORMAT','key_id'=>'test-v1',
    'chunk_size'=>1048576],JSON_THROW_ON_ERROR);
file_put_contents($path,$forged."\n".substr($original,$headerEnd+1));
err(fn()=> $store->objectEncryptionKeyId($key),'object_envelope_invalid',
    'R301 wrong ciphertext format cannot claim a trusted encryption key');
file_put_contents($path,$original);
ok($store->objectEncryptionKeyId($key)==='test-v1',
    'R301 restored sealed ciphertext still yields the verified original key');
echo "REVIEW ROUND 301 BOUNDED CIPHER HEADER INSPECTION: PASS\n";
