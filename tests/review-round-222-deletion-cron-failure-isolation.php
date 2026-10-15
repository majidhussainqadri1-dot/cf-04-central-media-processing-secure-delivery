<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{Plugin,RecordStore,Utils};

RecordStore::resetMemory();
// Reproduce a corrupted revocation cursor: dispatch must raise an error, but a
// pending CDN purge belongs to a separate reconciliation domain and must run.
RecordStore::put('cron_cursor','revocation-dispatch',[
    'actor_id'=>0,'status'=>'active','last_id'=>'not-a-sha256',
    'updated_at'=>Utils::now()
]);
$id=hash('sha256','r222-pending-cdn-map');
RecordStore::put('cdn_mapping',$id,[
    'actor_id'=>0,'asset_id'=>'r222-public-asset',
    'status'=>'purge_pending','version_key'=>'r222-public-cdn-version',
    'purge_reason'=>'rights-revoked','privacy_class'=>'C0'
]);
err(fn()=>Plugin::cronDeletions(),'revocation_cursor_invalid',
    'R222 upstream revocation cursor error remains visible');
ok((RecordStore::get('cdn_mapping',$id)['status']??null)==='purged',
    'R222 independent CDN revocation purge succeeds despite broken dispatch cursor');
ok((RecordStore::get('runtime_lock','deletions')['status']??null)==='released',
    'R222 deletion cron releases runtime lock after upstream failure');

$broken=RecordStore::get('cron_cursor','revocation-dispatch');
RecordStore::delete('cron_cursor','revocation-dispatch',(int)$broken['version']);
Plugin::cronDeletions();
ok((RecordStore::get('cdn_mapping',$id)['status']??null)==='purged',
    'R222 repaired dispatch cursor permits safe full cron retry');
echo "REVIEW ROUND 222 DELETION CRON FAILURE ISOLATION: PASS\n";
