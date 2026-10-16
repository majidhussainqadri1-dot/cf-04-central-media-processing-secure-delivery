<?php
declare(strict_types=1);
/** Reuse the isolated real SQL-mode fake schema/DB, never production WordPress. */
require __DIR__.'/review-round-248-indexed-db-scan-parity.php';
use Sabri\CentralMedia\{Error,RecordStore};

function r249row(string $sqlId,?string $payloadId=null): array {
    $payload=['record_type'=>'upload_part','id'=>$payloadId??$sqlId,
        'actor_id'=>11,'status'=>'uploaded','version'=>2,'updated_at'=>123];
    return ['id'=>$sqlId,'actor_id'=>11,'status'=>'uploaded','version'=>2,
        'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)];
}
function r249reject(string $label,string $code): void {
    try{RecordStore::getMany('upload_part',['part-001','part-002']);}
    catch(Error $e){r248($e->errorCode===$code,$label.' ['.$e->errorCode.']');return;}
    r248(false,$label.' unexpectedly accepted');
}
$wpdb->scanRows=[r249row('part-001'),r249row('part-002')];
$parts=RecordStore::getMany('upload_part',['part-001','part-002']);
r248(array_keys($parts)===['part-001','part-002'],
    'R249 exact SQL primary keys return the requested multipart parts');
$wpdb->scanRows=[r249row('part-001','part-002')];
r249reject('R249 SQL part A cannot masquerade as payload part B','record_payload_invalid');
$wpdb->scanRows=[r249row('part-003')];
r249reject('R249 unsolicited SQL row cannot alias an unrequested part','record_payload_invalid');
$wpdb->scanRows=[r249row('part-001'),r249row('part-001')];
r249reject('R249 duplicate indexed part refused','record_payload_invalid');
$wpdb->scanRows=[r249row('part-001')];$wpdb->scanRows[0]['actor_id']=77;
r249reject('R249 forged multipart actor is rejected','record_payload_invalid');
$wpdb->scanRows=[r249row('part-001')];$wpdb->scanRows[0]['status']='pending';
r249reject('R249 inconsistent indexed part status is rejected','record_payload_invalid');
$wpdb->scanRows=[r249row('part-001')];$wpdb->scanRows[0]['version']=8;
r249reject('R249 inconsistent indexed part version is rejected','record_payload_invalid');
$wpdb->scanRows=null;
r249reject('R249 null DB batch result must fail closed','persistence_read_failed');
echo "REVIEW ROUND 249 INDEXED DB BATCH PARITY: PASS\n";
