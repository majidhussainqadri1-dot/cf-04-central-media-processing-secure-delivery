<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{Plugin,RecordStore,Utils};

RecordStore::resetMemory();
$now=Utils::now();
$id='r209-orphan';
RecordStore::put('upload',$id,[
    'actor_id'=>11,'owner_domain'=>'file17','status'=>'uploading',
    'expires_at'=>$now-200,'credential_hash'=>'r209-credential'
]);
// Invalid existing rights-reconciliation cursor fails closed before the old
// monolithic retention cron ever reached expired-upload cleanup.
RecordStore::put('cron_cursor','rights-reconciliation',[
    'actor_id'=>0,'status'=>'active','last_key'=>'not-a-valid-cursor'
]);
err(fn()=>Plugin::cronRetention(),'rights_cursor_invalid',
    'R209 broken rights reconciliation still raises its explicit failure');
ok((RecordStore::get('upload',$id)['status']??null)==='expired',
    'R209 expired upload cleanup proceeds despite independent rights cursor failure');
$lock=RecordStore::get('runtime_lock','retention');
ok(($lock['status']??null)==='released',
    'R209 retention lock releases even when one maintenance operation fails');

// Repair the corrupt rights-cursor evidence as an explicit separate action.
$broken=RecordStore::get('cron_cursor','rights-reconciliation');
RecordStore::delete('cron_cursor','rights-reconciliation',(int)$broken['version']);
Plugin::cronRetention();
ok((RecordStore::get('upload',$id)['status']??null)==='expired',
    'R209 maintenance can safely retry after upstream rights-cursor correction');
echo "REVIEW ROUND 209 RETENTION FAILURE ISOLATION: PASS\n";
