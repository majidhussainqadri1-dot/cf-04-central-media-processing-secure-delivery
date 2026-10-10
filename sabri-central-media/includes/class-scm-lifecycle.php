<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class LegalHoldService {
    private const SCOPES=['delivery','processing','deletion','reprocess','provider_exit','all'];
    private const RESTRICTIONS=['restricted','blocked'];
    private const STATES=['active','escalated','released'];
    private const OPERATIONS=[
        'delete'=>'deletion','deletion'=>'deletion','expire-derivatives'=>'deletion',
        'expire_derivatives'=>'deletion','deliver'=>'delivery','delivery'=>'delivery',
        'process'=>'processing','processing'=>'processing','repair'=>'reprocess',
        'reprocess'=>'reprocess','provider-exit'=>'provider_exit',
        'provider_exit'=>'provider_exit','all'=>'all',
    ];
    private static function scopeList(mixed $raw,string $code): array {
        if(!is_array($raw)||!array_is_list($raw)||$raw===[])
            throw new Error($code,'A nonempty list of legal-hold scopes is required.',400);
        $scopes=[];
        foreach($raw as $value){
            if(!is_string($value)||!in_array($value,self::SCOPES,true)||in_array($value,$scopes,true))
                throw new Error($code,'Legal-hold scope is unsupported or duplicated.',400);
            $scopes[]=$value;
        }
        return $scopes;
    }
    private static function stored(array $hold): array {
        if(($hold['record_type']??null)!=='hold'
            ||!is_string($hold['id']??null)||$hold['id']===''
            ||($hold['hold_id']??null)!==$hold['id']
            ||!is_string($hold['asset_id']??null)||$hold['asset_id']===''
            ||!in_array($hold['status']??null,self::STATES,true)
            ||!in_array($hold['access_restriction']??null,self::RESTRICTIONS,true)
            ||!is_string($hold['authority']??null)||trim($hold['authority'])===''
            ||!is_string($hold['reason']??null)||trim($hold['reason'])==='')
            throw new Error('hold_record_invalid','Persisted legal-hold identity or policy is invalid.',500);
        try{
            $hold['version']=Utils::integer($hold['version']??null,'hold_record_invalid',1);
            $hold['version_number']=Utils::integer($hold['version_number']??null,'hold_record_invalid',1);
            $hold['object_version']=Utils::integer($hold['object_version']??null,'hold_record_invalid',1);
            $hold['review_at']=Utils::integer($hold['review_at']??null,'hold_record_invalid',1);
            $hold['expires_at']=Utils::integer($hold['expires_at']??null,'hold_record_invalid',0);
            $hold['scope']=self::scopeList($hold['scope']??null,'hold_record_invalid');
        }catch(Error $e){throw new Error('hold_record_invalid','Persisted legal-hold metadata is invalid.',500);}
        return $hold;
    }
    public static function place(string $assetId,int $actor,array $input): array {
        Auth::capability('media_hold');Auth::assertActor($actor,'manage_options');
        Utils::requireFields($input,['authority','reason','scope','review_at'],'hold_incomplete');
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        if(!is_string($input['authority'])||!is_string($input['reason']))
            throw new Error('hold_incomplete','Hold authority and reason must be text.',400);
        $authority=Utils::text($input['authority'],191);$reason=Utils::text($input['reason'],1000);
        $scope=self::scopeList($input['scope'],'hold_scope_invalid');
        if($authority===''||$reason==='')throw new Error('hold_incomplete','Hold authority and reason are required.',400);
        $restriction=$input['access_restriction']??'restricted';
        if(!is_string($restriction)||!in_array($restriction,self::RESTRICTIONS,true))
            throw new Error('hold_access_restriction_invalid','Unsupported legal-hold access restriction.',400);
        $now=Utils::now();
        $reviewAt=Utils::integer($input['review_at'],'hold_schedule_invalid',1);
        $expires=array_key_exists('expires_at',$input)?Utils::integer($input['expires_at'],'hold_schedule_invalid',0):0;
        if($reviewAt<=$now||($expires>0&&$expires<$reviewAt))
            throw new Error('hold_schedule_invalid','Hold review/expiry schedule invalid.',400);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_hold',[
            'asset'=>$asset,'actor_id'=>$actor,'hold'=>Utils::redact($input),
        ]);
        if($decision['object_version']!==Utils::integer($asset['object_version']??null,'hold_asset_version_invalid',1))
            throw new Error('domain_object_version_stale','Hold authorization is stale.',409);
        if(($decision['hold_allowed']??null)!==true)throw new Error('hold_denied','Owner denied hold.',403);
        $id=Utils::id('hold');
        $hold=[
            'actor_id'=>$actor,'hold_id'=>$id,'asset_id'=>$assetId,
            'object_version'=>$decision['object_version'],
            'owner_contract_version'=>(string)($decision['contract_version']??''),
            'authority'=>$authority,'reason'=>$reason,'scope'=>$scope,'status'=>'active',
            'access_restriction'=>$restriction,'review_at'=>$reviewAt,'expires_at'=>$expires,
            'placed_at'=>$now,'version_number'=>1,
        ];
        $hold=RecordStore::put('hold',$id,$hold);
        DeliveryService::revokeForAsset($assetId,'legal_hold');
        Audit::record('legal_hold_placed',['hold_id'=>$id,'asset_id'=>$assetId,'actor_id'=>$actor,'authority'=>$hold['authority']]);
        return $hold;
    }
    public static function active(string $assetId,?string $operation=null): array {
        $scope=null;
        if($operation!==null){
            $normalized=Utils::key($operation,32);
            if(!array_key_exists($normalized,self::OPERATIONS))
                throw new Error('hold_operation_invalid','Unknown legal-hold operation.',400);
            $scope=self::OPERATIONS[$normalized];
        }
        $holds=[];
        foreach(RecordStore::all('hold',0,null,100000) as $raw){
            if(!is_string($raw['asset_id']??null)||$raw['asset_id']==='')
                throw new Error('hold_record_invalid','Persisted legal-hold asset identity is invalid.',500);
            if($raw['asset_id']!==$assetId)continue;
            $hold=self::stored($raw);
            if($hold['status']==='released')continue;
            if($hold['expires_at']>0&&$hold['expires_at']<=Utils::now())continue;
            if($scope!==null&&!in_array('all',$hold['scope'],true)&&!in_array($scope,$hold['scope'],true))continue;
            $holds[]=$hold;
        }
        return $holds;
    }
    public static function assertNoHold(string $assetId,string $operation): void {
        $holds=self::active($assetId,$operation);
        if($holds!==[])throw new Error('asset_on_hold','Asset is under a scoped legal/security hold.',423,[
            'operation'=>Utils::key($operation,32),'hold_ids'=>array_column($holds,'id'),
        ]);
    }
    public static function review(string $holdId,int $actor,string $decision,string $reason): array {
        Auth::capability('media_hold');Auth::assertActor($actor,'manage_options');
        $raw=RecordStore::get('hold',$holdId);
        if(!$raw)throw new Error('hold_not_found','Hold not found.',404);
        $hold=self::stored($raw);
        if(!in_array($hold['status'],['active','escalated'],true))
            throw new Error('hold_state_invalid','Hold is not reviewable.',409);
        if(!in_array($decision,['continue','release','escalate'],true))
            throw new Error('hold_decision_invalid','Invalid hold decision.',400);
        $reason=Utils::text($reason,1000);
        if($reason==='')throw new Error('hold_review_reason_required','Hold review reason is required.',400);
        $asset=RecordStore::get('asset',$hold['asset_id']);
        if(!$asset)throw new Error('asset_not_found','Held asset not found.',404);
        $owner=DomainRegistry::decision($asset['owner_domain'],'authorize_hold',[
            'asset'=>$asset,'actor_id'=>$actor,'hold'=>$hold,
            'phase'=>'review','review_decision'=>$decision,'review_reason'=>$reason,
        ]);
        if($owner['object_version']!==Utils::integer($asset['object_version']??null,'hold_asset_version_invalid',1))
            throw new Error('domain_object_version_stale','Hold review authorization is stale.',409);
        if(($owner['hold_allowed']??null)!==true)throw new Error('hold_denied','Owner denied hold review.',403);
        if($hold['version_number']===PHP_INT_MAX)
            throw new Error('hold_record_invalid','Legal-hold review counter is exhausted.',500);
        $now=Utils::now();
        $hold['reviewed_by']=$actor;$hold['review_reason']=$reason;$hold['reviewed_at']=$now;
        $hold['review_object_version']=$owner['object_version'];
        $hold['review_contract_version']=(string)($owner['contract_version']??'');
        $hold['version_number']++;
        if($decision==='release'){$hold['status']='released';$hold['released_at']=$now;}
        else{
            $hold['status']=$decision==='escalate'?'escalated':'active';
            $next=$now+($decision==='escalate'?604800:2592000);
            $hold['review_at']=$hold['expires_at']>0?min($next,$hold['expires_at']):$next;
        }
        return RecordStore::put('hold',$holdId,$hold,$hold['version']);
    }
}

