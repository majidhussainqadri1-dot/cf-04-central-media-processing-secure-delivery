<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Audit {
    private static array $memory=[];
    public static function record(string $event, array $context=[]): void {
        $row=['event'=>Utils::key($event,96),'at'=>Utils::now(),'context'=>Utils::redact($context)];
        self::$memory[]=$row;
        if(function_exists('do_action')) do_action('scm_audit_event',$row);
    }
    public static function memory(): array { return self::$memory; }
}
