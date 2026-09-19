<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class Db {
    public static function table(string $name): string { global $wpdb; $prefix=(isset($wpdb)&&is_object($wpdb)&&isset($wpdb->prefix))?(string)$wpdb->prefix:'wp_'; return $prefix.'scm_'.Utils::key($name,32); }
    public static function available(): bool { global $wpdb; return isset($wpdb)&&is_object($wpdb)&&method_exists($wpdb,'prepare')&&method_exists($wpdb,'get_var')&&method_exists($wpdb,'insert')&&method_exists($wpdb,'update')&&method_exists($wpdb,'delete')&&method_exists($wpdb,'get_col')&&method_exists($wpdb,'get_results'); }
    public static function assertRead(string $operation): void {global $wpdb;if(isset($wpdb)&&is_object($wpdb)&&property_exists($wpdb,'last_error')&&trim((string)$wpdb->last_error)!=='')throw new Error('persistence_read_failed','Persistent database read failed closed.',503,['operation'=>Utils::key($operation,64)]);}
}

final class Schema {
    public static function install(): void {
        global $wpdb;if(!isset($wpdb))return;
        if(!function_exists('dbDelta')&&defined('ABSPATH')){$f=ABSPATH.'wp-admin/includes/upgrade.php';if(is_file($f))require_once $f;}
        if(!function_exists('dbDelta'))return;
        $c=$wpdb->get_charset_collate();
        $tables=[
            'records'=>'record_type varchar(48) NOT NULL,id varchar(96) NOT NULL,actor_id bigint unsigned NOT NULL DEFAULT 0,status varchar(40) NOT NULL,version bigint unsigned NOT NULL DEFAULT 1,expires_at datetime NULL,payload longtext NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(record_type,id),KEY type_actor_status(record_type,actor_id,status),KEY expiry(expires_at)',
            'audit'=>'id bigint unsigned NOT NULL AUTO_INCREMENT,event_id varchar(96) NOT NULL,event_key varchar(96) NOT NULL,actor_id bigint unsigned NOT NULL DEFAULT 0,previous_hash char(64) NOT NULL,event_hash char(64) NOT NULL,payload longtext NOT NULL,created_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY event_id(event_id),KEY event_time(event_key,created_at)',
        ];
        foreach($tables as $n=>$body)dbDelta('CREATE TABLE '.Db::table($n).' ('.$body.') '.$c.';');
        if(function_exists('update_option'))update_option('scm_schema_version',defined('SCM_SCHEMA_VERSION')?SCM_SCHEMA_VERSION:'1.5.0',false);
    }
    public static function ready(): bool { if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true)return true; if(!Db::available())return false; if(function_exists('get_option')&&(string)get_option('scm_schema_version','')!==(defined('SCM_SCHEMA_VERSION')?SCM_SCHEMA_VERSION:'1.5.0'))return false; global $wpdb;$required=['records'=>['record_type','id','actor_id','status','version','expires_at','payload','updated_at'],'audit'=>['id','event_id','event_key','actor_id','previous_hash','event_hash','payload','created_at']];foreach(['records','audit'] as $t){$name=Db::table($t);$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name));if((string)$found!==$name)return false;$columns=array_map('strval',(array)$wpdb->get_col('SHOW COLUMNS FROM '.$name,0));foreach($required[$t] as $column)if(!in_array($column,$columns,true))return false;$indexRows=$wpdb->get_results('SHOW INDEX FROM '.$name,defined('ARRAY_A')?ARRAY_A:'ARRAY_A');if(!is_array($indexRows))return false;$indexes=[];foreach($indexRows as $raw){$row=is_array($raw)?$raw:(array)$raw;$key=(string)($row['Key_name']??'');$column=(string)($row['Column_name']??'');$seq=(int)($row['Seq_in_index']??0);if($key!==''&&$column!==''&&$seq>0){$indexes[$key]['columns'][$seq]=$column;$indexes[$key]['non_unique']=(int)($row['Non_unique']??1);}}foreach($indexes as &$index)if(isset($index['columns']))ksort($index['columns']);unset($index);if($t==='records'){if(($indexes['PRIMARY']['non_unique']??1)!==0||array_values($indexes['PRIMARY']['columns']??[])!==['record_type','id'])return false;}else{if(($indexes['PRIMARY']['non_unique']??1)!==0||array_values($indexes['PRIMARY']['columns']??[])!==['id'])return false;if(($indexes['event_id']['non_unique']??1)!==0||array_values($indexes['event_id']['columns']??[])!==['event_id'])return false;}}return true; }
}

