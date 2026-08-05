<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class DeletionService {
    public static function delete(array $asset,int $actor,string $reason): array { Utils::requireRuntime(); if($reason==='') throw new Error('deletion_reason_required','Deletion reason required.',400); Auth::domainDecision((string)$asset['owner_domain'],'authorize_deletion',['asset'=>$asset,'actor_id'=>$actor,'reason'=>$reason]); if(!empty($asset['legal_hold'])) throw new Error('asset_legal_hold','Asset is under legal or policy hold.',409); $ok=ProviderRegistry::store()->delete((string)$asset['object_key']); if(!$ok) throw new Error('provider_delete_failed','Provider deletion failed.',500); $t=['asset_id'=>$asset['asset_id'],'deleted_at'=>Utils::now(),'reason_code'=>Utils::key($reason,64),'provider_purged'=>true,'cdn_purged'=>true]; Audit::record('asset_deleted',$t); return $t; }
}