final class RetentionService {
    private const STATES=['scheduled','pending','derivatives_expired','deletion_requested'];
    private static function timestamp(int $now,mixed $seconds): int {
        $duration=Utils::integer($seconds,'retention_schedule_invalid',0);
        if($duration>PHP_INT_MAX-$now)throw new Error('retention_schedule_invalid','Retention schedule overflows supported time.',400);
        return $now+$duration;
    }
    private static function stored(array $row): array {
        if(($row['record_type']??null)!=='retention'||!is_string($row['id']??null)||$row['id']===''
            ||!is_string($row['asset_id']??null)||$row['asset_id']===''
            ||!in_array($row['status']??null,self::STATES,true)
            ||!is_string($row['retention_class']??null)||$row['retention_class']==='')
            throw new Error('retention_record_invalid','Persisted retention identity or state is invalid.',500);
        try{
            $row['version']=Utils::integer($row['version']??null,'retention_record_invalid',1);
            foreach(['source_delete_at','derivative_delete_at','temporary_cleanup_at','backup_expiry_at'] as $field)
                $row[$field]=Utils::integer($row[$field]??null,'retention_record_invalid',1);
        }catch(Error $e){throw new Error('retention_record_invalid','Persisted retention schedule is invalid.',500);}
        if(array_key_exists('derivatives_expired',$row)&&!is_bool($row['derivatives_expired']))
            throw new Error('retention_record_invalid','Persisted derivative-expiry state is invalid.',500);
        if(array_key_exists('deletion_id',$row)&&(!is_string($row['deletion_id'])||$row['deletion_id']===''))
            throw new Error('retention_record_invalid','Persisted deletion reference is invalid.',500);
        return $row;
    }
    public static function schedule(string $assetId): array {
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        $ret=$asset['policy']['retention']??null;
        $hash=$asset['policy_hash']??null;
        if(!is_array($ret)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)
            ||!is_string($ret['class']??null)||$ret['class']==='')
            throw new Error('retention_policy_invalid','Asset retention policy identity is invalid.',500);
        $decision=DomainRegistry::decision($asset['owner_domain'],'retention_decision',[
            'asset'=>$asset,'policy'=>$ret,
        ]);
        if($decision['object_version']!==Utils::integer($asset['object_version']??null,'retention_policy_invalid',1))
            throw new Error('domain_object_version_stale','Retention decision is stale.',409);
        $now=Utils::now();
        $sourceDelete=max(self::timestamp($now,$ret['source_seconds']??null),
            Utils::integer($decision['source_retain_until']??null,'retention_schedule_invalid',0));
        $derivativeDelete=max(self::timestamp($now,$ret['derivative_seconds']??null),
            Utils::integer($decision['derivative_retain_until']??null,'retention_schedule_invalid',0));
        $backupExpiry=max(self::timestamp($now,$ret['backup_expiry_seconds']??null),
            Utils::integer($decision['backup_expiry_at']??null,'retention_schedule_invalid',0));
        $temporaryCleanup=self::timestamp($now,$ret['temporary_seconds']??null);
        $id=hash('sha256',$assetId.'|'.$hash);
        $existing=RecordStore::get('retention',$id);
        if($existing!==null){
            $old=self::stored($existing);
            if($old['asset_id']!==$assetId||$old['retention_class']!==$ret['class'])
                throw new Error('retention_record_invalid','Prior retention schedule identity is invalid.',500);
        }
        $record=[
            'actor_id'=>0,'asset_id'=>$assetId,'retention_class'=>$ret['class'],'status'=>'scheduled',
            'source_delete_at'=>$sourceDelete,'derivative_delete_at'=>$derivativeDelete,
            'temporary_cleanup_at'=>$temporaryCleanup,'backup_expiry_at'=>$backupExpiry,
            'decision_version'=>$decision['contract_version'],'created_at'=>$now,
        ];
        return RecordStore::put('retention',$id,$record,$existing?$old['version']:0);
    }
    public static function run(int $now=0): array {
        $now=$now>0?$now:Utils::now();$cleanup=UploadService::cleanupExpired($now);
        $out=['temporary_cleaned'=>(int)$cleanup['parts_purged'],'uploads_expired'=>(int)$cleanup['expired'],
            'cleanup_failed'=>(int)$cleanup['failed'],'derivatives_expired'=>0,'deletion_requested'=>0,
            'holds_skipped'=>0,'records_failed'=>0];
        foreach(RecordStore::all('retention',0,null,100000) as $snapshot){
            $assetId=is_string($snapshot['asset_id']??null)?$snapshot['asset_id']:'';
            try{
                $retention=self::stored($snapshot);
                if($retention['status']==='deletion_requested')continue;
                $assetId=$retention['asset_id'];
                $asset=RecordStore::get('asset',$assetId);
                if(!$asset)throw new Error('retention_asset_missing','Retention asset is unavailable.',500);
                $hash=$asset['policy_hash']??null;
                $class=$asset['policy']['retention']['class']??null;
                if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)
                    ||!is_string($class)||$class!==$retention['retention_class']
                    ||!hash_equals($retention['id'],hash('sha256',$assetId.'|'.$hash)))
                    throw new Error('retention_policy_stale','Retention schedule is no longer bound to current asset policy.',409);
                $deletionHeld=LegalHoldService::active($assetId,'deletion')!==[];
                $expiredDerivatives=false;$requestedDeletion=false;
                if($retention['derivative_delete_at']<=$now
                    &&!($retention['derivatives_expired']??false)
                    &&$retention['source_delete_at']>$now){
                    if($deletionHeld){$out['holds_skipped']++;continue;}
                    DeletionService::expireDerivatives($assetId,'retention-expiry');
                    $retention['derivatives_expired']=true;$retention['status']='derivatives_expired';
                    $expiredDerivatives=true;
                }
                if($retention['source_delete_at']<=$now&&!isset($retention['deletion_id'])){
                    if($deletionHeld){$out['holds_skipped']++;continue;}
                    $request=DeletionService::request($assetId,0,'retention-expiry',[
                        'backup_expiry_at'=>$retention['backup_expiry_at'],
                    ]);
                    $retention['status']='deletion_requested';$retention['deletion_id']=$request['id'];
                    $requestedDeletion=true;
                }
                $retention['temporary_cleaned']=true;
                RecordStore::put('retention',$retention['id'],$retention,$retention['version']);
                if($expiredDerivatives)$out['derivatives_expired']++;
                if($requestedDeletion)$out['deletion_requested']++;
            }catch(\Throwable $failure){
                $out['records_failed']++;$code=$failure instanceof Error?$failure->errorCode:'unexpected';
                try{DegradedStateService::record('retention-run',$code,[
                    'asset_ref'=>$assetId===''?'':Utils::hashReference($assetId),
                    'retention_ref'=>Utils::hashReference((string)($snapshot['id']??'')),
                ]);}catch(\Throwable){}
                continue;
            }
        }
        return $out;
    }
}

