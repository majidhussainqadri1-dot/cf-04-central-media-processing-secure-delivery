<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{Plugin,RecordStore,Utils};

// R220 processing and integrity must reach later keys without repeating
// offset-based enumeration from the first million records every run.
RecordStore::resetMemory();
for($i=0;$i<2100;$i++){
    RecordStore::put('asset',sprintf('r220-%05d',$i),[
        'actor_id'=>11,'status'=>$i===2099?'ready':'quarantined',
        'processing_status'=>$i===2099?'queued':'idle',
        'owner_domain'=>'file17','object_version'=>1
    ]);
}
Plugin::cronJobs();
$one=RecordStore::get('cron_cursor','processing');
ok(($one['last_id']??null)==='r220-01999'
    &&($one['last_scanned']??null)===2000,
    'R220 processing cron checks first 2000 records and persists an ID cursor');
Plugin::cronJobs();
$two=RecordStore::get('cron_cursor','processing');
ok(($two['last_id']??null)===''&&($two['last_scanned']??null)===100
    &&($two['last_selected']??null)===1,
    'R220 processing cron reaches last queued asset after first 2000 unrelated assets');
ok(($two['version']??0)>($one['version']??0),
    'R220 recurring processing cron advances the same durable cursor');

Plugin::cronIntegrity();
$icOne=RecordStore::get('cron_cursor','integrity');
ok(($icOne['last_id']??null)==='r220-01999'
    &&($icOne['last_scanned']??null)===2000,
    'R220 integrity cron persists an immutable-ID cursor through 2000 unready assets');
Plugin::cronIntegrity();
$icTwo=RecordStore::get('cron_cursor','integrity');
ok(($icTwo['last_id']??null)===''
    &&($icTwo['last_scanned']??null)===100
    &&($icTwo['last_selected']??null)===1,
    'R220 integrity cron reaches a ready asset beyond first 2000 inventory records');

$icTwo['last_id']="r220-1000\n";
RecordStore::put('cron_cursor','integrity',$icTwo,(int)$icTwo['version']);
err(fn()=>Plugin::cronIntegrity(),'maintenance_cursor_invalid',
    'R220 corrupt persisted integrity cursor is rejected without silent aliasing');
ok((RecordStore::get('runtime_lock','integrity')['status']??null)==='released',
    'R220 maintenance lock is released on cursor validation failure');

RecordStore::resetMemory();
RecordStore::put('cron_cursor','integrity',[
    'actor_id'=>0,'status'=>'active','name'=>'integrity','offset'=>500
]);
RecordStore::put('asset','r220-legacy',['actor_id'=>11,'status'=>'quarantined','processing_status'=>'idle']);
Plugin::cronIntegrity();
$legacy=RecordStore::get('cron_cursor','integrity');
ok(array_key_exists('last_id',$legacy)&&($legacy['last_id']??null)==='',
    'R220 legacy offset-only integrity evidence safely transitions to ID cursor');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-plugin.php');
ok(!str_contains($source,"RecordStore::list('asset'")
    &&str_contains($source,"RecordStore::scan('asset'")
    &&str_contains($source,"saveMaintenanceCursor('processing'")
    &&str_contains($source,"saveMaintenanceCursor('integrity'"),
    'R220 both background crons abandon unstable offset-based scanning');
echo "REVIEW ROUND 220 CRON KEYSET FAIRNESS: PASS\n";
