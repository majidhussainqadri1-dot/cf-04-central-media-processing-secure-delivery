<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class RecordStore {
    private static array $memory=[];
    private static function wp(): bool { global $wpdb; return isset($wpdb) && is_object($wpdb) && method_exists($wpdb,'replace'); }
    public static function put(string $type,string $id,array $row,?int $expectedVersion=null): array {
        $type=Utils::key($type,48);$id=Utils::text($id,96);$current=self::get($type,$id);$version=(int)($current['version']??0);
        if($expectedVersion!==null && $version!==$expectedVersion) throw new Error('record_version_conflict','Record changed concurrently.',409,['record_type'=>$type,'id'=>$id,'expected'=>$expectedVersion,'actual'=>$version]);
        $row['id']=$id;$row['record_type']=$type;$row['version']=$version+1;$row['updated_at']=Utils::now();
        if(self::wp()){ global $wpdb; $ok=$wpdb->replace(Db::table('records'),['record_type'=>$type,'id'=>$id,'actor_id'=>(int)($row['actor_id']??0),'status'=>Utils::key((string)($row['status']??'active'),40),'version'=>$row['version'],'expires_at'=>isset($row['expires_at'])?gmdate('Y-m-d H:i:s',(int)$row['expires_at']):null,'payload'=>Utils::json($row),'updated_at'=>gmdate('Y-m-d H:i:s')],['%s','%s','%d','%s','%d','%s','%s','%s']); if($ok===false) throw new Error('record_write_failed','Persistent record write failed.',500); }
        self::$memory[$type][$id]=$row; return $row;
    }
    public static function get(string $type,string $id): ?array { $type=Utils::key($type,48);$id=Utils::text($id,96); if(isset(self::$memory[$type][$id])) return self::$memory[$type][$id]; if(self::wp()){ global $wpdb; $raw=$wpdb->get_var($wpdb->prepare('SELECT payload FROM '.Db::table('records').' WHERE record_type=%s AND id=%s',$type,$id)); if(is_string($raw)&&$raw!==''){ $row=json_decode($raw,true,64,JSON_THROW_ON_ERROR); self::$memory[$type][$id]=$row; return $row; } } return null; }
    public static function delete(string $type,string $id): void { $type=Utils::key($type,48);$id=Utils::text($id,96);unset(self::$memory[$type][$id]);if(self::wp()){global $wpdb;$wpdb->delete(Db::table('records'),['record_type'=>$type,'id'=>$id],['%s','%s']);} }
    public static function list(string $type,int $actor=0): array { $type=Utils::key($type,48); if(self::wp()){global $wpdb;$sql='SELECT payload FROM '.Db::table('records').' WHERE record_type=%s'.($actor>0?' AND actor_id=%d':'').' ORDER BY updated_at DESC LIMIT 500';$prepared=$actor>0?$wpdb->prepare($sql,$type,$actor):$wpdb->prepare($sql,$type);$rows=$wpdb->get_col($prepared);return array_values(array_filter(array_map(fn($v)=>json_decode((string)$v,true),$rows)));} return array_values(array_filter(self::$memory[$type]??[],fn($r)=>$actor<1||(int)($r['actor_id']??0)===$actor)); }
}
