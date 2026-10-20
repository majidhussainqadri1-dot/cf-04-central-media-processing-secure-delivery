<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,RecordStore,ResidencyCryptoService};
function countR184(string $event): int {
    return count(array_filter(RecordStore::all('audit',0,null,100000),
        fn($a)=>($a['event_key']??null)===$event));
}
$p=['approved_algorithms'=>['aes-256-gcm','future-approved'],
    'minimum_key_bits'=>256,'migration_window_seconds'=>86400];
RecordStore::resetMemory();
Audit::failNextForTest('future40_crypto_agility_policy_intent');
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$p),'audit_write_failed','Intent fails before mutation');
ok(RecordStore::get('crypto_agility','global')===null,'Failed intent does not persist policy');
Audit::failNextForTest('future40_crypto_agility_policy_set');
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$p),'audit_write_failed','Applied audit failure');
$stored=RecordStore::get('crypto_agility','global');
ok($stored['version']===1&&isset($stored['audit_key'])
    &&countR184('future40_crypto_agility_policy_intent')===1
    &&countR184('future40_crypto_agility_policy_set')===0,'Pending applied audit is recoverable');
$fixed=ResidencyCryptoService::cryptoAgility(11,$p);
$again=ResidencyCryptoService::cryptoAgility(11,$p);
ok($fixed['version']===1&&$again['version']===1
    &&countR184('future40_crypto_agility_policy_set')===1
    &&Audit::verifyChain(),'Retry heals applied audit exactly once without version bump');
echo "REVIEW ROUND 184 AUDIT RECOVERY: PASS\n";
