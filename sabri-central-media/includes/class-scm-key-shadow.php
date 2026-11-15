<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/**
 * Staged physical shadow re-encryption, strictly before native owner remapping.
 * Does NOT provide write fencing, cutover, old-key deletion or KMS retirement.
 */
final class KeyRotationShadowStage {
    private static function loaded(string $inventoryId,int $actor): array {
        Auth::capability('media_manage_providers');
        Auth::assertActor($actor,'manage_options');
        KeyRotationInventoryStage::verifyComplete($inventoryId,$actor);
        $stage=RecordStore::get('key_rotation_inventory',$inventoryId);
        if(!$stage||($stage['status']??null)!=='inventory_complete_unfenced'||
           ($stage['active_key_id']??null)!==Keyring::activeId())
            throw new Error('key_shadow_inventory_invalid',
                'Only a stable, completed key inventory may be shadow-reencrypted.',409);
        return $stage;
    }

    private static function segment(string $inventoryId,string $segmentId): array {
        $seg=RecordStore::get('key_rotation_inventory_segment',$segmentId);
        if(!$seg||($seg['inventory_id']??null)!==$inventoryId||
           ($seg['status']??null)!=='candidate_unfenced'||
           !in_array(($seg['record_family']??null),['asset','derivative'],true)||
           !is_array($seg['items']??null)||!array_is_list($seg['items'])||
           count($seg['items'])>250)
            throw new Error('key_shadow_segment_invalid',
                'Key-rotation candidate segment is missing or malformed.',409);
        return $seg;
    }

    private static function candidate(array $item,array $stage,string $family): array {
        $type=$item['type']??null;$id=$item['id']??null;
        $provider=$item['provider_id']??null;$sourceKey=$item['object_key']??null;
        $sha=$item['sha256']??null;$oldKeyId=$item['old_key_id']??null;
        if($type!==$family||!is_string($id)||$id===''||strlen($id)>96||
           ($item['status']??null)!=='candidate_unfenced'||
           !is_string($provider)||$provider===''||Utils::key($provider,64)!==$provider||
           !is_string($sourceKey)||!preg_match('/^[a-f0-9]{64}$/D',$sourceKey)||
           !is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||
           !is_string($oldKeyId)||$oldKeyId===''||
           Utils::key($oldKeyId,64)!==$oldKeyId||
           $oldKeyId===$stage['active_key_id'])
            throw new Error('key_shadow_identity_invalid',
                'Staged key-rotation identity is noncanonical.',409);
        $size=Utils::integer($item['size']??null,'key_shadow_identity_invalid',0,1073741824);
        $record=RecordStore::get($type,$id);
        if(!$record||($record['status']??null)==='deleted'||
           ($record['object_key']??null)!==$sourceKey||
           ($record['storage']['provider_id']??null)!==$provider||
           ($record['storage']['key_id']??null)!==$oldKeyId||
           ($record['sha256']??null)!==$sha||($record['size']??null)!==$size)
            throw new Error('key_shadow_mapping_stale',
                'Source mapping changed after staged key discovery.',409);
        $targetKey=hash('sha256','rekey|'.$sourceKey.'|'.
            $stage['active_key_id'].'|'.$sha);
        return ['type'=>$type,'id'=>$id,'provider'=>$provider,
            'source_key'=>$sourceKey,'target_key'=>$targetKey,
            'sha256'=>$sha,'size'=>$size,'old_key_id'=>$oldKeyId];
    }

    private static function cipherStore(string $providerId): ObjectCipherKeyInspector {
        $store=ProviderRegistry::get($providerId);
        if(!($store instanceof ObjectCipherKeyInspector))
            throw new Error('key_shadow_cipher_inspector_unavailable',
                'Provider cannot attest the ciphertext key epoch.',503);
        return $store;
    }

    private static function attest(string $providerId,string $objectKey,
        string $expectedKeyId,string $sha,int $size): void {
        $store=self::cipherStore($providerId);
        if(!hash_equals($expectedKeyId,$store->objectEncryptionKeyId($objectKey)))
            throw new Error('key_shadow_cipher_key_mismatch',
                'Physical ciphertext uses a different key epoch.',409);
        $stream=$store->openStream($objectKey);
        try{$actual=Utils::streamHash($stream,1073741824);}
        finally{fclose($stream);}
        if($actual['size']!==$size||!hash_equals($sha,$actual['sha256']))
            throw new Error('key_shadow_cipher_content_mismatch',
                'Physical ciphertext differs from the staged content identity.',409);
    }

