<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,RevocationDispatchService};

// These deliberately non-deliverable pending events prove that a bad low-ID
// dispatch cannot suppress later events, and that the reconciler never needs a
// complete global inventory before doing bounded work.
RecordStore::resetMemory();
for($i=1;$i<=210;$i++){
    $id=sprintf('%064x',$i);
    RecordStore::put('revocation_dispatch',$id,[
        'actor_id'=>0,'status'=>'pending',
        'event_id'=>'r223-invalid-'.(string)$i,'asset_id'=>'missing-asset',
        'owner_domain'=>'file17','object_version'=>1
    ]);
}
$first=RevocationDispatchService::reconcile(100);
$c=RecordStore::get('cron_cursor','revocation-dispatch');
ok($first['checked']===100&&$first['failed']===100
    &&($c['last_id']??null)===sprintf('%064x',100),
    'R223 first 100 failed events progress cursor past initial keyset page');
$second=RevocationDispatchService::reconcile(100);
$c=RecordStore::get('cron_cursor','revocation-dispatch');
ok($second['checked']===100&&$second['failed']===100
    &&($c['last_id']??null)===sprintf('%064x',200),
    'R223 second run reaches next 100 pending entries without rescanning beginning');
$third=RevocationDispatchService::reconcile(100);
$c=RecordStore::get('cron_cursor','revocation-dispatch');
ok($third['checked']===10&&$third['failed']===10
    &&($c['last_id']??null)===sprintf('%064x',210),
    'R223 third run reaches last ten pending entries');
$wrap=RevocationDispatchService::reconcile(100);
$c=RecordStore::get('cron_cursor','revocation-dispatch');
ok($wrap['checked']===0&&($c['last_id']??null)===str_repeat('0',64),
    'R223 end-of-keyspace wraps durable cursor using canonical 64-hex start sentinel');
$retry=RevocationDispatchService::reconcile(1);
ok($retry['checked']===1&&$retry['failed']===1,
    'R223 pending failures can be retried in a later full traversal');
$c=RecordStore::get('cron_cursor','revocation-dispatch');
$c['last_id']='invalid cursor';
RecordStore::put('cron_cursor','revocation-dispatch',$c,(int)$c['version']);
err(fn()=>RevocationDispatchService::reconcile(1),'revocation_cursor_invalid',
    'R223 malformed cursor remains fail-closed');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-plan-parity.php');
ok(!str_contains($source,"RecordStore::all('revocation_dispatch',0,'pending',100000)")
    &&str_contains($source,"RecordStore::scan('revocation_dispatch'"),
    'R223 old 100k whole-inventory revocation dependency is eliminated');
echo "REVIEW ROUND 223 REVOCATION KEYSET FAIRNESS: PASS\n";
