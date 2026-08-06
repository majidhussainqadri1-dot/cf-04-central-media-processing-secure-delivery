<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class RuntimeGuard {
    public static function enabled(): bool { return defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED === true; }
    public static function requireReady(array $capabilities=[]): void {
        if(!self::enabled()) throw new Error('runtime_disabled','CF-04 runtime is disabled pending approved activation.',503);
        RecordStore::requirePersistent();
        Keyring::assertReady();
        ProviderRegistry::store();
        foreach($capabilities as $capability) ProviderRegistry::assertCapability((string)$capability);
        foreach(['file00','file17'] as $domain) if(!DomainRegistry::has($domain)) throw new Error('domain_contract_unavailable','Required owner contract unavailable.',503,['domain'=>$domain]);
        if(function_exists('apply_filters')){
            $evidence=apply_filters('scm_activation_evidence',null);
            if(!(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true) && (!is_array($evidence)||($evidence['approved']??false)!==true)) throw new Error('activation_evidence_missing','Approved staging/provider/migration/rollback evidence is required.',503);
        }
    }
}

final class DomainRegistry {
    private static array $domains=[];
    public static function reset(): void { self::$domains=[]; }
    public static function register(string $domain,string $version,array $callbacks,array $capabilities=[]): void {
        $domain=Utils::key($domain,64);$version=Utils::text($version,32);$normalized=[];foreach($callbacks as $operation=>$callback){$operation=Utils::key((string)$operation,64);if($operation===''||!is_callable($callback))throw new Error('domain_contract_invalid','Domain operation callback is invalid.',500,['domain'=>$domain,'operation'=>$operation]);if(isset($normalized[$operation]))throw new Error('domain_contract_invalid','Duplicate normalized domain operation.',500,['domain'=>$domain,'operation'=>$operation]);$normalized[$operation]=$callback;}
        if($domain===''||$version===''||$normalized===[]) throw new Error('domain_contract_invalid','Domain contract is incomplete.',500);
        if(isset(self::$domains[$domain])){$existing=self::$domains[$domain];if($existing['version']!==$version||array_keys($existing['callbacks'])!==array_keys($normalized))throw new Error('domain_contract_conflict','Domain contract was already registered with a different contract.',409,['domain'=>$domain]);foreach($normalized as $operation=>$callback)if($existing['callbacks'][$operation]!==$callback)throw new Error('domain_contract_conflict','Domain callback replacement is forbidden.',409,['domain'=>$domain,'operation'=>$operation]);return;}
        $caps=array_values(array_filter(array_unique(array_map(fn($v)=>Utils::key((string)$v,64),$capabilities))));self::$domains[$domain]=['version'=>$version,'callbacks'=>$normalized,'capabilities'=>$caps];
    }
    public static function has(string $domain): bool { return isset(self::$domains[Utils::key($domain,64)]); }
    public static function call(string $domain,string $operation,array $context): array {
        $domain=Utils::key($domain,64);$operation=Utils::key($operation,64);
        $entry=self::$domains[$domain]??null;
        if(!is_array($entry)||!isset($entry['callbacks'][$operation])||!is_callable($entry['callbacks'][$operation])) throw new Error('domain_contract_unavailable','Owning domain contract unavailable.',503,['domain'=>$domain,'operation'=>$operation]);
        try{$result=($entry['callbacks'][$operation])($context);}catch(Error $e){throw $e;}catch(\Throwable $e){throw new Error('domain_contract_failed','Owning domain contract failed.',503,['domain'=>$domain,'operation'=>$operation,'exception'=>get_class($e)]);}
        if(!is_array($result)) throw new Error('domain_contract_invalid','Owning domain returned invalid decision.',503,['domain'=>$domain,'operation'=>$operation]);
        $result['domain']=$domain;$result['contract_version']=$entry['version'];
        return $result;
    }
    public static function decision(string $domain,string $operation,array $context,bool $requireObjectVersion=true): array {
        $decision=self::call($domain,$operation,$context);
        if(($decision['allowed']??$decision['authorized']??false)!==true) throw new Error('domain_authorization_denied','Owning domain denied operation.',403,['domain'=>$domain,'operation'=>$operation,'reason'=>$decision['reason']??'denied']);
        if($requireObjectVersion && (int)($decision['object_version']??0)<1) throw new Error('domain_authorization_incomplete','Owning domain omitted current object version.',503,['domain'=>$domain,'operation'=>$operation]);
        return $decision;
    }
    public static function manifest(): array { $out=[];foreach(self::$domains as $id=>$entry)$out[$id]=['version'=>$entry['version'],'operations'=>array_keys($entry['callbacks']),'capabilities'=>$entry['capabilities']];return $out; }
}

