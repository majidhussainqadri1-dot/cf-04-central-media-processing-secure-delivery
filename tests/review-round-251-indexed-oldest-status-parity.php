<?php
declare(strict_types=1);
require __DIR__.'/review-round-248-indexed-db-scan-parity.php';
use Sabri\CentralMedia\{Error,RecordStore};

function r251deny(string $description,string $code): void {
    try{RecordStore::oldestByStatuses('asset',['ready']);}
    catch(Error $e){r248($e->errorCode===$code,$description.' ['.$e->errorCode.']');return;}
    r248(false,$description.' unexpectedly accepted');
}
$wpdb->scanRows=[];
r248(RecordStore::oldestByStatuses('asset',['ready'])===null,
    'R251 empty indexed oldest-status result is genuinely empty');
$wpdb->scanRows=[r248row('r251-first')];
$oldest=RecordStore::oldestByStatuses('asset',['ready']);
r248(($oldest['id']??null)==='r251-first',
    'R251 valid indexed oldest-status record preserves canonical identity');
$wpdb->scanRows=[r248row('r251-first','r251-forged')];
r251deny('R251 untrusted older payload cannot impersonate another record ID',
    'record_payload_invalid');
$wpdb->scanRows=[r248row('r251-first')];$wpdb->scanRows[0]['actor_id']=12;
r251deny('R251 forged indexed actor rejected','record_payload_invalid');
$wpdb->scanRows=[r248row('r251-first')];$wpdb->scanRows[0]['status']='deleted';
r251deny('R251 indexed status different from payload denied','record_payload_invalid');
$wpdb->scanRows=[r248row('r251-first')];$d=json_decode($wpdb->scanRows[0]['payload'],true,64,JSON_THROW_ON_ERROR);
$d['status']='pending';$wpdb->scanRows[0]['status']='pending';
$wpdb->scanRows[0]['payload']=json_encode($d,JSON_THROW_ON_ERROR);
r251deny('R251 off-status record cannot enter oldest-status queue','record_payload_invalid');
$wpdb->scanRows=[r248row('r251-first')];$wpdb->scanRows[0]['version']=5;
r251deny('R251 indexed queue version mismatch rejected','record_payload_invalid');
$wpdb->scanRows=[r248row('r251-first'),r248row('r251-second')];
r251deny('R251 impossible multi-row LIMIT 1 result is never silently accepted','record_payload_invalid');
$wpdb->scanRows=null;
r251deny('R251 null oldest-status DB response fails closed','persistence_read_failed');
echo "REVIEW ROUND 251 INDEXED OLDEST-STATUS PARITY: PASS\n";
