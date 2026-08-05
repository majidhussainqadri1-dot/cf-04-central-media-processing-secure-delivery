<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Auth {
    public static function currentUser(): int { return function_exists('get_current_user_id')?(int)get_current_user_id():0; }
    public static function capability(string $cap): int { $user=self::currentUser();if($user<1||!function_exists('current_user_can')||!current_user_can($cap))throw new Error('capability_denied','Current actor is not authorized.',403);return $user; }
    public static function assertVerifiedTransferUser(int $userId,string $action,string $serviceId=''): array {
        if($userId<=0) throw new Error('transfer_user_invalid','Verified identity required.',403);
        $assertion=function_exists('apply_filters')?apply_filters('scm_verified_transfer_assertion',null,$userId,Utils::key($action,48),Utils::key($serviceId,64)):null;
        if(!is_array($assertion)) throw new Error('verified_transfer_assertion_unavailable','File 00 verification evidence unavailable.',503);
        foreach(['verified','approved','active','eligible'] as $flag) if(($assertion[$flag]??false)!==true) throw new Error('transfer_account_not_verified','Account is not transfer eligible.',403,['flag'=>$flag]);
        foreach(['suspended','rejected','erasure_pending','expired'] as $flag) if(!empty($assertion[$flag])) throw new Error('transfer_account_blocked','Account is not transfer eligible.',403,['state'=>$flag]);
        if((int)($assertion['assertion_version']??0)<1) throw new Error('verified_transfer_assertion_incomplete','File 00 verification evidence is incomplete.',503);
        return Utils::redact($assertion);
    }
    public static function domainDecision(string $domain,string $callback,array $context): array {
        $decision=DomainRegistry::call($domain,$callback,$context);
        if(!is_array($decision)||(($decision['allowed']??$decision['authorized']??false)!==true)||(int)($decision['object_version']??0)<1) throw new Error('domain_authorization_denied','Owning domain denied or returned incomplete authorization.',403);
        return $decision;
    }
}
