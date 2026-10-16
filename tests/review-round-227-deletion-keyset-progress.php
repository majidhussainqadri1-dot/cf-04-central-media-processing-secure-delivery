<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeletionService,RecordStore};

RecordStore::resetMemory();
for($i=0;$i<2100;$i++){
    $id=sprintf('r227-%05d',$i);
    RecordStore::put('deletion',$id,[
        'actor_id'=>11,'deletion_id'=>$id,'asset_id'=>'r227-asset',
        'reason'=>'user-request','status'=>'cancelled'
    ]);
}
$first=DeletionService::reconcile();
$cursor=RecordStore::get('cron_cursor','deletion-reconciliation');
ok($first['pending']===1000&&($cursor['last_id']??null)==='r227-00999',
    'R227 deletion reconciler advances first 1000 cancelled records by key');
$second=DeletionService::reconcile();
$cursor=RecordStore::get('cron_cursor','deletion-reconciliation');
ok($second['pending']===1000&&($cursor['last_id']??null)==='r227-01999',
    'R227 deletion reconciler reaches second 1000 without restarting head');
$third=DeletionService::reconcile();
$cursor=RecordStore::get('cron_cursor','deletion-reconciliation');
ok($third['pending']===100&&($cursor['last_id']??null)==='',
    'R227 deletion reconciler reaches final 100 and wraps');
$cursor['last_id']="r227-bad\n";
RecordStore::put('cron_cursor','deletion-reconciliation',$cursor,(int)$cursor['version']);
err(fn()=>DeletionService::reconcile(),'deletion_cursor_invalid',
    'R227 corrupt persistent deletion cursor fails closed');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-lifecycle.php');
ok(!str_contains($source,"RecordStore::all('deletion',0,null,100000)")
    &&str_contains($source,"RecordStore::scan('deletion',1000"),
    'R227 reconciler no longer requires the 100k full-inventory scan');
echo "REVIEW ROUND 227 DELETION KEYSET: PASS\n";
