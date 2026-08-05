<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Crypto,DomainRegistry,Error,Idempotency,RecordStore,ServiceAuth,Utils,Validator,WebhookService};
err(fn()=>Crypto::verify('broken'),'grant_invalid','malformed grant denied');
err(fn()=>Validator::range('bytes=0-99999999',100000000,1024),'range_not_satisfiable','oversized range denied');
$f=hash('sha256','one');Idempotency::claim('adversarial','key',$f);err(fn()=>Idempotency::claim('adversarial','key',hash('sha256','two')),'idempotency_conflict','idempotency fingerprint conflict');
$r=RecordStore::put('cas','row',['status'=>'active']);RecordStore::put('cas','row',['status'=>'changed'],(int)$r['version']);err(fn()=>RecordStore::put('cas','row',['status'=>'stale'],(int)$r['version']),'record_version_conflict','stale CAS denied');
DomainRegistry::reset();err(fn()=>DomainRegistry::decision('file17','authorize_delivery',[]),'domain_contract_unavailable','missing owner fails closed');
$secret=str_repeat('x',32);$timestamp=time();$body='{}';$event='event';$signature=Utils::b64url(hash_hmac('sha256','provider|'.$event.'|'.$timestamp.'|'.hash('sha256',$body),$secret,true));$first=WebhookService::verify('provider',$event,$timestamp,$body,$signature,$secret);$second=WebhookService::verify('provider',$event,$timestamp,$body,$signature,$secret);ok($first['replay']===false&&$second['replay']===true,'webhook replay idempotent');
err(fn()=>WebhookService::verify('provider','event2',$timestamp,$body,'wrong',$secret),'webhook_signature_invalid','forged webhook denied');
echo "REVIEW ROUND 13 ADVERSARIAL: PASS\n";
