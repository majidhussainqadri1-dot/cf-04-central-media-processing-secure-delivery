<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class RecordStore {
    private static array $memory=[];
    private static function wp(): bool { global $wpdb; return isset($wpdb) && is_object($wpdb) && method_exists($wpdb,'insert') && method_exists($wpdb,'update') && method_exists($wpdb,'prepare') && method_exists($wpdb,'get_var'); }
    public static function put(string $type,string $id,array $row,?int $expectedVersion=null): array {
        $type=Utils::key($type,48);
        $id=Utils::text($id,96);
        if($type===''||$id==='') throw new Error('record_identity_invalid','Record type and identifier are required.',500);
        $current=self::get($type,$id);
        $version=(int)($current['version']??0);
        if($expectedVersion!==null && $version!==$expectedVersion) throw self::conflict($type,$id,$expectedVersion,$version);
        $row['id']=$id;
        $row['record_type']=$type;
        $row['version']=$version+1;
        $row['updated_at']=Utils::now();
        if(self::wp()) self::persist($type,$id,$row,$version);
        self::$memory[$type][$id]=$row;
        return $row;
    }
    private static function persist(string $type,string $id,array $row,int $previousVersion): void {
        global $wpdb;
        $data=[
            'record_type'=>$type,
            'id'=>$id,
            'actor_id'=>(int)($row['actor_id']??0),
            'status'=>Utils::key((string)($row['status']??'active'),40),
            'version'=>(int)$row['version'],
            'expires_at'=>isset($row['expires_at'])?gmdate('Y-m-d H:i:s',(int)$row['expires_at']):null,
            'payload'=>Utils::canonicalJson($row),
            'updated_at'=>gmdate('Y-m-d H:i:s',(int)$row['updated_at']),
        ];
        $formats=['%s','%s','%d','%s','%d','%s','%s','%s'];
        if($previousVersion===0){
            $ok=$wpdb->insert(Db::table('records'),$data,$formats);
            if($ok===false){
                $actual=self::persistentVersion($type,$id);
                if($actual>0) throw self::conflict($type,$id,0,$actual);
                throw new Error('record_write_failed','Persistent record insert failed.',500,['record_type'=>$type,'id'=>$id]);
            }
            return;
        }
        $updated=$wpdb->update(Db::table('records'),$data,['record_type'=>$type,'id'=>$id,'version'=>$previousVersion],$formats,['%s','%s','%d']);
        if($updated!==1){
            $actual=self::persistentVersion($type,$id);
            if($actual!==$previousVersion) throw self::conflict($type,$id,$previousVersion,$actual);
            throw new Error('record_write_failed','Persistent record update failed.',500,['record_type'=>$type,'id'=>$id]);
        }
    }
    private static function persistentVersion(string $type,string $id): int {
        if(!self::wp()) return 0;
        global $wpdb;
        $value=$wpdb->get_var($wpdb->prepare('SELECT version FROM '.Db::table('records').' WHERE record_type=%s AND id=%s',$type,$id));
        return is_numeric($value)?(int)$value:0;
    }
    private static function conflict(string $type,string $id,int $expected,int $actual): Error { return new Error('record_version_conflict','Record changed concurrently.',409,['record_type'=>$type,'id'=>$id,'expected'=>$expected,'actual'=>$actual]); }
    public static function get(string $type,string $id): ?array {
        $type=Utils::key($type,48);
        $id=Utils::text($id,96);
        if($type===''||$id==='') return null;
        if(isset(self::$memory[$type][$id])) return self::$memory[$type][$id];
        if(self::wp()){
            global $wpdb;
            $raw=$wpdb->get_var($wpdb->prepare('SELECT payload FROM '.Db::table('records').' WHERE record_type=%s AND id=%s',$type,$id));
            if(is_string($raw)&&$raw!==''){
                try{$row=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new Error('record_payload_invalid','Persistent record payload is invalid.',500,['record_type'=>$type,'id'=>$id]);}
                if(!is_array($row)) throw new Error('record_payload_invalid','Persistent record payload is invalid.',500,['record_type'=>$type,'id'=>$id]);
                self::$memory[$type][$id]=$row;
                return $row;
            }
        }
        return null;
    }
    public static function delete(string $type,string $id): void {
        $type=Utils::key($type,48);
        $id=Utils::text($id,96);
        unset(self::$memory[$type][$id]);
        if(self::wp()){
            global $wpdb;
            $result=$wpdb->delete(Db::table('records'),['record_type'=>$type,'id'=>$id],['%s','%s']);
            if($result===false) throw new Error('record_delete_failed','Persistent record delete failed.',500,['record_type'=>$type,'id'=>$id]);
        }
    }
    public static function list(string $type,int $actor=0): array {
        $type=Utils::key($type,48);
        if(self::wp()){
            global $wpdb;
            $sql='SELECT payload FROM '.Db::table('records').' WHERE record_type=%s'.($actor>0?' AND actor_id=%d':'').' ORDER BY updated_at DESC LIMIT 500';
            $prepared=$actor>0?$wpdb->prepare($sql,$type,$actor):$wpdb->prepare($sql,$type);
            $rows=$wpdb->get_col($prepared);
            $out=[];
            foreach((array)$rows as $raw){
                try{$row=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException $e){continue;}
                if(is_array($row)) $out[]=$row;
            }
            return $out;
        }
        return array_values(array_filter(self::$memory[$type]??[],fn($r)=>$actor<1||(int)($r['actor_id']??0)===$actor));
    }
    public static function resetMemory(): void { self::$memory=[]; }
}
