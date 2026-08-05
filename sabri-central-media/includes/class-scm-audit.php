<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Audit {
    private static array $memory=[];
    public static function record(string $event, array $context=[]): void {
        $eventKey=Utils::key($event,96);
        if($eventKey==='') throw new Error('audit_event_invalid','Audit event identifier is invalid.',500);
        $actor=(int)($context['actor_id']??$context['actor']??(function_exists('get_current_user_id')?get_current_user_id():0));
        $row=['event'=>$eventKey,'actor_id'=>max(0,$actor),'at'=>Utils::now(),'context'=>Utils::redact($context)];
        self::$memory[]=$row;
        if(count(self::$memory)>1000) array_shift(self::$memory);
        global $wpdb;
        if(isset($wpdb) && is_object($wpdb) && method_exists($wpdb,'insert')) {
            $ok=$wpdb->insert(Db::table('audit'),[
                'event_key'=>$eventKey,
                'actor_id'=>$row['actor_id'],
                'payload'=>Utils::canonicalJson($row['context']),
                'created_at'=>gmdate('Y-m-d H:i:s',$row['at']),
            ],['%s','%d','%s','%s']);
            if($ok===false && defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED===true) throw new Error('audit_write_failed','Security audit evidence could not be persisted.',500,['event'=>$eventKey]);
        }
        if(function_exists('do_action')) do_action('scm_audit_event',$row);
    }
    public static function memory(): array { return self::$memory; }
    public static function reset(): void { self::$memory=[]; }
}