final class Auth {
    public static function currentUser(): int { return function_exists('get_current_user_id')?(int)get_current_user_id():0; }
    public static function requireUser(): int { $id=self::currentUser();if($id<1)throw new Error('authentication_required','Authentication required.',401);return $id; }
    public static function capability(string $capability): int { $id=self::requireUser();$capability=Utils::key($capability,64);if($capability===''||!function_exists('current_user_can')||!current_user_can($capability))throw new Error('capability_denied','Current actor is not authorized.',403,['capability'=>$capability]);return $id; }
    public static function assertActor(int $actor,?string $delegateCapability=null): int { $current=self::requireUser();if($actor<1)throw new Error('actor_invalid','Actor identity is invalid.',400);if($current!==$actor){if($delegateCapability===null||!function_exists('current_user_can')||!current_user_can($delegateCapability))throw new Error('actor_binding_mismatch','Requested actor does not match the authenticated user.',403,['actor_id'=>$actor]);}return $current; }
    public static function verifiedUser(int $userId,string $action,string $serviceId,array $context=[]): array {
        if($userId<1)throw new Error('verified_identity_required','Verified identity required.',403);
        $assertion=DomainRegistry::call('file00','verify_user',['user_id'=>$userId,'action'=>Utils::key($action,64),'service_id'=>Utils::key($serviceId,64),'context'=>Utils::redact($context)]);
        foreach(['verified','approved','active','eligible'] as $flag)if(($assertion[$flag]??false)!==true)throw new Error('account_not_verified','Account is not eligible.',403,['flag'=>$flag]);
        foreach(['suspended','rejected','erasure_pending','expired','sanctioned'] as $flag)if(!empty($assertion[$flag]))throw new Error('account_blocked','Account is blocked.',403,['state'=>$flag]);
        if((int)($assertion['assertion_version']??0)<1)throw new Error('verification_incomplete','Verification assertion incomplete.',503);
        return Utils::redact($assertion);
    }
    public static function transferParties(array $envelope,string $action): array {
        $sender=(int)($envelope['sender_user_id']??0);$type=Utils::key((string)($envelope['recipient_type']??''),16);$recipient=(int)($envelope['recipient_user_id']??0);$group=Utils::text((string)($envelope['recipient_group_id']??''),96);
        $senderAssertion=self::verifiedUser($sender,$action,'file17',['party'=>'sender']);
        $recipientAssertion=null;
        if($type==='user')$recipientAssertion=self::verifiedUser($recipient,$action,'file17',['party'=>'recipient','sender_user_id'=>$sender]);
        elseif($type==='group'){
            if($group==='')throw new Error('recipient_group_invalid','Recipient group required.',400);
            $groupDecision=DomainRegistry::decision('file17','verify_transfer_group',['group_id'=>$group,'sender_user_id'=>$sender,'action'=>$action],false);
            if(($groupDecision['active']??false)!==true||($groupDecision['eligible']??false)!==true)throw new Error('recipient_group_blocked','Recipient group is not eligible.',403);
            $recipientAssertion=$groupDecision;
        }else throw new Error('recipient_type_invalid','Recipient type must be user or group.',400);
        return ['sender'=>$senderAssertion,'recipient'=>$recipientAssertion];
    }
}

