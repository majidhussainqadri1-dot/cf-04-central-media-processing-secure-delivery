<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,ResidencyCryptoService};
RecordStore::resetMemory();
$valid=['approved_algorithms'=>['aes-256-gcm','future-approved'],
    'minimum_key_bits'=>256,'migration_window_seconds'=>86400];
foreach([null,'aes-256-gcm',[],['future-approved'],
    ['aes-256-gcm','aes-256-gcm'],array_fill(0,17,'aes-256-gcm'),
    ['aes-256-gcm',7],['aes-256-gcm','AES-256-GCM'],
    ['aes-256-gcm','bad space']] as $bad){
    $input=$valid;$input['approved_algorithms']=$bad;
    err(fn()=>ResidencyCryptoService::cryptoAgility(11,$input),
        $bad===null?'crypto_policy_incomplete':'crypto_policy_invalid',
        'Round 183 rejects invalid algorithm list');
}
foreach(['256junk','1e3',true,[],null,128,8193,INF,NAN] as $bad){
    $input=$valid;$input['minimum_key_bits']=$bad;
    err(fn()=>ResidencyCryptoService::cryptoAgility(11,$input),
        $bad===null?'crypto_policy_incomplete':'crypto_policy_invalid',
        'Round 183 rejects noncanonical or weak key length');
}
foreach(['3600junk','1e5',true,[],null,3599,31536001,INF,NAN] as $bad){
    $input=$valid;$input['migration_window_seconds']=$bad;
    err(fn()=>ResidencyCryptoService::cryptoAgility(11,$input),
        $bad===null?'crypto_policy_incomplete':'crypto_policy_invalid',
        'Round 183 rejects noncanonical or unbounded migration window');
}
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$valid+['notes'=>'secret']),
    'crypto_policy_invalid','Round 183 unapproved policy fields rejected');
$row=ResidencyCryptoService::cryptoAgility(11,$valid);
ok($row['approved_algorithms']===$valid['approved_algorithms']
    &&$row['minimum_key_bits']===256&&$row['migration_window_seconds']===86400,
    'Round 183 canonical policy saved without coercion');
$audits=RecordStore::list('audit',0,null,500);
ok(count(array_filter($audits,fn($r)=>($r['event_key']??null)==='future40_crypto_agility_policy_set'))===1,
    'Round 183 security-policy change emits audit evidence');
$stored=RecordStore::get('crypto_agility','global');
$stored['minimum_key_bits']='256junk';
RecordStore::put('crypto_agility','global',$stored,(int)$stored['version']);
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$valid),
    'crypto_policy_record_invalid','Round 183 corrupt stored policy cannot be overwritten');
$stored=RecordStore::get('crypto_agility','global');
$stored['minimum_key_bits']=256;
$stored['approved_algorithms']=['future-approved'];
RecordStore::put('crypto_agility','global',$stored,(int)$stored['version']);
err(fn()=>ResidencyCryptoService::cryptoAgility(11,$valid),
    'crypto_policy_record_invalid','Round 183 stored policy must retain current cipher');
echo "REVIEW ROUND 183 CRYPTO AGILITY POLICY: PASS\n";
