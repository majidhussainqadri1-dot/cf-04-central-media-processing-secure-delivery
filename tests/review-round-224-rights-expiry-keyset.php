<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,RightsRevocationService,Utils};

RecordStore::resetMemory();
$now=Utils::now();
// Most assets are terminal, but late IDs still require prompt expiry processing.
// A 1-million-row full inventory could never guarantee fair progress at scale.
for($i=0;$i<2100;$i++){
    $id=sprintf('r224-%05d',$i);
    $row=['actor_id'=>11,'asset_id'=>$id,'status'=>'deleted'];
    if($i===2099){
        $rights=rights(['view']);$rights['expires_at']=$now-30;
        $row=[
            'actor_id'=>11,'asset_id'=>$id,'status'=>'ready',
            'owner_domain'=>'file17','owner_object'=>'r224-native-owner',
            'object_version'=>1,'policy_hash'=>hash('sha256','r224-policy'),
            'rights'=>$rights,'storage'=>['provider_id'=>'source-private'],
            'object_key'=>hash('sha256','r224-binary')
        ];
    }
    RecordStore::put('asset',$id,$row);
}
$first=RightsRevocationService::reconcileExpired($now,500);
$cursor=RecordStore::get('cron_cursor','rights-reconciliation');
ok($first['scanned']===2000&&$first['checked']===0
    &&($cursor['last_id']??null)==='r224-01999',
    'R224 first rights sweep scans 2000 terminal assets and persists progress');
$second=RightsRevocationService::reconcileExpired($now,500);
$last=RecordStore::get('asset','r224-02099');
$cursor=RecordStore::get('cron_cursor','rights-reconciliation');
ok($second['scanned']===100&&$second['revoked']===1
    &&isset($last['rights_reconciled_hash']),
    'R224 later expired rights are reconciled after a page boundary');
ok(($cursor['last_id']??null)===''
    &&is_string($cursor['last_key']??null),
    'R224 valid cursor wraps once full inventory was reached');

$cursor['last_id']="r224-bad\n";
RecordStore::put('cron_cursor','rights-reconciliation',$cursor,(int)$cursor['version']);
err(fn()=>RightsRevocationService::reconcileExpired($now,500),'rights_cursor_invalid',
    'R224 noncanonical persistent ID cursor fails closed');

RecordStore::resetMemory();
$rights=rights(['view']);$rights['expires_at']=$now-30;
foreach(['r224-legacy-a','r224-legacy-b'] as $id){
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'asset_id'=>$id,'status'=>'ready',
        'owner_domain'=>'file17','owner_object'=>$id.'-owner',
        'object_version'=>1,'policy_hash'=>hash('sha256',$id.'-policy'),
        'rights'=>$rights,'storage'=>['provider_id'=>'source-private'],
        'object_key'=>hash('sha256',$id.'-missing')
    ]);
}
RecordStore::put('cron_cursor','rights-reconciliation',[
    'actor_id'=>0,'status'=>'active',
    'last_key'=>sprintf('%019d:%s',$now-30,'r224-legacy-a'),
]);
$legacy=RightsRevocationService::reconcileExpired($now,500);
ok($legacy['revoked']===1
    &&isset(RecordStore::get('asset','r224-legacy-b')['rights_reconciled_hash']),
    'R224 legacy expiry-key-only cursor advances from prior asset ID without schema migration');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-plan-parity.php');
ok(!str_contains($source,"RecordStore::all('asset',0,null,1000000)")
    &&str_contains($source,"RecordStore::scan('asset'"),
    'R224 rights expiry no longer depends on a million-row full inventory scan');
echo "REVIEW ROUND 224 RIGHTS EXPIRY KEYSET: PASS\n";
