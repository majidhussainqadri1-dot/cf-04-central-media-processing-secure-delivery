<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/**
 * Read-only physical source audit of a restored inventory checkpoint.
 * Deliberately unfenced: not an immutable snapshot, restore executor or serve gate.
 */
final class RestorePhysicalAuditStage {
    private const PAGE=10;
    private const TYPES=['asset','derivative'];

    private static function actor(int $actor): void {
        Auth::capability('media_manage_providers');
        Auth::assertActor($actor,'manage_options');
    }

    public static function begin(string $checkpointId,int $actor): array {
        self::actor($actor);
        $snapshot=RestoreCheckpointStage::verifyComplete($checkpointId,$actor);
        $id=Utils::id('rphysical');
        return RecordStore::put('restore_physical_audit',$id,[
            'actor_id'=>$actor,'checkpoint_id'=>$checkpointId,
            'checkpoint_chain'=>$snapshot['chain_hash'],
            'status'=>'scanning_unfenced','family'=>'asset','after_id'=>'',
            'page_size'=>self::PAGE,'segments'=>0,'objects'=>0,
            'bytes_verified'=>0,'chain_hash'=>str_repeat('0',64),
            'created_at'=>Utils::now()
        ],0);
    }

    private static function load(string $id,int $actor): array {
        self::actor($actor);
        $state=RecordStore::get('restore_physical_audit',$id);
        if(!$state)throw new Error('restore_physical_not_found',
            'Restore physical audit not found.',404);
        if(($state['actor_id']??null)!==$actor||
           !is_string($state['checkpoint_id']??null)||
           !is_string($state['checkpoint_chain']??null)||
           !preg_match('/^[a-f0-9]{64}$/D',$state['checkpoint_chain'])||
           ($state['page_size']??null)!==self::PAGE||
           !is_int($state['segments']??null)||$state['segments']<0||
           !is_int($state['objects']??null)||$state['objects']<0||
           !is_int($state['bytes_verified']??null)||$state['bytes_verified']<0||
           !is_string($state['chain_hash']??null)||
           !preg_match('/^[a-f0-9]{64}$/D',$state['chain_hash']))
            throw new Error('restore_physical_state_invalid',
                'Physical audit state is not canonical.',409);
        return $state;
    }

