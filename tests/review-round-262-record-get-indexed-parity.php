<?php
declare(strict_types=1);
/** Exercises the WordPress SQL branch, not SCM_TEST_MODE. */
require __DIR__.'/review-round-248-indexed-db-scan-parity.php';
use Sabri\CentralMedia\{Error,RecordStore};

function r262deny(string $why,string $code): void {
    try{RecordStore::get('asset','asset-0001');}
    catch(Error $e){r248($e->errorCode===$code,$why.' ['.$e->errorCode.']');return;}
    r248(false,$why.' was not rejected');
}
$wpdb->scanRows=[];
r248(RecordStore::get('asset','asset-0001')===null,
    'R262 verified empty indexed SQL lookup returns genuinely absent');
$wpdb->scanRows=[r248row('asset-0001')];
$record=RecordStore::get('asset','asset-0001');
r248(($record['id']??null)==='asset-0001'&&($record['version']??null)===2,
    'R262 indexed primary ID, actor, status, version and payload agree');
$wpdb->scanRows=[r248row('asset-0002','asset-0001')];
r262deny('R262 wrong SQL primary key cannot impersonate requested payload','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001','asset-0002')];
r262deny('R262 wrong payload ID rejected','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];$wpdb->scanRows[0]['actor_id']=42;
r262deny('R262 indexed actor and payload actor must agree','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];$wpdb->scanRows[0]['status']='deleted';
r262deny('R262 indexed status and payload status must agree','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];$wpdb->scanRows[0]['version']=9;
r262deny('R262 indexed version and payload version must agree','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];$wpdb->scanRows[0]['payload']='{broken';
r262deny('R262 malformed JSON is rejected','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];$wpdb->scanRows[0]['payload']=null;
r262deny('R262 null payload from present SQL row cannot masquerade as missing','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001'),r248row('asset-0001')];
r262deny('R262 impossible two-row exact-key query denied','record_payload_invalid');
$wpdb->scanRows=null;
r262deny('R262 null indexed query response fails closed','persistence_read_failed');
echo "REVIEW ROUND 262 SQL RECORD GET INDEX PARITY: PASS\n";