final class DeletionService {

    private const DELETION_STEPS=['revoke_grants','purge_cdn','delete_derivatives','delete_source','delete_mappings','backup_ledger','tombstone'];
    private const DELETION_STATUSES=['pending_revoke','pending_cdn','pending_derivatives','pending_source','pending_mappings','pending_backup_ledger','pending_tombstone','pending_retry','completed','cancelled'];
    private static function stored(array $d,?string $expectedId=null): array {
        if(($d['record_type']??null)!=='deletion'
            ||!is_string($d['id']??null)||$d['id']===''
            ||($d['deletion_id']??null)!==$d['id']
            ||($expectedId!==null&&$d['id']!==$expectedId)
            ||!is_string($d['asset_id']??null)||$d['asset_id']===''
            ||!is_string($d['reason']??null)||$d['reason']===''
            ||!in_array($d['status']??null,self::DELETION_STATUSES,true)
            ||!is_array($d['steps']??null)
            ||array_keys($d['steps'])!==self::DELETION_STEPS)
            throw new Error('deletion_record_invalid','Persisted deletion identity or state is invalid.',500);
        try {
            foreach(['version'=>1,'actor_id'=>0,'attempts'=>0,'next_attempt_at'=>0,'backup_expiry_at'=>1,'created_at'=>1] as $field=>$minimum)
                $d[$field]=Utils::integer($d[$field]??null,'deletion_record_invalid',$minimum);
            if(array_key_exists('completed_at',$d))
                $d['completed_at']=Utils::integer($d['completed_at'],'deletion_record_invalid',1);
        }catch(Error $e){throw new Error('deletion_record_invalid','Persisted deletion numeric metadata is invalid.',500);}
        $pending=false;$done=0;
        foreach(self::DELETION_STEPS as $step){
            $value=$d['steps'][$step];
            if(!in_array($value,['pending','complete'],true)||($pending&&$value==='complete'))
                throw new Error('deletion_record_invalid','Deletion step state/order is invalid.',500);
            if($value==='pending')$pending=true;else $done++;
        }
        $states=['pending_revoke','pending_cdn','pending_derivatives','pending_source','pending_mappings','pending_backup_ledger','pending_tombstone'];
        if(($d['status']==='completed'&&($done!==7||!isset($d['completed_at'])))
            ||(in_array($d['status'],$states,true)&&array_search($d['status'],$states,true)!==$done)
            ||($d['status']==='pending_retry'&&$done===7))
            throw new Error('deletion_record_invalid','Deletion status does not match persisted steps.',500);
        return $d;
    }
    private static function auditRequested(array $d): void {
        Audit::recordOnce('deletion_requested',[
            'deletion_id'=>$d['id'],'asset_id'=>$d['asset_id'],
            'actor_id'=>$d['actor_id'],'reason'=>$d['reason'],
        ],$d['id']);
    }
    private static function auditCompleted(array $d): void {
        Audit::recordOnce('deletion_completed',[
            'deletion_id'=>$d['id'],'asset_id'=>$d['asset_id'],
            'actor_id'=>$d['actor_id'],'attempts'=>$d['attempts'],
        ],$d['id']);
    }
    private static function recoverDeletedAsset(array $d,array $asset): array {
        if(($asset['status']??null)!=='deleted'||($asset['deletion_id']??null)!==$d['id']
            ||!in_array($d['status'],['pending_tombstone','pending_retry'],true)
            ||$d['steps']['tombstone']!=='pending'
            ||array_slice(array_values($d['steps']),0,6)!==array_fill(0,6,'complete'))
            throw new Error('deletion_evidence_invalid','Deleted asset does not match a recoverable terminal deletion.',500);
        $deletedAt=Utils::integer($asset['deleted_at']??null,'deletion_evidence_invalid',1);
        $ledger=RecordStore::get('backup_expiry',hash('sha256',$d['id'].'|backup-expiry'));
        if(!is_array($ledger)||($ledger['asset_id']??null)!==$d['asset_id']
            ||($ledger['deletion_id']??null)!==$d['id']
            ||($ledger['status']??null)!=='awaiting_backup_expiry'
            ||($ledger['backup_expiry_at']??null)!==$d['backup_expiry_at'])
            throw new Error('deletion_evidence_invalid','Terminal recovery requires matching backup evidence.',500);
        $tomb=RecordStore::get('tombstone',$d['asset_id']);
        if($tomb===null){
            foreach(['owner_domain','owner_object','object_version','policy_hash'] as $field)
                if(!isset($asset[$field]))throw new Error('deletion_evidence_invalid','Legacy tombstone recovery lacks asset identity.',500);
            if(!is_array($asset['rights']??null)||!is_string($asset['rights']['policy_hash']??null))
                throw new Error('deletion_evidence_invalid','Legacy tombstone recovery lacks rights identity.',500);
            $tomb=RecordStore::put('tombstone',$d['asset_id'],[
                'actor_id'=>$d['actor_id'],'asset_id'=>$d['asset_id'],'deletion_id'=>$d['id'],
                'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],
                'object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],
                'rights_hash'=>$asset['rights']['policy_hash'],'reason'=>$d['reason'],
                'status'=>'deleted','deleted_at'=>$deletedAt,
                'backup_expiry_at'=>$d['backup_expiry_at'],
            ],0);
        }
        if(!self::tombstoneMatches($tomb,$asset,$d))
            throw new Error('deletion_evidence_invalid','Terminal recovery tombstone conflicts with asset identity.',500);
        $d['steps']['tombstone']='complete';$d['status']='completed';$d['completed_at']=$deletedAt;
        $d=self::save($d);self::completedEvidence($d);self::auditCompleted($d);self::notifyCompleted($d,$asset);
        return $d;
    }
    private static function notifyCompleted(array $d,array $asset): void {
        $noticeId=hash('sha256',$d['id'].'|completion-revocation');
        $eventId='scm-revoked-'.$d['id'];
        $notice=RecordStore::get('revocation_notice',$noticeId);
        if($notice!==null){
            if(($notice['record_type']??null)!=='revocation_notice'
                ||($notice['id']??null)!==$noticeId
                ||($notice['deletion_id']??null)!==$d['id']
                ||($notice['asset_id']??null)!==$d['asset_id']
                ||($notice['event_id']??null)!==$eventId
                ||!in_array($notice['status']??null,['pending','delivered'],true))
                throw new Error('revocation_notice_invalid','Completion notification identity is invalid.',500);
            if($notice['status']==='delivered')return;
        }else{
            $notice=RecordStore::put('revocation_notice',$noticeId,[
                'actor_id'=>$d['actor_id'],'asset_id'=>$d['asset_id'],
                'deletion_id'=>$d['id'],'event_id'=>$eventId,
                'status'=>'pending','created_at'=>Utils::now(),
            ],0);
        }
        if(!function_exists('do_action'))
            throw new Error('revocation_dispatch_unavailable','Owner revocation hook is unavailable.',503);
        do_action('scm.media.revoked',[
            'event_id'=>$eventId,'asset_id'=>$d['asset_id'],
            'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],
            'object_version'=>$asset['object_version'],'reason'=>'deleted',
        ]);
        $notice['status']='delivered';$notice['delivered_at']=Utils::now();
        RecordStore::put('revocation_notice',$noticeId,$notice,(int)$notice['version']);
    }
    private static function tombstoneMatches(array $tomb,array $asset,array $d): bool {
        $rights=$asset['rights']['policy_hash']??null;
        if(!is_string($rights)||$rights==='')return false;
        foreach(['owner_domain','owner_object','object_version','policy_hash'] as $field)
            if(!array_key_exists($field,$asset)||!array_key_exists($field,$tomb)
                ||$tomb[$field]!==$asset[$field])return false;
        return ($tomb['asset_id']??null)===$d['asset_id']
            &&($tomb['status']??null)==='deleted'
            &&($tomb['reason']??null)===$d['reason']
            &&($tomb['backup_expiry_at']??null)===$d['backup_expiry_at']
            &&($tomb['rights_hash']??null)===$rights
            &&(!array_key_exists('deletion_id',$tomb)||$tomb['deletion_id']===$d['id']);
    }
    private static function completedEvidence(array $d): void {
        $asset=RecordStore::get('asset',$d['asset_id']);
        $tomb=RecordStore::get('tombstone',$d['asset_id']);
        $ledger=RecordStore::get('backup_expiry',hash('sha256',$d['id'].'|backup-expiry'));
        if(!is_array($asset)||($asset['status']??null)!=='deleted'
            ||($asset['deletion_id']??null)!==$d['id']
            ||!is_array($tomb)||!self::tombstoneMatches($tomb,$asset,$d)
            ||!is_array($ledger)||($ledger['asset_id']??null)!==$d['asset_id']
            ||($ledger['deletion_id']??null)!==$d['id']
            ||($ledger['status']??null)!=='awaiting_backup_expiry'
            ||($ledger['backup_expiry_at']??null)!==$d['backup_expiry_at'])
            throw new Error('deletion_evidence_invalid','Completed deletion evidence does not match asset, tombstone and backup ledger.',500);
    }
    public static function request(string $assetId,int $actor,string $reason,array $context=[]): array {
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        if(($asset['status']??'')==='deleted')throw new Error('asset_deleted','Asset is already deleted.',410);
        LegalHoldService::assertNoHold($assetId,'delete');
        if($actor>0){Auth::assertActor($actor,'manage_options');if($actor!==(int)$asset['actor_id'])Auth::capability('media_reprocess');}
        $reason=Utils::key($reason,64);
        if($reason==='')throw new Error('deletion_reason_required','Deletion reason required.',400);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_deletion',[
            'asset'=>$asset,'actor_id'=>$actor,'reason'=>$reason,'context'=>Utils::redact($context),
        ]);
        if($decision['object_version']!==Utils::integer($asset['object_version']??null,'domain_object_version_stale',1))
            throw new Error('domain_object_version_stale','Owner version stale.',409);
        if(array_key_exists('deletion_id',$asset)&&$asset['deletion_id']!==null&&$asset['deletion_id']!==''){
            if(!is_string($asset['deletion_id']))throw new Error('deletion_record_invalid','Asset deletion reference is invalid.',500);
            $existing=RecordStore::get('deletion',$asset['deletion_id']);
            if(!$existing)throw new Error('deletion_record_invalid','Asset deletion reference is missing.',500);
            $existing=self::stored($existing,$asset['deletion_id']);
            if($existing['asset_id']!==$assetId||$existing['actor_id']!==$actor||$existing['reason']!==$reason)
                throw new Error('deletion_record_invalid','Existing deletion request identity conflicts.',409);
            if($existing['status']==='completed'){self::completedEvidence($existing);throw new Error('asset_deleted','Asset is already deleted.',410);}
            if($existing['status']!=='cancelled'){self::auditRequested($existing);return $existing;}
        }
        $id=Utils::id('del');$now=Utils::now();
        $requestedBackup=array_key_exists('backup_expiry_at',$context)
            ?Utils::integer($context['backup_expiry_at'],'deletion_backup_expiry_invalid',0)
            :$now+2592000;
        $backup=max($now,min($now+315360000,$requestedBackup));
        $record=['actor_id'=>$actor,'deletion_id'=>$id,'asset_id'=>$assetId,'reason'=>$reason,
            'status'=>'pending_revoke','steps'=>array_fill_keys(self::DELETION_STEPS,'pending'),
            'attempts'=>0,'next_attempt_at'=>$now,'backup_expiry_at'=>$backup,'created_at'=>$now];
        $previousState=['status'=>$asset['status']??null,'deletion_id'=>$asset['deletion_id']??null,'deletion_requested_at'=>$asset['deletion_requested_at']??null];
        $asset['status']='deletion_pending';$asset['deletion_id']=$id;$asset['deletion_requested_at']=$now;
        RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);
        try{$record=RecordStore::put('deletion',$id,$record);}
        catch(\Throwable $exception){
            $fresh=RecordStore::get('asset',$assetId);
            if($fresh&&($fresh['deletion_id']??'')===$id){
                $fresh['status']=$previousState['status'];
                if($previousState['deletion_id']===null)unset($fresh['deletion_id']);else $fresh['deletion_id']=$previousState['deletion_id'];
                if($previousState['deletion_requested_at']===null)unset($fresh['deletion_requested_at']);else $fresh['deletion_requested_at']=$previousState['deletion_requested_at'];
                RecordStore::put('asset',$assetId,$fresh,(int)$fresh['version']);
            }
            throw $exception;
        }
        self::auditRequested($record);
        return $record;
    }
    public static function expireDerivatives(string $assetId,string $reason): array {
        $asset=self::authorizeCurrent($assetId,0,$reason,['scope'=>'derivatives','phase'=>'expire_derivatives'],'expire-derivatives');DeliveryService::revokeForAsset($assetId,$reason);
        $cdn=DeliveryService::purgePublicForAsset($assetId,$reason);if((int)$cdn['pending']>0)throw new Error('cdn_purge_pending','CDN purge remains pending before derivative expiry.',503,['pending'=>(int)$cdn['pending']]);
        $asset=self::authorizeCurrent($assetId,0,$reason,['scope'=>'derivatives','phase'=>'delete_derivatives'],'expire-derivatives');ResidencyCryptoService::assertUnlocked($assetId,'expire_derivatives');
        $deleted=[];foreach(DerivativeService::forAsset($assetId) as $d){if(($d['status']??'')==='deleted')continue;$provider=ProviderRegistry::get((string)($d['storage']['provider_id']??''));$key=(string)$d['object_key'];if($provider->exists($key)&&!$provider->delete($key)&&$provider->exists($key))throw new Error('derivative_delete_failed','Derivative provider deletion failed.',503,['derivative_id'=>$d['id']]);$d['status']='deleted';$d['deleted_at']=Utils::now();RecordStore::put('derivative',(string)$d['id'],$d,(int)$d['version']);$deleted[]=$d['id'];}
        if(!empty($asset['active_manifest_id'])){$m=RecordStore::get('manifest',(string)$asset['active_manifest_id']);if($m){$m['status']='expired';$m['expired_at']=Utils::now();RecordStore::put('manifest',(string)$m['id'],$m,(int)$m['version']);}}
        $freshAsset=RecordStore::get('asset',$assetId)??$asset;$freshAsset['active_manifest_id']=null;$freshAsset['processing_status']='retained_source';$freshAsset['status']='quarantined';$freshAsset['derivatives_expired_at']=Utils::now();$freshAsset=RecordStore::put('asset',$assetId,$freshAsset,(int)$freshAsset['version']);Audit::record('derivatives_expired',['asset_id'=>$assetId,'reason'=>$reason,'count'=>count($deleted),'cdn_purged'=>(int)$cdn['purged']]);if(function_exists('do_action'))do_action('scm.media.revoked',['asset_id'=>$assetId,'owner_domain'=>$freshAsset['owner_domain'],'owner_object'=>$freshAsset['owner_object'],'object_version'=>$freshAsset['object_version'],'reason'=>$reason]);return ['asset_id'=>$assetId,'deleted_derivatives'=>$deleted,'cdn_mappings'=>(int)$cdn['purged']];
    }
    public static function process(string $deletionId): array {
        $d=RecordStore::get('deletion',$deletionId);if(!$d)throw new Error('deletion_not_found','Deletion request not found.',404);$d=self::stored($d,$deletionId);if($d['status']==='completed'){self::completedEvidence($d);self::auditCompleted($d);self::notifyCompleted($d,RecordStore::get('asset',$d['asset_id']));return $d;}if($d['status']==='cancelled')throw new Error('deletion_cancelled','Deletion was cancelled.',409);if($d['next_attempt_at']>Utils::now())throw new Error('deletion_retry_pending','Deletion retry is not due.',409);
        $terminalAsset=RecordStore::get('asset',$d['asset_id']);
        if(is_array($terminalAsset)&&($terminalAsset['status']??null)==='deleted')return self::recoverDeletedAsset($d,$terminalAsset);
        $asset=self::authorizeCurrent((string)$d['asset_id'],(int)($d['actor_id']??0),(string)($d['reason']??'deletion'),['deletion_id'=>$deletionId,'phase'=>'process-start'],'delete');if($d['attempts']===PHP_INT_MAX)throw new Error('deletion_record_invalid','Deletion attempt counter exhausted.',500);$d['attempts']++;$d=self::save($d);
        try{
            if($d['steps']['revoke_grants']!=='complete'){$asset=self::authorizeCurrent((string)$d['asset_id'],(int)($d['actor_id']??0),(string)$d['reason'],['deletion_id'=>$deletionId,'phase'=>'revoke_grants'],'delete');$d['revoked_grants']=DeliveryService::revokeForAsset((string)$asset['asset_id'],'deletion');$d['steps']['revoke_grants']='complete';$d['status']='pending_cdn';$d=self::save($d);}
            if($d['steps']['purge_cdn']!=='complete'){$asset=self::authorizeCurrent((string)$d['asset_id'],(int)($d['actor_id']??0),(string)$d['reason'],['deletion_id'=>$deletionId,'phase'=>'purge_cdn'],'delete');$purge=DeliveryService::purgePublicForAsset((string)$asset['asset_id'],'deletion');$d['cdn_purge_evidence']=$purge;if((int)$purge['pending']>0)throw new Error('cdn_purge_pending','CDN purge not fully confirmed; deletion remains pending.',503,['pending'=>(int)$purge['pending']]);$d['steps']['purge_cdn']='complete';$d['status']='pending_derivatives';$d=self::save($d);}
            if($d['steps']['delete_derivatives']!=='complete'){$asset=self::authorizeCurrent((string)$d['asset_id'],(int)($d['actor_id']??0),(string)$d['reason'],['deletion_id'=>$deletionId,'phase'=>'delete_derivatives'],'delete');ResidencyCryptoService::assertUnlocked((string)$asset['asset_id'],'delete_derivatives');$deleted=[];foreach(DerivativeService::forAsset($asset['asset_id']) as $derivative){if(($derivative['status']??'')==='deleted')continue;$provider=ProviderRegistry::get((string)($derivative['storage']['provider_id']??''));$key=(string)$derivative['object_key'];if($provider->exists($key)&&!$provider->delete($key)&&$provider->exists($key))throw new Error('derivative_delete_failed','Derivative provider deletion failed.',503,['derivative_id'=>$derivative['id']]);$derivative['status']='deleted';$derivative['deleted_at']=Utils::now();RecordStore::put('derivative',(string)$derivative['id'],$derivative,(int)$derivative['version']);$deleted[]=$derivative['id'];}$d['deleted_derivatives']=$deleted;$d['steps']['delete_derivatives']='complete';$d['status']='pending_source';$d=self::save($d);}
            if($d['steps']['delete_source']!=='complete'){$asset=self::authorizeCurrent((string)$d['asset_id'],(int)($d['actor_id']??0),(string)$d['reason'],['deletion_id'=>$deletionId,'phase'=>'delete_source'],'delete');ResidencyCryptoService::assertUnlocked((string)$asset['asset_id'],'delete_source');$provider=ProviderRegistry::get((string)($asset['storage']['provider_id']??''));$key=(string)$asset['object_key'];if($provider->exists($key)&&!$provider->delete($key)&&$provider->exists($key))throw new Error('source_delete_failed','Source provider deletion failed.',503);$d['steps']['delete_source']='complete';$d['status']='pending_mappings';$d=self::save($d);}
            if($d['steps']['delete_mappings']!=='complete'){$asset=self::authorizeCurrent((string)$d['asset_id'],(int)($d['actor_id']??0),(string)$d['reason'],['deletion_id'=>$deletionId,'phase'=>'delete_mappings'],'delete');foreach(RecordStore::all('provider_mapping',0,null,100000) as $m)if(($m['asset_id']??'')===$asset['asset_id']){RecordStore::delete('provider_mapping',(string)$m['id'],(int)$m['version']);}$d['steps']['delete_mappings']='complete';$d['status']='pending_backup_ledger';$d=self::save($d);}
            if($d['steps']['backup_ledger']!=='complete'){
                $asset=self::authorizeCurrent($d['asset_id'],$d['actor_id'],$d['reason'],['deletion_id'=>$deletionId,'phase'=>'backup_ledger'],'delete');
                $ledgerId=hash('sha256',$d['id'].'|backup-expiry');
                $existingLedger=RecordStore::get('backup_expiry',$ledgerId);
                if($existingLedger!==null&&(
                    ($existingLedger['asset_id']??null)!==$d['asset_id']
                    ||($existingLedger['deletion_id']??null)!==$d['id']
                    ||($existingLedger['status']??null)!=='awaiting_backup_expiry'
                    ||($existingLedger['backup_expiry_at']??null)!==$d['backup_expiry_at']))
                    throw new Error('deletion_evidence_invalid','Existing backup ledger conflicts with deletion.',500);
                $ledger=['actor_id'=>0,'asset_id'=>$asset['asset_id'],'deletion_id'=>$d['id'],
                    'status'=>'awaiting_backup_expiry','backup_expiry_at'=>$d['backup_expiry_at'],'created_at'=>Utils::now()];
                RecordStore::put('backup_expiry',$ledgerId,$ledger,$existingLedger?(int)$existingLedger['version']:0);
                $d['steps']['backup_ledger']='complete';$d['status']='pending_tombstone';$d=self::save($d);
            }
            if($d['steps']['tombstone']!=='complete'){
                $asset=self::authorizeCurrent($d['asset_id'],$d['actor_id'],$d['reason'],['deletion_id'=>$deletionId,'phase'=>'tombstone'],'delete');
                $existingTombstone=RecordStore::get('tombstone',$asset['asset_id']);
                if($existingTombstone!==null&&(
                    !self::tombstoneMatches($existingTombstone,$asset,$d)
                    ||($existingTombstone['deletion_id']??null)!==$d['id']))
                    throw new Error('deletion_evidence_invalid','Existing tombstone conflicts with deletion.',500);
                $deletedAt=Utils::now();
                RecordStore::put('tombstone',$asset['asset_id'],[
                    'actor_id'=>$d['actor_id'],'asset_id'=>$asset['asset_id'],'deletion_id'=>$d['id'],
                    'owner_domain'=>$asset['owner_domain'],
                    'owner_object'=>$asset['owner_object'],'object_version'=>$asset['object_version'],
                    'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
                    'reason'=>$d['reason'],'status'=>'deleted','deleted_at'=>$deletedAt,
                    'backup_expiry_at'=>$d['backup_expiry_at'],
                ],$existingTombstone?(int)$existingTombstone['version']:0);
                $asset['status']='deleted';$asset['deleted_at']=$deletedAt;$asset['deletion_id']=$d['id'];
                unset($asset['object_key'],$asset['storage']);
                $asset=RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);
                $d['steps']['tombstone']='complete';$d['status']='completed';$d['completed_at']=Utils::now();$d=self::save($d);
                self::completedEvidence($d);
                self::auditCompleted($d);
                self::notifyCompleted($d,$asset);
            }
            return $d;
        }catch(\Throwable $e){
            $fresh=RecordStore::get('deletion',$deletionId);
            if($fresh){
                $d=self::stored($fresh,$deletionId);
                if($d['status']==='completed'){
                    try{DegradedStateService::record('deletion-post-completion','audit_or_hook_failed',[
                        'deletion_ref'=>Utils::hashReference($deletionId),
                    ]);}catch(\Throwable){}
                    throw $e;
                }
            }
            $d['attempts']=max($d['attempts'],1);
            $d['status']='pending_retry';$d['last_error']=$e instanceof Error?$e->errorCode:'unexpected';
            $d['next_attempt_at']=Utils::now()+min(3600,2**min(8,$d['attempts'])*15);
            $d=self::save($d);
            Audit::record('deletion_pending_retry',['deletion_id'=>$d['id'],'asset_id'=>$d['asset_id'],
                'error'=>$d['last_error'],'next_attempt_at'=>$d['next_attempt_at']]);
            throw $e;
        }
    }
    private static function authorizeCurrent(string $assetId,int $actor,string $reason,array $context,string $holdOperation='delete'): array {$asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);LegalHoldService::assertNoHold($assetId,$holdOperation);$decision=DomainRegistry::decision($asset['owner_domain'],'authorize_deletion',['asset'=>$asset,'actor_id'=>$actor,'reason'=>$reason,'context'=>Utils::redact($context)]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner deletion authorization is stale.',409);return $asset;}
    private static function save(array $d): array {return RecordStore::put('deletion',(string)$d['id'],$d,(int)$d['version']);}
    public static function reconcile(): array {
        $result=['completed'=>0,'pending'=>0,'failed'=>0];
        foreach(RecordStore::all('deletion',0,null,100000) as $raw){
            try{
                $d=self::stored($raw);
                if($d['status']==='completed'){self::completedEvidence($d);self::auditCompleted($d);self::notifyCompleted($d,RecordStore::get('asset',$d['asset_id']));$result['completed']++;continue;}
                if($d['status']==='cancelled'||$d['next_attempt_at']>Utils::now()){$result['pending']++;continue;}
                $processed=self::process($d['id']);
                $processed=self::stored($processed,$d['id']);
                if($processed['status']!=='completed')throw new Error('deletion_incomplete','Deletion did not reach completion.',500);
                self::completedEvidence($processed);$result['completed']++;
            }catch(\Throwable){$result['failed']++;}
        }
        return $result;
    }
}
