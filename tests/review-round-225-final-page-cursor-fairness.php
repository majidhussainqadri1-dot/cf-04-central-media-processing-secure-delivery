<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{Plugin,RecordStore};

RecordStore::resetMemory();
// A work quota can be exhausted halfway through the inventory's final page.
// Its unread tail must not disappear when the cursor is persisted.
for($i=1;$i<=8;$i++){
    $id=sprintf('r225-jobs-%02d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'quarantined','processing_status'=>'queued',
        'owner_domain'=>'file17','object_version'=>1,
        // Fail processing safely on an explicit missing job rather than
        // letting an intentionally minimal asset emit PHP undefined-key warnings.
        'job_graph'=>['probe'=>'r225-expected-missing-job']
    ]);
}
Plugin::cronJobs();
$first=RecordStore::get('cron_cursor','processing');
ok(($first['last_id']??null)==='r225-jobs-05'
    &&($first['last_selected']??null)===5,
    'R225 processing cursor preserves fifth queued asset when quota ends mid-final-page');
Plugin::cronJobs();
$second=RecordStore::get('cron_cursor','processing');
ok(($second['last_selected']??null)===3&&($second['last_id']??null)==='',
    'R225 next processing run reaches three remaining queued assets before wrapping');

RecordStore::resetMemory();
for($i=1;$i<=24;$i++){
    $id=sprintf('r225-integrity-%02d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','processing_status'=>'ready',
        'owner_domain'=>'file17','object_version'=>1
    ]);
}
Plugin::cronIntegrity();
$first=RecordStore::get('cron_cursor','integrity');
ok(($first['last_id']??null)==='r225-integrity-20'
    &&($first['last_selected']??null)===20,
    'R225 integrity cursor preserves twentieth ready asset when quota ends mid-final-page');
Plugin::cronIntegrity();
$second=RecordStore::get('cron_cursor','integrity');
ok(($second['last_selected']??null)===4&&($second['last_id']??null)==='',
    'R225 next integrity run samples final four assets and then wraps safely');
echo "REVIEW ROUND 225 FINAL-PAGE CURSOR FAIRNESS: PASS\n";
