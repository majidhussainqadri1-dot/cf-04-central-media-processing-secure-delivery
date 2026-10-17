<?php
declare(strict_types=1);
/** Real SQL production code path, using fake WordPress database counts. */
require __DIR__.'/review-round-248-indexed-db-scan-parity.php';
use Sabri\CentralMedia\{Error,RecordStore};

function r263deny(string $why,string $code): void {
    try{RecordStore::countStatuses('asset',['ready']);}
    catch(Error $e){r248($e->errorCode===$code,$why.' ['.$e->errorCode.']');return;}
    r248(false,$why.' erroneously returned an authoritative count');
}
foreach(['0'=>0,'1'=>1,'125'=>125,'2147483647'=>2147483647] as $raw=>$expect){
    $wpdb->statusCount=(string)$raw;
    r248(RecordStore::countStatuses('asset',['ready'])===$expect,
        'R263 validated SQL COUNT(*) result '.$raw.' is retained exactly');
}
$wpdb->statusCount='0';
r248(RecordStore::countStatuses('asset',['ready'],true)===0,
    'R263 exclusion status aggregation supports real zero');
foreach([null,'','null','-1','1e3','1.1',' 0 ','00','01','9999999999999999999999999999'] as $result){
    $wpdb->statusCount=$result;
    r263deny('R263 malformed, unavailable or overflowed COUNT result rejected',
        'persistence_read_failed');
}
$wpdb->statusCount='0';$wpdb->last_error='database gone away';
r263deny('R263 explicit MySQL error must not be masked by plausible zero count',
    'persistence_read_failed');
$wpdb->last_error='';
echo "REVIEW ROUND 263 STATUS COUNT FAIL-CLOSED: PASS\n";
