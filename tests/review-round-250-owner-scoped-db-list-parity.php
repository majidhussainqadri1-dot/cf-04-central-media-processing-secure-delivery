<?php
declare(strict_types=1);
/** Exercise production SQL, never the in-memory SCM_TEST_MODE bootstrap. */
require __DIR__.'/review-round-248-indexed-db-scan-parity.php';
use Sabri\CentralMedia\{Error,RecordStore};

function r250deny(string $message,string $errorCode): void {
    try{RecordStore::list('asset',11,'ready',5,0);}
    catch(Error $e){r248($e->errorCode===$errorCode,$message.' ['.$e->errorCode.']');return;}
    r248(false,$message.' was unexpectedly accepted');
}
$wpdb->scanRows=[r248row('r250-asset')];
$list=RecordStore::list('asset',11,'ready',5,0);
r248(count($list)===1&&$list[0]['id']==='r250-asset',
    'R250 valid owner-scoped and status-scoped list returns exact indexed record');
$wpdb->scanRows=[r248row('r250-sql','r250-forged')];
r250deny('R250 forged listed payload identity denied','record_payload_invalid');
$wpdb->scanRows=[r248row('r250-sql')];
$wpdb->scanRows[0]['actor_id']=55;
r250deny('R250 DB actor and payload actor mismatch denied','record_payload_invalid');
$wpdb->scanRows=[r248row('r250-sql')];
$wpdb->scanRows[0]['status']='deleted';
r250deny('R250 DB status and payload status mismatch denied','record_payload_invalid');
$wpdb->scanRows=[r248row('r250-sql')];
$wpdb->scanRows[0]['version']=1;
r250deny('R250 DB version and payload version mismatch denied','record_payload_invalid');
$wpdb->scanRows=[r248row('r250-sql')];
$d=json_decode($wpdb->scanRows[0]['payload'],true,64,JSON_THROW_ON_ERROR);
$d['actor_id']=55;
$wpdb->scanRows[0]['actor_id']=55;
$wpdb->scanRows[0]['payload']=json_encode($d,JSON_THROW_ON_ERROR);
r250deny('R250 off-owner indexed row cannot bypass requested owner filter','record_payload_invalid');
$wpdb->scanRows=[r248row('r250-sql')];
$d=json_decode($wpdb->scanRows[0]['payload'],true,64,JSON_THROW_ON_ERROR);
$d['status']='pending';
$wpdb->scanRows[0]['status']='pending';
$wpdb->scanRows[0]['payload']=json_encode($d,JSON_THROW_ON_ERROR);
r250deny('R250 off-status indexed row cannot bypass requested status filter','record_payload_invalid');
$wpdb->scanRows=null;
r250deny('R250 null DB list result is not an empty list','persistence_read_failed');
echo "REVIEW ROUND 250 OWNER-SCOPED DB LIST PARITY: PASS\n";
