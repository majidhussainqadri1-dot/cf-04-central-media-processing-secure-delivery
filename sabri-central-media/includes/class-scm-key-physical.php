<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/**
 * Bounded, strictly read-only physical ciphertext audit of staged stale-key
 * candidates. This is not a group lease, re-encryption or key retirement.
 */
final class KeyRotationPhysicalPreflight {
    public static function inspectSegment(string $inventoryId,string $segmentId,
        int $actor,int $offset=0,int $limit=25): array {
        Auth::capability('media_manage_providers');
        Auth::assertActor($actor,'manage_options');
        if($limit<1||$limit>25||$offset<0||$offset>250)
            throw new Error('key_rotation_physical_limit_invalid',
                'Physical rekey audit supports offsets 0–250 and 1–25 items.',400);
        $complete=KeyRotationInventoryStage::verifyComplete($inventoryId,$actor);
        $stage=RecordStore::get('key_rotation_inventory',$inventoryId);
        $segment=RecordStore::get('key_rotation_inventory_segment',$segmentId);
        if(!$stage||!$segment||
           ($segment['inventory_id']??null)!==$inventoryId||
           ($segment['status']??null)!=='candidate_unfenced'||
           !in_array(($segment['record_family']??null),['asset','derivative'],true)||
           !is_array($segment['items']??null)||
           !array_is_list($segment['items'])||
           count($segment['items'])>250)
            throw new Error('key_rotation_physical_segment_invalid',
                'Staged candidate segment is missing or not canonical.',409);
        $candidates=$segment['items'];
        if($offset>count($candidates))
            throw new Error('key_rotation_physical_limit_invalid',
                'Physical audit offset exceeds the candidate count.',400);
        $active=Keyring::activeId();
        if(($stage['active_key_id']??null)!==$active)
            throw new Error('key_rotation_physical_epoch_changed',
                'Active encryption key changed since the candidate inventory.',409);
        $chain=str_repeat('0',64);$checked=0;$bytes=0;
        $stop=min(count($candidates),$offset+$limit);
        for($i=$offset;$i<$stop;$i++){
            $candidate=$candidates[$i];
            $type=$candidate['type']??null;
            $id=$candidate['id']??null;
            if($type!==$segment['record_family']||
               !is_string($id)||$id===''||strlen($id)>96||
               ($candidate['status']??null)!=='candidate_unfenced')
                throw new Error('key_rotation_physical_identity_invalid',
                    'Staged key-rotation candidate identity is invalid.',409);
            $record=RecordStore::get($type,$id);
            if(!$record||($record['status']??null)==='deleted')
                throw new Error('key_rotation_physical_mapping_stale',
                    'Stale-key candidate is no longer an active media object.',409);
            $objectKey=$candidate['object_key']??null;
            $sha=$candidate['sha256']??null;
            $providerId=$candidate['provider_id']??null;
            $oldKey=$candidate['old_key_id']??null;
            if(!is_string($objectKey)||!preg_match('/^[a-f0-9]{64}$/D',$objectKey)||
               !is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||
               !is_string($providerId)||$providerId===''||
               Utils::key($providerId,64)!==$providerId||
               !is_string($oldKey)||$oldKey===''||
               Utils::key($oldKey,64)!==$oldKey||$oldKey===$active)
                throw new Error('key_rotation_physical_identity_invalid',
                    'Physical rotation reference is noncanonical.',409);
            $size=Utils::integer($candidate['size']??null,
                'key_rotation_physical_identity_invalid',0,1073741824);
            if(($record['object_key']??null)!==$objectKey||
               ($record['storage']['provider_id']??null)!==$providerId||
               ($record['storage']['key_id']??null)!==$oldKey||
               ($record['sha256']??null)!==$sha||
               ($record['size']??null)!==$size)
                throw new Error('key_rotation_physical_mapping_stale',
                    'Current media metadata differs from the staged key identity.',409);
            $store=ProviderRegistry::get($providerId);
            if(!($store instanceof ObjectCipherKeyInspector))
                throw new Error('key_rotation_cipher_inspector_unavailable',
                    'Provider cannot attest the physical encryption key.',503);
            $physicalKey=$store->objectEncryptionKeyId($objectKey);
            if(!hash_equals($oldKey,$physicalKey))
                throw new Error('key_rotation_physical_key_mismatch',
                    'Actual ciphertext key differs from the recorded old key.',409);
            $stream=$store->openStream($objectKey);
            try{$actual=Utils::streamHash($stream,1073741824);}
            finally{fclose($stream);}
            if($actual['size']!==$size||!hash_equals($sha,$actual['sha256']))
                throw new Error('key_rotation_physical_content_mismatch',
                    'Physical ciphertext decrypts to unexpected content.',409);
            if($bytes>PHP_INT_MAX-$size)
                throw new Error('key_rotation_physical_counter_overflow',
                    'Physical rekey audit byte count overflowed.',409);
            $bytes+=$size;$checked++;
            $chain=hash('sha256',$chain.'|'.$type.'|'.$id.'|'.
                $providerId.'|'.hash('sha256',$objectKey).'|'.$oldKey.'|'.$sha);
        }
        return ['inventory_id'=>$inventoryId,'segment_id'=>$segmentId,
            'status'=>$stop===count($candidates)?
                'segment_physically_verified_unfenced':'partial_unfenced',
            'next_index'=>$stop,'checked'=>$checked,'bytes_verified'=>$bytes,
            'source_chain_hash'=>$chain,'candidate_total'=>count($candidates),
            'inventory_chain_hash'=>$complete['chain_hash'],
            'keys_rotated'=>0,'authority_to_retire_old_key'=>false];
    }
}