final class RecordStore {
    private static array $memory=[];
    private static function test(): bool { return defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true; }
    public static function requirePersistent(): void { if(!self::test()&&!Schema::ready())throw new Error('persistence_unavailable','Durable persistence/schema unavailable.',503); }
    public static function resetMemory(): void { self::$memory=[];if(class_exists(Audit::class,false))Audit::resetMemory(); }
    public static function get(string $type,string $id): ?array {
        $type=Utils::key($type,48);$id=Utils::text($id,96);if($type===''||$id==='')return null;
        if(self::test())return self::$memory[$type][$id]??null;
        self::requirePersistent();global $wpdb;$raw=$wpdb->get_var($wpdb->prepare('SELECT payload FROM '.Db::table('records').' WHERE record_type=%s AND id=%s',$type,$id));Db::assertRead('record_get');
        if(!is_string($raw)||$raw==='')return null;try{$row=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Error('record_payload_invalid','Persistent record payload is invalid.',500,['record_type'=>$type,'id'=>$id]);}if(!is_array($row)||($row['record_type']??'')!==$type||($row['id']??'')!==$id||(int)($row['version']??0)<1)throw new Error('record_payload_invalid','Persistent record identity is invalid.',500,['record_type'=>$type,'id'=>$id]);return $row;
    }
    public static function put(string $type,string $id,array $row,?int $expectedVersion=null): array {
        $type=Utils::key($type,48);$id=Utils::text($id,96);if($type===''||$id==='')throw new Error('record_identity_invalid','Record identity required.',500);
        $current=self::get($type,$id);$version=(int)($current['version']??0);if($expectedVersion!==null&&$version!==$expectedVersion)throw new Error('record_version_conflict','Record changed concurrently.',409,['expected'=>$expectedVersion,'actual'=>$version]);
        $row['record_type']=$type;$row['id']=$id;$row['actor_id']=(int)($row['actor_id']??0);$row['status']=Utils::key((string)($row['status']??'active'),40)?:'active';$row['version']=$version+1;$row['updated_at']=Utils::now();
        if(self::test()){self::$memory[$type][$id]=$row;return $row;}
        self::requirePersistent();global $wpdb;$data=['record_type'=>$type,'id'=>$id,'actor_id'=>(int)($row['actor_id']??0),'status'=>Utils::key((string)($row['status']??'active'),40),'version'=>$row['version'],'expires_at'=>isset($row['expires_at'])&&(int)$row['expires_at']>0?gmdate('Y-m-d H:i:s',(int)$row['expires_at']):null,'payload'=>Utils::canonicalJson($row),'updated_at'=>gmdate('Y-m-d H:i:s',$row['updated_at'])];
        if($version===0){$ok=$wpdb->insert(Db::table('records'),$data,['%s','%s','%d','%s','%d','%s','%s','%s']);if($ok!==1){$winner=self::get($type,$id);if($winner!==null)throw new Error('record_version_conflict','Concurrent record creation won before this insert.',409,['type'=>$type,'id'=>$id,'expected'=>0,'actual'=>(int)($winner['version']??1)]);throw new Error('record_write_failed','Persistent insert failed.',500,['type'=>$type,'id'=>$id]);}}
        else{$ok=$wpdb->update(Db::table('records'),$data,['record_type'=>$type,'id'=>$id,'version'=>$version],['%s','%s','%d','%s','%d','%s','%s','%s'],['%s','%s','%d']);if($ok!==1)throw new Error('record_version_conflict','Atomic compare-and-swap failed.',409,['type'=>$type,'id'=>$id]);}
        return $row;
    }
    public static function delete(string $type,string $id,?int $expectedVersion=null): void { $type=Utils::key($type,48);$id=Utils::text($id,96);if($type===''||$id==='')throw new Error('record_identity_invalid','Record identity required.',500);if(self::test()){$current=self::$memory[$type][$id]??null;if($expectedVersion!==null&&$current&&(int)($current['version']??0)!==$expectedVersion)throw new Error('record_version_conflict','Record changed before deletion.',409,['expected'=>$expectedVersion,'actual'=>(int)($current['version']??0)]);unset(self::$memory[$type][$id]);return;}self::requirePersistent();global $wpdb;$where=['record_type'=>$type,'id'=>$id];$formats=['%s','%s'];if($expectedVersion!==null){$where['version']=$expectedVersion;$formats[]='%d';}$ok=$wpdb->delete(Db::table('records'),$where,$formats);if($ok===false)throw new Error('record_delete_failed','Persistent delete failed.',500);if($expectedVersion!==null&&$ok!==1){$current=self::get($type,$id);if($current!==null)throw new Error('record_version_conflict','Record changed before deletion.',409,['expected'=>$expectedVersion,'actual'=>(int)($current['version']??0)]);} }
    public static function list(string $type,int $actor=0,?string $status=null,int $limit=500,int $offset=0): array {
    $type=Utils::key($type,48);$limit=max(1,min(2000,$limit));$offset=max(0,min(1000000,$offset));
    if($type==='')throw new Error('record_identity_invalid','Record type is required.',500);
    if(self::test()){
        $rows=array_values(array_filter(self::$memory[$type]??[],fn(array $row)=>($actor<1||(int)($row['actor_id']??0)===$actor)&&($status===null||($row['status']??'')===$status)));
        usort($rows,fn($a,$b)=>((int)($b['updated_at']??0)<=>(int)($a['updated_at']??0)) ?: strcmp((string)($a['id']??''),(string)($b['id']??'')));
        return array_slice($rows,$offset,$limit);
    }
    self::requirePersistent();global $wpdb;$sql='SELECT payload FROM '.Db::table('records').' WHERE record_type=%s';$args=[$type];
    if($actor>0){$sql.=' AND actor_id=%d';$args[]=$actor;}
    if($status!==null){$sql.=' AND status=%s';$args[]=$status;}
    $sql.=' ORDER BY updated_at DESC,id ASC LIMIT '.$offset.','.$limit;
    $rows=$wpdb->get_col($wpdb->prepare($sql,...$args));Db::assertRead('record_list');$out=[];
    foreach((array)$rows as $raw){
        try{$value=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Error('record_payload_invalid','Persistent record list contains invalid JSON.',500,['record_type'=>$type]);}
        if(!is_array($value)||($value['record_type']??'')!==$type||(int)($value['version']??0)<1)throw new Error('record_payload_invalid','Persistent record list contains invalid identity.',500,['record_type'=>$type]);
        $out[]=$value;
    }
    return $out;
}
    private static function scanPage(string $type,int $actor,?string $status,int $limit,string $afterId=''): array {
        $type=Utils::key($type,48);$limit=max(1,min(2000,$limit));$afterId=Utils::text($afterId,96);if($type==='')throw new Error('record_identity_invalid','Record type is required.',500);
        if(self::test()){$rows=array_values(array_filter(self::$memory[$type]??[],fn(array $row)=>($actor<1||(int)($row['actor_id']??0)===$actor)&&($status===null||($row['status']??'')===$status)&&($afterId===''||strcmp((string)($row['id']??''),$afterId)>0)));usort($rows,fn($a,$b)=>strcmp((string)$a['id'],(string)$b['id']));return array_slice($rows,0,$limit);}
        self::requirePersistent();global $wpdb;$sql='SELECT payload FROM '.Db::table('records').' WHERE record_type=%s';$args=[$type];if($actor>0){$sql.=' AND actor_id=%d';$args[]=$actor;}if($status!==null){$sql.=' AND status=%s';$args[]=$status;}if($afterId!==''){$sql.=' AND id>%s';$args[]=$afterId;}$sql.=' ORDER BY id ASC LIMIT '.$limit;$rows=$wpdb->get_col($wpdb->prepare($sql,...$args));Db::assertRead('record_scan');$out=[];foreach((array)$rows as $raw){try{$value=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Error('record_payload_invalid','Persistent record scan contains invalid JSON.',500,['record_type'=>$type]);}if(!is_array($value)||($value['record_type']??'')!==$type||(int)($value['version']??0)<1)throw new Error('record_payload_invalid','Persistent record scan contains invalid identity.',500,['record_type'=>$type]);$out[]=$value;}return $out;
    }
    public static function all(string $type,int $actor=0,?string $status=null,int $maximum=100000): array {
        $maximum=max(1,min(1000000,$maximum));$out=[];$after='';
        while(count($out)<$maximum){$remaining=$maximum-count($out);$pageSize=min(2000,$remaining);$page=self::scanPage($type,$actor,$status,$pageSize,$after);$count=count($page);if($count===0)break;array_push($out,...$page);$after=(string)$page[$count-1]['id'];if(count($out)>=$maximum){$probe=self::scanPage($type,$actor,$status,1,$after);if($probe!==[])throw new Error('record_scan_limit','Record scan exceeded its explicit safety limit.',503,['record_type'=>Utils::key($type,48),'maximum'=>$maximum]);break;}if($count<$pageSize)break;}return $out;
    }
    public static function countStatuses(string $type,array $statuses,bool $exclude=false): int {
        $type=Utils::key($type,48);$statuses=array_values(array_filter(array_unique(array_map(fn($v)=>Utils::key((string)$v,40),$statuses))));if($type===''||$statuses===[])throw new Error('record_status_query_invalid','Record status query is invalid.',500);
        if(self::test())return count(array_filter(self::$memory[$type]??[],fn(array $row)=>$exclude?!in_array((string)($row['status']??''),$statuses,true):in_array((string)($row['status']??''),$statuses,true)));
        self::requirePersistent();global $wpdb;$placeholders=implode(',',array_fill(0,count($statuses),'%s'));$sql='SELECT COUNT(*) FROM '.Db::table('records').' WHERE record_type=%s AND status '.($exclude?'NOT IN':'IN').' ('.$placeholders.')';$value=$wpdb->get_var($wpdb->prepare($sql,$type,...$statuses));Db::assertRead('record_count_status');return max(0,(int)$value);
    }
    public static function oldestByStatuses(string $type,array $statuses): ?array {
        $type=Utils::key($type,48);$statuses=array_values(array_filter(array_unique(array_map(fn($v)=>Utils::key((string)$v,40),$statuses))));if($type===''||$statuses===[])throw new Error('record_status_query_invalid','Record status query is invalid.',500);
        if(self::test()){$rows=array_values(array_filter(self::$memory[$type]??[],fn(array $row)=>in_array((string)($row['status']??''),$statuses,true)));usort($rows,fn($a,$b)=>((int)($a['updated_at']??0)<=>(int)($b['updated_at']??0)) ?: strcmp((string)($a['id']??''),(string)($b['id']??'')));return $rows[0]??null;}
        self::requirePersistent();global $wpdb;$placeholders=implode(',',array_fill(0,count($statuses),'%s'));$raw=$wpdb->get_var($wpdb->prepare('SELECT payload FROM '.Db::table('records').' WHERE record_type=%s AND status IN ('.$placeholders.') ORDER BY updated_at ASC,id ASC LIMIT 1',$type,...$statuses));Db::assertRead('record_oldest_status');if(!is_string($raw)||$raw==='')return null;try{$row=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Error('record_payload_invalid','Persistent status query contains invalid JSON.',500,['record_type'=>$type]);}return is_array($row)?$row:null;
    }

}

