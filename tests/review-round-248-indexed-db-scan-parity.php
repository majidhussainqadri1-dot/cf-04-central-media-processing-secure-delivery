<?php
declare(strict_types=1);
/** Real persistence-branch fixture: must not import tests/bootstrap.php (SCM_TEST_MODE). */
require dirname(__DIR__).'/sabri-central-media/includes/class-scm-core.php';
require dirname(__DIR__).'/sabri-central-media/includes/class-scm-persistence.php';

use Sabri\CentralMedia\{Error,RecordStore,Schema};

final class R248FakeWpdb {
    public string $prefix='wp_';
    public string $last_error='';
    public ?array $scanRows=[];
    public ?string $statusCount=null;
    public function prepare(string $sql,mixed ...$args): string { return str_contains($sql,'SHOW TABLES LIKE')?$sql.' '.(string)($args[0]??''):$sql; }
    public function get_var(string $sql): ?string {
        if(str_contains($sql,'SELECT COUNT(*)'))return $this->statusCount;
        if(str_contains($sql,'SHOW TABLES'))return
            str_contains($sql,'scm_audit')?'wp_scm_audit':'wp_scm_records';
        return null;
    }
    public function get_col(string $sql): array {
        if(str_contains($sql,'SHOW COLUMNS FROM wp_scm_records'))
            return ['record_type','id','actor_id','status','version','expires_at','payload','updated_at'];
        if(str_contains($sql,'SHOW COLUMNS FROM wp_scm_audit'))
            return ['id','event_id','event_key','actor_id','previous_hash','event_hash','payload','created_at'];
        return [];
    }
    public function get_results(string $sql,mixed $mode=null): ?array {
        if(str_contains($sql,'SHOW INDEX FROM wp_scm_records'))return [
            ['Key_name'=>'PRIMARY','Column_name'=>'record_type','Seq_in_index'=>1,'Non_unique'=>0],
            ['Key_name'=>'PRIMARY','Column_name'=>'id','Seq_in_index'=>2,'Non_unique'=>0]
        ];
        if(str_contains($sql,'SHOW INDEX FROM wp_scm_audit'))return [
            ['Key_name'=>'PRIMARY','Column_name'=>'id','Seq_in_index'=>1,'Non_unique'=>0],
            ['Key_name'=>'event_id','Column_name'=>'event_id','Seq_in_index'=>1,'Non_unique'=>0]
        ];
        if(str_contains($sql,'SELECT id,actor_id,status,version,payload FROM'))
            return $this->scanRows;
        return [];
    }
    public function insert(...$args): int { return 1; }
    public function update(...$args): int { return 1; }
    public function delete(...$args): int { return 1; }
}
$GLOBALS['wpdb']=new R248FakeWpdb();
$wpdb=$GLOBALS['wpdb'];

function r248(bool $ok,string $message): void {
    if(!$ok){fwrite(STDERR,"R248 FAIL: $message\n");exit(1);}
    echo "R248 PASS: $message\n";
}
function r248row(string $indexedId,?string $payloadId=null): array {
    $data=['record_type'=>'asset','id'=>$payloadId??$indexedId,
        'actor_id'=>11,'status'=>'ready','version'=>2,'updated_at'=>123];
    return ['id'=>$indexedId,'actor_id'=>11,'status'=>'ready','version'=>2,
        'payload'=>json_encode($data,JSON_THROW_ON_ERROR)];
}
function r248reject(string $label,string $expectedCode): void {
    try{RecordStore::scan('asset',5);}catch(Error $e){
        r248($e->errorCode===$expectedCode,$label.' ['.$e->errorCode.']');return;
    }
    r248(false,$label.' unexpectedly accepted');
}
r248(!defined('SCM_TEST_MODE')&&Schema::ready(),
    'R248 production branch uses indexed SQL fixture, not in-memory test mode');
$wpdb->scanRows=[r248row('asset-0001'),r248row('asset-0002')];
$page=RecordStore::scan('asset',5);
r248(array_column($page,'id')===['asset-0001','asset-0002'],
    'R248 canonical SQL identity and order returns valid records');

$wpdb->scanRows=[r248row('asset-0001','asset-9999')];
r248reject('R248 forged payload ID not accepted as next keyset cursor','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0002'),r248row('asset-0001')];
r248reject('R248 SQL keyset ID inversion denied','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001'),r248row('asset-0001')];
r248reject('R248 duplicate SQL keyset ID denied','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];
$wpdb->scanRows[0]['status']='deleted';
r248reject('R248 indexed status and payload status must agree','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];
$wpdb->scanRows[0]['actor_id']=42;
r248reject('R248 indexed actor and payload actor must agree','record_payload_invalid');
$wpdb->scanRows=[r248row('asset-0001')];
$wpdb->scanRows[0]['version']=1;
r248reject('R248 indexed version and payload version must agree','record_payload_invalid');
$wpdb->scanRows=null;
r248reject('R248 null query result is not an empty authoritative inventory','persistence_read_failed');
echo "REVIEW ROUND 248 INDEXED DB SCAN PARITY: PASS\n";