    private static function journal(string $inventoryId,string $segmentId,int $actor,
        array $stage,array $seg): array {
        $id=hash('sha256','key-shadow|'.$inventoryId.'|'.$segmentId);
        $row=RecordStore::get('key_rotation_shadow_segment',$id);
        if(!$row){
            $row=RecordStore::put('key_rotation_shadow_segment',$id,[
                'actor_id'=>$actor,'inventory_id'=>$inventoryId,
                'segment_id'=>$segmentId,'active_key_id'=>$stage['active_key_id'],
                'inventory_chain_hash'=>$stage['chain_hash'],
                'status'=>'copying_unfenced','next_index'=>0,
                'copies'=>[],'created_at'=>Utils::now()
            ],0);
        }
        if(($row['actor_id']??null)!==$actor||
           ($row['inventory_id']??null)!==$inventoryId||
           ($row['segment_id']??null)!==$segmentId||
           ($row['active_key_id']??null)!==$stage['active_key_id']||
           ($row['inventory_chain_hash']??null)!==$stage['chain_hash']||
           !is_array($row['copies']??null)||!array_is_list($row['copies'])||
           !is_int($row['next_index']??null)||
           $row['next_index']<0||$row['next_index']>count($seg['items'])||
           $row['next_index']!==count($row['copies']))
            throw new Error('key_shadow_journal_invalid',
                'A key shadow checkpoint has malformed identity or cursor.',409);
        foreach($row['copies'] as $index=>$proof){
            $expected=self::candidate($seg['items'][$index],$stage,$seg['record_family']);
            if(!is_array($proof)||
               ($proof['type']??null)!==$expected['type']||
               ($proof['id']??null)!==$expected['id']||
               ($proof['provider_id']??null)!==$expected['provider']||
               ($proof['source_key_hash']??null)!==hash('sha256',$expected['source_key'])||
               ($proof['target_key']??null)!==$expected['target_key']||
               ($proof['sha256']??null)!==$expected['sha256']||
               ($proof['size']??null)!==$expected['size']||
               ($proof['status']??null)!=='shadow_copied_unfenced')
                throw new Error('key_shadow_journal_invalid',
                    'A saved shadow copy does not match the immutable staged candidate.',409);
        }
        if($row['copies']!==[]){
            $last=$row['next_index']-1;
            $item=self::candidate($seg['items'][$last],$stage,$seg['record_family']);
            self::attest($item['provider'],$item['target_key'],
                (string)$stage['active_key_id'],$item['sha256'],$item['size']);
        }
        return $row;
    }

