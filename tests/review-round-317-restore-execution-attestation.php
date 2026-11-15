<?php
declare(strict_types=1);
// Force the independent production-like branch in this isolated fixture.
define('SCM_TEST_REQUIRE_SIGNED_RESTORE',true);
define('SCM_RESTORE_ATTESTATION_KEY',str_repeat('r',64));
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreExecutionAttestation,Utils};
$key=str_repeat('r',64);
$restore=[
    'restore_id'=>'rst-r317-case',
    'inventory_hash'=>hash('sha256','r317-inventory'),
    'source_fingerprint'=>hash('sha256','r317-source-inventory')
];
$now=Utils::now();
$proof=[
    'restore_id'=>$restore['restore_id'],
    'inventory_hash'=>$restore['inventory_hash'],
    'source_fingerprint'=>$restore['source_fingerprint'],
    'database_snapshot_sha256'=>hash('sha256','r317-db-snapshot'),
    'object_snapshot_sha256'=>hash('sha256','r317-object-backup'),
    'key_snapshot_sha256'=>hash('sha256','r317-separate-key-backup'),
    'issued_at'=>$now,'expires_at'=>$now+300,'restored_at'=>$now-30,
    'executed'=>true,'rights_reapplied'=>true,
    'tombstones_verified'=>true,'owner_reconciliation'=>'complete'
];
function r317sign(array $proof,string $key):array {
    unset($proof['signature']);
    $proof['signature']=hash_hmac('sha256',Utils::canonicalJson($proof),$key);
    return $proof;
}
$good=r317sign($proof,$key);
err(fn()=>RestoreExecutionAttestation::requireForServe($restore),
    'restore_attestation_incomplete',
    'R318 signed-proof production-like guard denies when external executor hook is absent');
add_filter('scm_restore_execution_proof',
    static function($value,array $request)use($good):array {
        if(($request['restore_id']??null)!==$good['restore_id'])
            throw new RuntimeException('wrong_restore_context');
        return $good;
    });
RestoreExecutionAttestation::requireForServe($restore);
$ok=RestoreExecutionAttestation::validate($restore,$good,$key);
ok($ok['signature_verified']===true&&
   $ok['status']==='independently_attested_source_only',
   'R317 complete recent signed restore execution descriptor validates in isolation');
err(fn()=>RestoreExecutionAttestation::validate($restore,$good,'short'),
    'restore_attestation_key_unavailable',
    'R317 missing sufficiently strong independent attestation key fails closed');
$bad=$good;$bad['signature']=hash('sha256','forged');
err(fn()=>RestoreExecutionAttestation::validate($restore,$bad,$key),
    'restore_attestation_signature_invalid',
    'R317 forged restore execution signature rejected');
$bad=$good;$bad['source_fingerprint']=hash('sha256','other-run');
err(fn()=>RestoreExecutionAttestation::validate($restore,r317sign($bad,$key),$key),
    'restore_attestation_mismatch',
    'R317 even correctly signed evidence for another source inventory is rejected');
$bad=$good;$bad['restore_id']='rst-other-run';
err(fn()=>RestoreExecutionAttestation::validate($restore,r317sign($bad,$key),$key),
    'restore_attestation_mismatch',
    'R317 old/different restore ID cannot unlock a new run');
$bad=$good;$bad['expires_at']=$now-1;
err(fn()=>RestoreExecutionAttestation::validate($restore,r317sign($bad,$key),$key),
    'restore_attestation_expired',
    'R317 expired independently signed proof cannot authorize serving');
$bad=$good;$bad['owner_reconciliation']='pending';
err(fn()=>RestoreExecutionAttestation::validate($restore,r317sign($bad,$key),$key),
    'restore_attestation_incomplete',
    'R317 unverified owner rights block restore success');
$bad=$good;unset($bad['database_snapshot_sha256']);
err(fn()=>RestoreExecutionAttestation::validate($restore,r317sign($bad,$key),$key),
    'restore_attestation_incomplete',
    'R317 missing database snapshot proof cannot be interpreted as complete');
$bad=$good;$bad['key_snapshot_sha256']=str_repeat('0',64);
err(fn()=>RestoreExecutionAttestation::validate($restore,r317sign($bad,$key),$key),
    'restore_attestation_invalid',
    'R317 placeholder key snapshot evidence is not accepted');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
ok(is_string($source)&&str_contains($source,'RestoreExecutionAttestation::requireForServe($restore);'),
    'R317 production gate requires attestation before media serving is authorized');
echo "REVIEW ROUND 317 SIGNED RESTORE EXECUTION ATTESTATION: PASS\n";
