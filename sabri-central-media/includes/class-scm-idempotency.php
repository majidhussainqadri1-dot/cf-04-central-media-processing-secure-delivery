<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Idempotency {
    private const CLAIM_TTL=900;
    private const RESULT_TTL=86400;
    public static function claim(string $scope,string $key,string $fingerprint): array {
        $scope=Utils::key($scope,48);
        $key=trim($key);
        if($scope==='' || $key==='') throw new Error('idempotency_key_required','Idempotency key required.',400);
        if(preg_match('/^[a-f0-9]{64}$/',$fingerprint)!==1) throw new Error('idempotency_fingerprint_invalid','Idempotency fingerprint is invalid.',500);
        $id=$scope.':'.hash('sha256',$key);
        $now=Utils::now();
        $old=RecordStore::get('idempotency',$id);
        if($old && (int)($old['expires_at']??0)<=$now){ RecordStore::delete('idempotency',$id); $old=null; }
        if($old){
            if(!hash_equals((string)($old['fingerprint']??''),$fingerprint)) throw new Error('idempotency_conflict','Idempotency key was reused for a different request.',409);
            if(($old['status']??'')==='completed') return ['replay'=>true,'record'=>$old];
            if(($old['status']??'')==='claimed') throw new Error('idempotency_in_progress','An identical request is already in progress.',409,['retry_after'=>max(1,(int)$old['expires_at']-$now)]);
            if(($old['status']??'')==='failed'){
                $old['status']='claimed';
                $old['claimed_at']=$now;
                $old['expires_at']=$now+self::CLAIM_TTL;
                unset($old['failure_code'],$old['failed_at']);
                $old=RecordStore::put('idempotency',$id,$old,(int)$old['version']);
                return ['replay'=>false,'record'=>$old];
            }
            throw new Error('idempotency_state_invalid','Idempotency record is in an invalid state.',500);
        }
        try {
            $record=RecordStore::put('idempotency',$id,['actor_id'=>Auth::currentUser(),'status'=>'claimed','scope'=>$scope,'fingerprint'=>$fingerprint,'claimed_at'=>$now,'expires_at'=>$now+self::CLAIM_TTL]);
            return ['replay'=>false,'record'=>$record];
        } catch(Error $e){
            if($e->errorCode!=='record_version_conflict') throw $e;
            $winner=RecordStore::get('idempotency',$id);
            if(!$winner || !hash_equals((string)($winner['fingerprint']??''),$fingerprint)) throw new Error('idempotency_conflict','Idempotency key was claimed by another request.',409);
            if(($winner['status']??'')==='completed') return ['replay'=>true,'record'=>$winner];
            throw new Error('idempotency_in_progress','An identical request is already in progress.',409,['retry_after'=>max(1,(int)($winner['expires_at']??$now+1)-$now)]);
        }
    }
    public static function complete(string $scope,string $key,string $fingerprint,string $resultType,string $resultId): array {
        $id=self::id($scope,$key);
        $record=RecordStore::get('idempotency',$id);
        if(!$record || !hash_equals((string)($record['fingerprint']??''),$fingerprint)) throw new Error('idempotency_completion_invalid','Idempotency completion does not match the active claim.',409);
        if(($record['status']??'')==='completed') return $record;
        if(($record['status']??'')!=='claimed') throw new Error('idempotency_completion_invalid','Idempotency claim is not active.',409);
        $record['status']='completed';
        $record['result_type']=Utils::key($resultType,48);
        $record['result_id']=Utils::text($resultId,96);
        $record['completed_at']=Utils::now();
        $record['expires_at']=Utils::now()+self::RESULT_TTL;
        return RecordStore::put('idempotency',$id,$record,(int)$record['version']);
    }
    public static function fail(string $scope,string $key,string $fingerprint,string $failureCode): void {
        if(trim($key)==='') return;
        $id=self::id($scope,$key);
        $record=RecordStore::get('idempotency',$id);
        if(!$record || !hash_equals((string)($record['fingerprint']??''),$fingerprint) || ($record['status']??'')!=='claimed') return;
        $record['status']='failed';
        $record['failure_code']=Utils::key($failureCode,64);
        $record['failed_at']=Utils::now();
        $record['expires_at']=Utils::now()+300;
        RecordStore::put('idempotency',$id,$record,(int)$record['version']);
    }
    private static function id(string $scope,string $key): string {
        $scope=Utils::key($scope,48);
        $key=trim($key);
        if($scope==='' || $key==='') throw new Error('idempotency_key_required','Idempotency key required.',400);
        return $scope.':'.hash('sha256',$key);
    }
}