final class RightsPolicy {
    public static function normalize(array $input): array {
        Utils::requireFields($input,['rights_id','rights_version','copyright_basis','consent_status','allowed_audiences','allowed_territories','allowed_operations','expires_at'],'rights_policy_incomplete');
        $rawOps=(array)$input['allowed_operations'];$ops=array_values(array_filter(array_unique(array_map(fn($v)=>Utils::key((string)$v,32),$rawOps))));$allowedOps=['view','download','publish','share','stream','preview','extract_text','ocr','transform','reprocess'];
        $rawAud=(array)$input['allowed_audiences'];$aud=array_values(array_filter(array_unique(array_map(fn($v)=>Utils::key((string)$v,64),$rawAud))));$allowedAudiences=['private','user','recipient','group','members','public'];
        $rawTerritories=(array)$input['allowed_territories'];$territories=array_values(array_unique(array_map(fn($v)=>strtoupper(Utils::text((string)$v,8)),$rawTerritories)));
        if($ops===[]||$aud===[]||$territories===[]||count($ops)!==count(array_unique($rawOps))||array_diff($ops,$allowedOps)!==[]||count($aud)!==count(array_unique($rawAud))||array_diff($aud,$allowedAudiences)!==[]||array_filter($territories,fn($v)=>$v!=='GLOBAL'&&!preg_match('/^[A-Z]{2}$/',$v)))throw new Error('rights_policy_incomplete','Rights policy contains empty or unsupported values.',400);
        if(!in_array((string)$input['consent_status'],['granted','not_required'],true))throw new Error('consent_not_granted','Required consent is not granted.',403);
        $expires=(int)$input['expires_at'];if($expires>0&&$expires<=Utils::now())throw new Error('rights_expired','Rights policy expired.',403);
        $normalized=[
            'rights_id'=>Utils::text((string)$input['rights_id'],96),'rights_version'=>max(1,(int)$input['rights_version']),
            'copyright_basis'=>Utils::key((string)$input['copyright_basis'],48),'license_id'=>Utils::text((string)($input['license_id']??''),96),
            'consent_status'=>(string)$input['consent_status'],'consent_id'=>Utils::text((string)($input['consent_id']??''),96),
            'allowed_audiences'=>$aud,'allowed_territories'=>$territories,'allowed_operations'=>$ops,
            'clinical_confidentiality'=>Utils::bool($input['clinical_confidentiality']??false),'guardian_consent_id'=>Utils::text((string)($input['guardian_consent_id']??''),96),
            'expires_at'=>$expires,
        ];
        if($normalized['rights_id']===''||$normalized['copyright_basis']==='')throw new Error('rights_policy_incomplete','Rights identity and copyright basis are required.',400);if($normalized['clinical_confidentiality']&&in_array('public',$normalized['allowed_audiences'],true))throw new Error('clinical_public_audience_denied','Clinically confidential media cannot have a public audience.',400);$normalized['policy_hash']=hash('sha256',Utils::canonicalJson($normalized));return $normalized;
    }
    public static function assert(array $rights,string $operation,array $context): void {
        $operation=Utils::key($operation,32);
        if(!in_array($operation,(array)($rights['allowed_operations']??[]),true))throw new Error('rights_operation_denied','Operation not allowed by rights policy.',403,['operation'=>$operation]);
        $territory=strtoupper(Utils::text((string)($context['territory']??'GLOBAL'),8));
        $allowed=(array)($rights['allowed_territories']??[]);if(!in_array('GLOBAL',$allowed,true)&&!in_array($territory,$allowed,true))throw new Error('rights_territory_denied','Territory not allowed.',403);
        $aud=Utils::key((string)($context['audience_type']??'private'),64);if(!in_array($aud,(array)($rights['allowed_audiences']??[]),true))throw new Error('rights_audience_denied','Audience not allowed.',403);
        $expires=(int)($rights['expires_at']??0);if($expires>0&&$expires<=Utils::now())throw new Error('rights_expired','Rights policy expired.',403);
    }
}

