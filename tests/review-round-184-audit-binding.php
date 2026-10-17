<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,RecordStore,ResidencyCryptoService};
$p=['approved_algorithms'=>['aes-256-gcm','future-approved'],
    'minimum_key_bits'=>256,'migration_window_seconds'=>86400];
RecordStore::resetMemory();
$first=ResidencyCryptoService::cryptoAgility(11,$p);
$next=$p;$next['minimum_key_bits']=512;
Audit::failNextForTest('future40_crypto_agility_policy_set');
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$next),'audit_write_failed','Second applied audit failure');
$pending=RecordStore::get('crypto_agility','global');
ok($pending['version']===2,'Changed policy is durable after applied audit outage');
$healed=ResidencyCryptoService::cryptoAgility(11,$next);
ok($healed['version']===2&&Audit::verifyChain(),'Second version heals without duplicate mutation');
$third=$next;$third['minimum_key_bits']=1024;
Audit::failNextForTest('future40_crypto_agility_policy_intent');
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$third),'audit_write_failed','Intent failure on subsequent version');
ok(RecordStore::get('crypto_agility','global')['version']===2,'Failed intent preserves previous version');
$stored=RecordStore::get('crypto_agility','global');
$stored['audit_key']=str_repeat('0',64);
RecordStore::put('crypto_agility','global',$stored,(int)$stored['version']);
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$next),
    'crypto_policy_record_invalid','Tampered audit binding fails closed');
RecordStore::resetMemory();
$legacy=RecordStore::put('crypto_agility','global',[
    'actor_id'=>11,'status'=>'active','approved_algorithms'=>$p['approved_algorithms'],
    'minimum_key_bits'=>256,'migration_window_seconds'=>86400,
    'required_test_vector'=>true,'created_at'=>time()]);
$unchanged=ResidencyCryptoService::cryptoAgility(11,$p);
ok($unchanged['version']===$legacy['version'],'Legacy policy stays readable without rewriting');
echo "REVIEW ROUND 184 AUDIT BINDING: PASS\n";
