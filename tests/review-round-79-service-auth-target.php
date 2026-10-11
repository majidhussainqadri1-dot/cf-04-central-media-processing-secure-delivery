<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Error,ServiceAuth,Utils};
function r79($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 79 FAIL: $m\n");exit(1);}echo "ROUND 79 PASS: $m\n";}
$secret=str_repeat('q',32);add_filter('scm_service_secret',fn($value,string $service)=>$secret);$ts=time();$nonce='round79-nonce';$body='{}';
$target='/sabri-media/v1/downloads?asset=a1&mode=view';
$canonical='file17|GET|'.$target.'|'.$ts.'|'.$nonce.'|'.hash('sha256',$body);$sig=Utils::b64url(hash_hmac('sha256',$canonical,$secret,true));
$denied=false;try{ServiceAuth::verify('file17','GET','/sabri-media/v1/downloads?asset=a2&mode=view',$body,$nonce,$ts,$sig);}catch(Error $e){$denied=$e->errorCode==='service_signature_invalid';}
r79($denied,'query-string tampering invalidates the service signature before nonce consumption');
$ok=ServiceAuth::verify('file17','GET',$target,$body,$nonce,$ts,$sig);r79(($ok['path']??'')===$target,'verified service identity retains the signed origin-form path and query');
$absolute=false;try{ServiceAuth::verify('file17','GET','https://example.test'.$target,$body,'round79-abs',$ts,$sig);}catch(Error $e){$absolute=$e->errorCode==='service_path_invalid';}
r79($absolute,'absolute-form request targets are rejected to avoid host/canonicalization ambiguity');
echo "REVIEW ROUND 79 SERVICE AUTH TARGET: PASS\n";