    public static function copySegment(string $inventoryId,string $segmentId,
        int $actor,int $limit=10): array {
        if($limit<1||$limit>25)
            throw new Error('key_shadow_limit_invalid','Shadow batch size must be 1–25.',400);
        $stage=self::loaded($inventoryId,$actor);
        $seg=self::segment($inventoryId,$segmentId);
        $row=self::journal($inventoryId,$segmentId,$actor,$stage,$seg);
        if(($row['status']??null)==='copied_unfenced'){
            self::verifySegment($inventoryId,$segmentId,$actor);
            return $row;
        }
        if(($row['status']??null)!=='copying_unfenced')
            throw new Error('key_shadow_state_invalid','Shadow run state is invalid.',409);
        $from=$row['next_index'];
        $batch=min($limit,count($seg['items'])-$from);
        if($batch>0){
            KeyRotationPhysicalPreflight::inspectSegment(
                $inventoryId,$segmentId,$actor,$from,$batch);
        }
        for($i=$from;$i<$from+$batch;$i++){
            $item=self::candidate($seg['items'][$i],$stage,$seg['record_family']);
            if(Keyring::activeId()!==$stage['active_key_id'])
                throw new Error('key_shadow_epoch_changed','Active key rotated during shadow copying.',409);
            self::attest($item['provider'],$item['source_key'],
                $item['old_key_id'],$item['sha256'],$item['size']);
            $store=ProviderRegistry::get($item['provider']);
            $stream=$store->openStream($item['source_key']);
            try{
                $current=Utils::streamHash($stream,1073741824);
                if($current['size']!==$item['size']||
                   !hash_equals($current['sha256'],$item['sha256']))
                    throw new Error('key_shadow_source_changed',
                        'Source bytes changed between preflight and shadow re-encryption.',409);
                if(Keyring::activeId()!==$stage['active_key_id'])
                    throw new Error('key_shadow_epoch_changed',
                        'Active encryption epoch changed before the write.',409);
                $stored=$store->putStream($item['target_key'],$stream,[
                    'scope'=>'staged-key-shadow','source_hash'=>hash('sha256',$item['source_key'])
                ]);
            }finally{fclose($stream);}
            if(!is_array($stored)||
               ($stored['object_key']??null)!==$item['target_key']||
               ($stored['sha256']??null)!==$item['sha256']||
               ($stored['size']??null)!==$item['size']||
               ($stored['key_id']??null)!==$stage['active_key_id']||
               Keyring::activeId()!==$stage['active_key_id'])
                throw new Error('key_shadow_copy_invalid',
                    'Shadow provider result or key epoch does not match the plan.',409);
            self::attest($item['provider'],$item['target_key'],
                (string)$stage['active_key_id'],$item['sha256'],$item['size']);
            // Only a durable proof advances a cursor. No owner mapping changes.
            $row['copies'][]=['type'=>$item['type'],'id'=>$item['id'],
                'provider_id'=>$item['provider'],
                'source_key_hash'=>hash('sha256',$item['source_key']),
                'target_key'=>$item['target_key'],'sha256'=>$item['sha256'],
                'size'=>$item['size'],'status'=>'shadow_copied_unfenced'];
            $row['next_index']=$i+1;$row['updated_at']=Utils::now();
            $row=RecordStore::put('key_rotation_shadow_segment',$row['id'],
                $row,(int)$row['version']);
        }
        if($row['next_index']===count($seg['items'])){
            $row['status']='copied_unfenced';$row['completed_at']=Utils::now();
            $row=RecordStore::put('key_rotation_shadow_segment',$row['id'],
                $row,(int)$row['version']);
        }
        return $row;
    }

    /** Fresh full source/target rehash; never grants permission to remap aliases. */
    public static function verifySegment(string $inventoryId,string $segmentId,
        int $actor): array {
        $stage=self::loaded($inventoryId,$actor);
        $seg=self::segment($inventoryId,$segmentId);
        $id=hash('sha256','key-shadow|'.$inventoryId.'|'.$segmentId);
        $row=RecordStore::get('key_rotation_shadow_segment',$id);
        if(!$row||($row['status']??null)!=='copied_unfenced'||
           ($row['actor_id']??null)!==$actor||
           ($row['inventory_id']??null)!==$inventoryId||
           ($row['segment_id']??null)!==$segmentId||
           ($row['active_key_id']??null)!==$stage['active_key_id']||
           ($row['inventory_chain_hash']??null)!==$stage['chain_hash']||
           !is_array($row['copies']??null)||!array_is_list($row['copies'])||
           ($row['next_index']??null)!==count($seg['items'])||
           count($row['copies'])!==count($seg['items']))
            throw new Error('key_shadow_incomplete','Shadow segment proof is not complete.',409);
        foreach($seg['items'] as $index=>$candidate){
            $item=self::candidate($candidate,$stage,$seg['record_family']);
            $saved=$row['copies'][$index]??null;
            if(!is_array($saved)||
               ($saved['type']??null)!==$item['type']||
               ($saved['id']??null)!==$item['id']||
               ($saved['provider_id']??null)!==$item['provider']||
               ($saved['source_key_hash']??null)!==hash('sha256',$item['source_key'])||
               ($saved['target_key']??null)!==$item['target_key']||
               ($saved['sha256']??null)!==$item['sha256']||
               ($saved['size']??null)!==$item['size']||
               ($saved['status']??null)!=='shadow_copied_unfenced')
                throw new Error('key_shadow_journal_invalid','Shadow proof has been changed.',409);
            self::attest($item['provider'],$item['source_key'],
                $item['old_key_id'],$item['sha256'],$item['size']);
            self::attest($item['provider'],$item['target_key'],
                (string)$stage['active_key_id'],$item['sha256'],$item['size']);
        }
        return ['inventory_id'=>$inventoryId,'segment_id'=>$segmentId,
            'status'=>'shadow_reencrypted_unfenced',
            'verified_objects'=>count($seg['items']),
            'active_key_id'=>$stage['active_key_id'],
            'mappings_changed'=>0,'old_ciphertexts_deleted'=>0,
            'source_provider_credentials_revoked'=>false];
    }
}
