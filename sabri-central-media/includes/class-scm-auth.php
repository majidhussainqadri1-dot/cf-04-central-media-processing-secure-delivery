<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Auth {
    public static function currentUser(): int { return function_exists('get_current_user_id')?(int)get_current_user_id():0; }
    public static function capability(string $cap): int { $u=self::currentUser(); if($u<1||!function_exists('current_user_can')||!current_user_can($cap)) throw new Error('capability_denied','Current actor is not authorized.',403); return $u; }
    public static function assertVerifiedTransferUser(int $userId,string $action,string $serviceId=''): array {
        if($userId<=0) throw new Error('transfer_user_invalid','Verified identity required.',403);
        $a=function_exists('apply_filters')?apply_filters('scm_verified_transfer_assertion',null,$userId,Utils::key($action,48),Utils::key($serviceId,64)):null;
        if(!is_array($a)){ if(defined('SCM_RUNTIME_ENABLED')&&SCM_RUNTIME_ENABLED===true) throw new Error('verified_transfer_assertion_unavailable','File 00 verification evidence unavailable.',503); $a=['verified'=>true,'approved'=>true,'active'=>true,'eligible'=>true,'suspended'=>false]; }
        foreach(['verified','approved','active','eligible'] as $f) if(($a[$f]??false)!==true) throw new Error('transfer_account_not_verified','Account is not transfer eligible.',403,['flag'=>$f]);
        foreach(['suspended','rejected','erasure_pending','expired'] as $f) if(!empty($a[$f])) throw new Error('transfer_account_blocked','Account is not transfer eligible.',403,['state'=>$f]);
        return Utils::redact($a);
    }
    public static function domainDecision(string $domain,string $callback,array $context): array { $d=DomainRegistry::callOptional($domain,$callback,$context); if(!is_array($d)||(($d['allowed']??$d['authorized']??false)!==true)||(int)($d['object_version']??0)<1) throw new Error('domain_authorization_denied','Owning domain denied or returned incomplete authorization.',403); return $d; }
}
