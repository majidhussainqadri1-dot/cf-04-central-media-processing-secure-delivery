<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,RetentionService,Utils};

RecordStore::resetMemory();
$now=Utils::now();
// Every historical terminal row must be skippable without a global list.
// The last live schedule must remain reachable after 2,100 older IDs.
for($i=1;$i<=2100;$i++){
    $id=sprintf('%064x',$i);
    RecordStore::put('retention',$id,[
        'actor_id'=>0,'asset_id'=>'historical-'.$i,
        'status'=>'deletion_requested','retention_class'=>'private',
        'deletion_id'=>'prior-deletion-'.$i,
        'source_delete_at'=>$now+3000,'derivative_delete_at'=>$now+3000,
        'temporary_cleanup_at'=>$now+3000,'backup_expiry_at'=>$now+6000,
    ]);
}
$assetId='r235-live-asset';
$policyHash=hash('sha256','r235-policy');
$retentionId=hash('sha256',$assetId.'|'.$policyHash);
ok(strcmp($retentionId,sprintf('%064x',2100))>0,
    'R235 late schedule is lexicographically after 2100 terminal records');
RecordStore::put('asset',$assetId,[
    'actor_id'=>11,'status'=>'ready','policy_hash'=>$policyHash,
    'policy'=>['retention'=>['class'=>'private']],
]);
RecordStore::put('retention',$retentionId,[
    'actor_id'=>0,'asset_id'=>$assetId,
    'status'=>'scheduled','retention_class'=>'private',
    'source_delete_at'=>$now+3000,'derivative_delete_at'=>$now+3000,
    'temporary_cleanup_at'=>$now+3000,'backup_expiry_at'=>$now+6000,
]);
$first=RetentionService::run($now);
$c=RecordStore::get('cron_cursor','retention-reconciliation');
ok($first['records_failed']===0&&($c['last_id']??null)===sprintf('%064x',1000)
    &&($c['last_scanned']??null)===1000,
    'R235 first retention sweep is bounded at 1000 and persists exact cursor');
$second=RetentionService::run($now);
$c=RecordStore::get('cron_cursor','retention-reconciliation');
ok($second['records_failed']===0&&($c['last_id']??null)===sprintf('%064x',2000),
    'R235 second sweep progresses across a further 1000 terminal schedules');
$third=RetentionService::run($now);
$c=RecordStore::get('cron_cursor','retention-reconciliation');
$live=RecordStore::get('retention',$retentionId);
ok($third['records_failed']===0&&($live['temporary_cleaned']??false)===true
    &&($c['last_id']??null)===''&&($c['last_scanned']??null)===101,
    'R235 third sweep reaches late live schedule and wraps at inventory end');

$c['last_id']="invalid\ncursor";
RecordStore::put('cron_cursor','retention-reconciliation',$c,(int)$c['version']);
err(fn()=>RetentionService::run($now),'retention_cursor_invalid',
    'R235 corrupted persistent retention cursor is rejected without fallback');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-lifecycle.php');
ok(!str_contains($source,"RecordStore::all('retention',0,null,100000)")
    &&str_contains($source,"RecordStore::scan('retention',1000"),
    'R235 retention no longer materializes a globally capped 100k schedule list');
echo "REVIEW ROUND 235 RETENTION BACKLOG KEYSET: PASS\n";
