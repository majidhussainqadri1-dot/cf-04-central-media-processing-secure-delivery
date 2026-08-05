<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Policy {
    public const PRIVACY=['C1','C2','C3','C4','C5'];
    public static function validate(array $p,bool $strict=true): array {
        foreach(['policy_id','policy_version','domain','purpose','privacy_class','media_class','max_size_bytes'] as $f) if(!isset($p[$f])||$p[$f]==='') throw new Error('policy_field_missing','Upload policy is incomplete.',503,['field'=>$f]);
        $p['policy_id']=Utils::key((string)$p['policy_id'],96); $p['domain']=Utils::key((string)$p['domain'],64); $p['purpose']=Utils::key((string)$p['purpose'],96); $p['media_class']=Utils::key((string)$p['media_class'],32);
        if(!in_array($p['privacy_class'],self::PRIVACY,true)) throw new Error('policy_privacy_invalid','Unknown privacy classification.',503);
        $p['max_size_bytes']=(int)$p['max_size_bytes']; if($p['max_size_bytes']<1) throw new Error('policy_size_invalid','Invalid policy size ceiling.',503);
        $p['allowed_mime_types']=array_values(array_unique(array_map('strtolower',(array)($p['allowed_mime_types']??[]))));
        $p['required_scans']=array_values(array_unique(array_map(fn($v)=>Utils::key((string)$v,48),(array)($p['required_scans']??[]))));
        if($strict && !in_array('malware',$p['required_scans'],true)) throw new Error('policy_malware_scan_required','Malware scanning may not be silently disabled.',503);
        $p['delivery_modes']=array_values(array_unique((array)($p['delivery_modes']??['token_endpoint'])));
        $p['max_upload_parts']=max(1,min(10000,(int)($p['max_upload_parts']??1000)));
        $p['max_archive_ratio']=max(1,min(1000,(int)($p['max_archive_ratio']??100)));
        return $p;
    }
    public static function fingerprint(array $p): string { ksort($p); return hash('sha256',Utils::json($p)); }
}