    /**
     * Read and hash each real encrypted source, rather than trusting
     * metadata or a previous checkpoint alone. Skip deleted references.
     */
    private static function scanBytes(array $page,string $family): array {
        $witness=[];$bytes=0;
        foreach($page as $item){
            $id=$item['id']??null;
            if(!is_string($id)||$id===''||strlen($id)>96)
                throw new Error('restore_physical_identity_invalid','Invalid media record identity.',409);
            if(($item['status']??null)==='deleted')continue;
            $key=$item['object_key']??null;
            if($key===null||$key===''){
                if(($family==='asset'&&($item['status']??null)==='ready')||
                   ($family==='derivative'&&($item['status']??null)==='validated'))
                    throw new Error('restore_physical_reference_missing',
                        'Ready media lacks its physical object reference.',409);
                continue;
            }
            $provider=$item['storage']['provider_id']??null;
            $hash=$item['sha256']??null;
            if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)||
               !is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||
               !is_string($provider)||$provider===''||
               Utils::key($provider,64)!==$provider)
                throw new Error('restore_physical_identity_invalid',
                    'Media object identity or provider is malformed.',409);
            $size=Utils::integer($item['size']??null,
                'restore_physical_identity_invalid',0,1073741824);
            $stream=ProviderRegistry::get($provider)->openStream($key);
            try{$observed=Utils::streamHash($stream,1073741824);}
            finally{fclose($stream);}
            if($observed['size']!==$size||!hash_equals($observed['sha256'],$hash))
                throw new Error('restore_physical_integrity_failed',
                    'Restored source bytes differ from the authoritative hash.',409);
            if($bytes>PHP_INT_MAX-$size)
                throw new Error('restore_physical_counter_overflow','Verified bytes overflow.',409);
            $bytes+=$size;
            $witness[]=['id'=>$id,'provider'=>$provider,
                'key_hash'=>hash('sha256',$key),'sha256'=>$hash,'size'=>$size];
        }
        return ['count'=>count($witness),'bytes'=>$bytes,
            'hash'=>hash('sha256',Utils::canonicalJson($witness))];
    }

    private static function pageRecord(string $auditId,string $family,string $after,
        array $page,array $proof,bool $allowCreate): array {
        $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
        $id=hash('sha256',$auditId.'|'.$family.'|'.$after);
        $pageHash=hash('sha256',Utils::canonicalJson($page));
        $saved=RecordStore::get('restore_physical_segment',$id);
        if(!$saved){
            if(!$allowCreate)throw new Error('restore_physical_journal_missing',
                'A physical page checkpoint is missing.',409);
            $saved=RecordStore::put('restore_physical_segment',$id,[
                'actor_id'=>0,'audit_id'=>$auditId,'family'=>$family,
                'after_id'=>$after,'last_id'=>$last,
                'page_hash'=>$pageHash,'source_hash'=>$proof['hash'],
                'objects'=>$proof['count'],'bytes_verified'=>$proof['bytes'],
                'row_count'=>count($page),'status'=>'source_verified_unfenced',
                'created_at'=>Utils::now()
            ],0);
        }
        if(($saved['audit_id']??null)!==$auditId||
           ($saved['family']??null)!==$family||
           ($saved['after_id']??null)!==$after||
           ($saved['last_id']??null)!==$last||
           ($saved['page_hash']??null)!==$pageHash||
           ($saved['source_hash']??null)!==$proof['hash']||
           ($saved['objects']??null)!==$proof['count']||
           ($saved['bytes_verified']??null)!==$proof['bytes']||
           ($saved['row_count']??null)!==count($page)||
           ($saved['status']??null)!=='source_verified_unfenced')
            throw new Error('restore_physical_journal_drift',
                'Saved physical evidence differs from the current sources.',409);
        return ['id'=>$id,'last'=>$last,'page_hash'=>$pageHash,
            'source_hash'=>$proof['hash'],'count'=>$proof['count'],'bytes'=>$proof['bytes']];
    }

    /** Exactly one 10-row keyset page; resumable and CAS-protected. */
    public static function advance(string $id,int $actor): array {
        $state=self::load($id,$actor);
        if(($state['status']??null)==='physical_verified_unfenced'){
            self::verifyComplete($id,$actor);
            return $state;
        }
        $family=$state['family']??null;$after=$state['after_id']??null;
        if(($state['status']??null)!=='scanning_unfenced'||
           !in_array($family,self::TYPES,true)||
           !is_string($after)||strlen($after)>96||Utils::text($after,96)!==$after)
            throw new Error('restore_physical_cursor_invalid','Physical cursor is invalid.',409);
        $page=RecordStore::scan($family,self::PAGE,$after);
        $proof=self::scanBytes($page,$family);
        $row=self::pageRecord($id,$family,$after,$page,$proof,true);
        if($state['segments']===PHP_INT_MAX||
           $state['bytes_verified']>PHP_INT_MAX-$row['bytes']||
           $state['objects']>PHP_INT_MAX-$row['count'])
            throw new Error('restore_physical_counter_overflow','Audit counters overflow.',409);
        $state['segments']++;
        $state['objects']+=$row['count'];
        $state['bytes_verified']+=$row['bytes'];
        $state['chain_hash']=hash('sha256',$state['chain_hash'].'|'.
            $row['id'].'|'.$row['page_hash'].'|'.$row['source_hash']);
        if(count($page)<self::PAGE){
            $state['family']=$family==='asset'?'derivative':'complete';
            $state['after_id']='';
            if($family==='derivative')$state['status']='physical_verified_unfenced';
        }else{$state['after_id']=$row['last'];}
        $state['updated_at']=Utils::now();
        return RecordStore::put('restore_physical_audit',$id,$state,(int)$state['version']);
    }

    /** Verify every physical byte again; still no serving authorization. */
    public static function verifyComplete(string $id,int $actor): array {
        $state=self::load($id,$actor);
        if(($state['status']??null)!=='physical_verified_unfenced'||
           ($state['family']??null)!=='complete'||($state['after_id']??null)!=='')
            throw new Error('restore_physical_incomplete','Physical audit is incomplete.',409);
        $checkpoint=RestoreCheckpointStage::verifyComplete(
            (string)$state['checkpoint_id'],$actor);
        if(!hash_equals((string)$state['checkpoint_chain'],(string)$checkpoint['chain_hash']))
            throw new Error('restore_physical_checkpoint_drift',
                'Database checkpoint differs from physical inventory.',409);
        $chain=str_repeat('0',64);$count=0;$bytes=0;$segments=0;
        foreach(self::TYPES as $family){
            $after='';
            do{
                $page=RecordStore::scan($family,self::PAGE,$after);
                $proof=self::scanBytes($page,$family);
                $row=self::pageRecord($id,$family,$after,$page,$proof,false);
                if($bytes>PHP_INT_MAX-$row['bytes'])
                    throw new Error('restore_physical_counter_overflow','Audit counters overflow.',409);
                $chain=hash('sha256',$chain.'|'.$row['id'].'|'.
                    $row['page_hash'].'|'.$row['source_hash']);
                $count+=$row['count'];$bytes+=$row['bytes'];$segments++;
                if(count($page)<self::PAGE)break;
                $after=$row['last'];
            }while(true);
        }
        if(($state['chain_hash']??null)!==$chain||
           ($state['objects']??null)!==$count||
           ($state['bytes_verified']??null)!==$bytes||
           ($state['segments']??null)!==$segments)
            throw new Error('restore_physical_journal_drift',
                'Complete source proof chain no longer matches the checkpoint.',409);
        return ['audit_id'=>$id,'status'=>'physical_verified_unfenced',
            'objects'=>$count,'bytes_verified'=>$bytes,'segments'=>$segments,
            'chain_hash'=>$chain,'delivery_authorized'=>false];
    }
}