final class Policy {
    public const PRIVACY=['C1','C2','C3','C4','C5'];
    public const MEDIA=['image','audio','video','document','archive','course','other'];
    public static function normalize(array $input,bool $strict=true): array {
        $required=['policy_id','policy_version','owner_domain','purpose','privacy_class','media_class','max_size_bytes','max_part_size_bytes','max_upload_parts','allowed_mime_types','required_scans','derivative_set','retention','rights','delivery'];
        Utils::requireFields($input,$required,'policy_incomplete');
        $privacy=strtoupper(Utils::text((string)$input['privacy_class'],4));if(!in_array($privacy,self::PRIVACY,true))throw new Error('privacy_class_invalid','Invalid privacy class.',400);
        $media=Utils::key((string)$input['media_class'],32);if(!in_array($media,self::MEDIA,true))throw new Error('media_class_invalid','Unsupported media class.',400);
        $max=(int)$input['max_size_bytes'];$part=(int)$input['max_part_size_bytes'];$parts=(int)$input['max_upload_parts'];if($max<1||$max>1073741824||$part<65536||$part>67108864||$parts<1||$parts>20000||$part*$parts<$max)throw new Error('upload_bounds_invalid','Upload bounds invalid.',400);
        $rawMimes=(array)$input['allowed_mime_types'];$mimes=array_values(array_unique(array_map(fn($m)=>strtolower(Utils::text((string)$m,96)),$rawMimes)));if($mimes===[]||count($mimes)!==count(array_unique($rawMimes))||array_filter($mimes,fn($m)=>!preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*$~',$m)))throw new Error('mime_allowlist_empty','MIME allowlist contains an empty or invalid value.',400);
        $scans=array_values(array_filter(array_unique(array_map(fn($s)=>Utils::key((string)$s,48),(array)$input['required_scans']))));
        foreach(['hash','magic','mime','malware','archive','polyglot','decompression_bomb','metadata'] as $scan)if($strict&&!in_array($scan,$scans,true))throw new Error('required_scan_missing','Mandatory scan is missing.',400,['scan'=>$scan]);
        $derivatives=array_values(array_filter(array_unique(array_map(fn($d)=>Utils::key((string)$d,64),(array)$input['derivative_set']))));
        $ret=(array)$input['retention'];Utils::requireFields($ret,['class','source_seconds','derivative_seconds','temporary_seconds','backup_expiry_seconds'],'retention_policy_incomplete');
        $delivery=(array)$input['delivery'];Utils::requireFields($delivery,['modes','grant_ttl_seconds','allow_ranges','allow_download','public_cdn'],'delivery_policy_incomplete');
        $modes=array_values(array_filter(array_unique(array_map(fn($m)=>Utils::key((string)$m,32),(array)$delivery['modes']))));$allowedModes=['same_origin_proxy','token_endpoint','public_cdn'];if($modes===[]||array_diff($modes,$allowedModes)!==[])throw new Error('delivery_mode_invalid','Delivery mode is missing or unsupported.',400);
        $rights=RightsPolicy::normalize((array)$input['rights']);
        $normalized=[
            'policy_id'=>Utils::text((string)$input['policy_id'],96),'policy_version'=>max(1,(int)$input['policy_version']),
            'owner_domain'=>Utils::key((string)$input['owner_domain'],64),'purpose'=>Utils::key((string)$input['purpose'],96),
            'privacy_class'=>$privacy,'media_class'=>$media,'max_size_bytes'=>$max,'max_part_size_bytes'=>$part,'max_upload_parts'=>$parts,
            'allowed_mime_types'=>$mimes,'allowed_extensions'=>self::extensions((array)($input['allowed_extensions']??[])),
            'required_scans'=>$scans,'derivative_set'=>$derivatives,
            'max_width'=>max(0,(int)($input['max_width']??0)),'max_height'=>max(0,(int)($input['max_height']??0)),'max_pixels'=>max(0,(int)($input['max_pixels']??0)),
            'max_duration_seconds'=>max(0,(int)($input['max_duration_seconds']??0)),'max_pages'=>max(0,(int)($input['max_pages']??0)),
            'max_archive_ratio'=>max(1,(int)($input['max_archive_ratio']??100)),'max_archive_depth'=>max(1,(int)($input['max_archive_depth']??5)),'max_archive_entries'=>max(1,(int)($input['max_archive_entries']??10000)),
            'retention'=>['class'=>Utils::key((string)$ret['class'],48),'source_seconds'=>max(0,(int)$ret['source_seconds']),'derivative_seconds'=>max(0,(int)$ret['derivative_seconds']),'temporary_seconds'=>max(60,(int)$ret['temporary_seconds']),'backup_expiry_seconds'=>max(0,(int)$ret['backup_expiry_seconds'])],
            'rights'=>$rights,
            'delivery'=>['modes'=>$modes,'grant_ttl_seconds'=>max(30,min(86400,(int)$delivery['grant_ttl_seconds'])),'allow_ranges'=>Utils::bool($delivery['allow_ranges']),'max_range_bytes'=>max(0,(int)($delivery['max_range_bytes']??8388608)),'allow_download'=>Utils::bool($delivery['allow_download']),'public_cdn'=>Utils::bool($delivery['public_cdn']),'content_disposition'=>in_array(($delivery['content_disposition']??'inline'),['inline','attachment'],true)?$delivery['content_disposition']:'inline'],
            'metadata'=>['strip_location'=>Utils::bool($input['metadata']['strip_location']??true),'strip_device'=>Utils::bool($input['metadata']['strip_device']??true),'strip_author'=>Utils::bool($input['metadata']['strip_author']??true),'preserve_orientation'=>Utils::bool($input['metadata']['preserve_orientation']??true),'preserve_rights'=>Utils::bool($input['metadata']['preserve_rights']??true),'preserve_accessibility'=>Utils::bool($input['metadata']['preserve_accessibility']??true)],
            'safety'=>['require_reviewer_for_low_confidence'=>Utils::bool($input['safety']['require_reviewer_for_low_confidence']??true),'minimum_confidence'=>max(0.0,min(1.0,(float)($input['safety']['minimum_confidence']??0.80)))],
            'issued_at'=>max(1,(int)($input['issued_at']??$input['created_at']??Utils::now())),
        ];
        if($normalized['owner_domain']===''||$normalized['purpose']==='')throw new Error('policy_identity_invalid','Policy owner and purpose are required.',400);if($normalized['issued_at']>Utils::now()+300)throw new Error('policy_issued_at_invalid','Policy issue time is in the future.',400);if($normalized['delivery']['public_cdn']&&$normalized['privacy_class']!=='C1')throw new Error('public_cdn_privacy_denied','Public CDN requires privacy class C1.',400);if($normalized['rights']['clinical_confidentiality']&&($normalized['delivery']['public_cdn']||in_array('public',$normalized['rights']['allowed_audiences'],true)))throw new Error('clinical_public_delivery_denied','Clinically confidential media cannot use public delivery.',400);if($normalized['delivery']['allow_ranges']&&$normalized['delivery']['max_range_bytes']<1)throw new Error('range_policy_invalid','Range delivery requires a positive range limit.',400);$normalized['policy_hash']=hash('sha256',Utils::canonicalJson($normalized));return $normalized;
    }
    private static function extensions(array $values): array {$raw=array_map('strval',$values);$extensions=array_values(array_unique(array_map(fn($x)=>strtolower(Utils::key($x,16)),$raw)));if(array_filter($extensions,fn($x)=>$x===''||!preg_match('/^[a-z0-9][a-z0-9._+-]{0,15}$/',$x))||count($extensions)!==count(array_unique($raw)))throw new Error('extension_allowlist_invalid','Extension allowlist contains an invalid value.',400);return $extensions;}
}

final class CompanionDomainAdapters {
    public static function registerWordPressFilters(): void {
        if(!function_exists('add_filter'))return;
        foreach(['file10','file11','file12','file17','file21','file22','cf01'] as $domain){
            $filter='scm_'.$domain.'_contract';
            $entry=apply_filters($filter,null);
            if(is_array($entry)&&isset($entry['version'],$entry['callbacks']))DomainRegistry::register($domain,(string)$entry['version'],(array)$entry['callbacks'],(array)($entry['capabilities']??[]));
        }
        $file00=apply_filters('scm_file00_contract',null);if(is_array($file00)&&isset($file00['version'],$file00['callbacks']))DomainRegistry::register('file00',(string)$file00['version'],(array)$file00['callbacks']);
    }
}

final class IntegrationRegistry {
    public static function manifest(): array {
        return [
            'module'=>'CF-04','runtime_version'=>defined('SCM_VERSION')?SCM_VERSION:'unknown','schema_version'=>defined('SCM_SCHEMA_VERSION')?SCM_SCHEMA_VERSION:'unknown','contract_version'=>defined('SCM_CONTRACT_VERSION')?SCM_CONTRACT_VERSION:'unknown',
            'canonical_owner'=>'binary-ingest-quarantine-processing-storage-delivery','identity_owner'=>'file00','transfer_owner'=>'file17','notification_owner'=>'file19','shell_owner'=>'file20','assurance_owner'=>'file24','visual_owner'=>'file25',
            'native_domain_owners'=>['file10'=>'video','file11'=>'audio','file12'=>'documents','file17'=>'messages-transfers','file21'=>'knowledge','file22'=>'composer','cf01'=>'clinical'],
            'runtime_default'=>'disabled','domain_contracts'=>DomainRegistry::manifest(),
            'events'=>['scm.asset.quarantined','scm.asset.ready','scm.asset.rejected','scm.derivative.ready','scm.grant.issued','scm.grant.revoked','scm.deletion.pending','scm.deletion.completed','scm.provider.degraded','scm.budget.threshold'],
        ];
    }
}