final class Audit {
    private static string $memoryHash='';
    public static function resetMemory(): void {self::$memoryHash='';}
    public static function record(string $event,array $context=[]): array {
        $event=Utils::key($event,96);if($event==='')throw new Error('audit_event_invalid','Invalid audit event.',500);
        $actor=(int)($context['actor_id']??(function_exists('get_current_user_id')?get_current_user_id():0));$safe=Utils::redact($context);$prev=self::lastHash();$id=Utils::id('ev');$at=Utils::now();$created=gmdate('Y-m-d H:i:s',$at);$payload=Utils::canonicalJson($safe);$hash=hash('sha256',$prev.'|'.$id.'|'.$event.'|'.$actor.'|'.$created.'|'.$payload);$row=['event_id'=>$id,'event_key'=>$event,'actor_id'=>$actor,'previous_hash'=>$prev,'event_hash'=>$hash,'payload'=>$safe,'created_at'=>$at,'created_at_utc'=>$created,'status'=>'recorded'];
        if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true){RecordStore::put('audit',$id,$row);self::$memoryHash=$hash;return $row;}
        RecordStore::requirePersistent();global $wpdb;$lock='scm_audit_'.substr(hash('sha256',Db::table('audit')),0,54);$acquired=(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,%d)',$lock,10));if($acquired!==1)throw new Error('audit_lock_unavailable','Audit chain lock unavailable.',503);try{$prev=self::lastHash();$hash=hash('sha256',$prev.'|'.$id.'|'.$event.'|'.$actor.'|'.$created.'|'.$payload);$row['previous_hash']=$prev;$row['event_hash']=$hash;$ok=$wpdb->insert(Db::table('audit'),['event_id'=>$id,'event_key'=>$event,'actor_id'=>$actor,'previous_hash'=>$prev,'event_hash'=>$hash,'payload'=>$payload,'created_at'=>$created],['%s','%s','%d','%s','%s','%s','%s']);if($ok!==1)throw new Error('audit_write_failed','Audit evidence could not be persisted.',500);return $row;}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    private static function lastHash(): string { if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true)return self::$memoryHash?:str_repeat('0',64);RecordStore::requirePersistent();global $wpdb;$v=$wpdb->get_var('SELECT event_hash FROM '.Db::table('audit').' ORDER BY id DESC LIMIT 1');Db::assertRead('audit_head');if($v===null)return str_repeat('0',64);if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/',$v))throw new Error('audit_chain_head_invalid','Audit chain head is malformed; append denied.',503);return $v; }
    public static function verifyChain(int $maximum=1000000): bool {
    $maximum=max(1,min(5000000,$maximum));
    if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true){
        $remaining=RecordStore::all('audit',0,null,min(1000000,$maximum));$previous=str_repeat('0',64);
        while($remaining!==[]){$matches=[];foreach($remaining as $index=>$row)if(($row['previous_hash']??'')===$previous)$matches[$index]=$row;if(count($matches)!==1)return false;$index=array_key_first($matches);$row=$matches[$index];$created=(string)($row['created_at_utc']??gmdate('Y-m-d H:i:s',(int)$row['created_at']));$payload=Utils::canonicalJson((array)$row['payload']);$expected=hash('sha256',$previous.'|'.$row['event_id'].'|'.$row['event_key'].'|'.(int)$row['actor_id'].'|'.$created.'|'.$payload);if(!hash_equals($expected,(string)$row['event_hash']))return false;$previous=(string)$row['event_hash'];unset($remaining[$index]);$remaining=array_values($remaining);}return true;
    }
    try{
        RecordStore::requirePersistent();global $wpdb;$previous=str_repeat('0',64);$lastId=0;$checked=0;$pageSize=1000;
        while($checked<$maximum){$limit=min($pageSize,$maximum-$checked);$rows=$wpdb->get_results($wpdb->prepare('SELECT id,event_id,event_key,actor_id,previous_hash,event_hash,payload,created_at FROM '.Db::table('audit').' WHERE id>%d ORDER BY id ASC LIMIT '.$limit,$lastId),defined('ARRAY_A')?ARRAY_A:'ARRAY_A');Db::assertRead('audit_verify');if(!is_array($rows)||$rows===[])return true;foreach($rows as $row){$record=is_array($row)?$row:(array)$row;$payload=(string)$record['payload'];$expected=hash('sha256',$previous.'|'.$record['event_id'].'|'.$record['event_key'].'|'.(int)$record['actor_id'].'|'.$record['created_at'].'|'.$payload);if(!hash_equals($previous,(string)$record['previous_hash'])||!hash_equals($expected,(string)$record['event_hash']))return false;$previous=(string)$record['event_hash'];$lastId=(int)$record['id'];$checked++;}if(count($rows)<$limit)return true;}
        $probe=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.Db::table('audit').' WHERE id>%d ORDER BY id ASC LIMIT 1',$lastId));Db::assertRead('audit_verify_probe');return $probe===null;
    }catch(\Throwable){return false;}
}
}
