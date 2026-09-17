#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 79 was fully completed before corrections began.
# Defect ledger:
# - ServiceAuth canonicalized a request target with parse_url(..., PHP_URL_PATH),
#   discarding the query string before HMAC verification. Service-to-service GET or
#   query-bearing requests could therefore change authorization-relevant query
#   parameters without changing the signature. The request target must bind the
#   exact origin-form path+query and reject absolute/fragment-bearing targets.

p=ROOT/'sabri-central-media/includes/class-scm-rest.php'
s=p.read_text()
old="""    private static function path(string $path): string {$path=parse_url($path,PHP_URL_PATH);if(!is_string($path)||$path===''||strlen($path)>1024||str_contains($path,"\\r")||str_contains($path,"\\n")||preg_match('#(?:^|/)\\.\\.(?:/|$)#',$path)||!str_starts_with($path,'/'))throw new Error('service_path_invalid','Service path invalid.',400);return $path;}
"""
new="""    private static function path(string $target): string {
        if($target===''||strlen($target)>2048||str_contains($target,"\\r")||str_contains($target,"\\n")||str_contains($target,'#'))throw new Error('service_path_invalid','Service request target invalid.',400);
        $parts=parse_url($target);if($parts===false||isset($parts['scheme'])||isset($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment']))throw new Error('service_path_invalid','Service request target must use origin-form path and query only.',400);
        $path=$parts['path']??'';if(!is_string($path)||$path===''||!str_starts_with($path,'/')||preg_match('#(?:^|/)\\.\\.(?:/|$)#',rawurldecode($path)))throw new Error('service_path_invalid','Service path invalid.',400);
        $query=array_key_exists('query',$parts)?(string)$parts['query']:null;return $path.($query!==null?'?'.$query:'');
    }
"""
if old not in s: raise SystemExit('round 79 ServiceAuth path target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-79-service-auth-target.php'
t.write_text(r'''<?php
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
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-78-key-rotation-reference-group.php"\n'
if 'review-round-79-service-auth-target.php' not in x:
    if anchor not in x: raise SystemExit('round 79 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-79-service-auth-target.php"\n',1))
