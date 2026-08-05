<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class ProcessingService {
    private static array $processors=[];
    public static function register(string $type,callable $processor): void { $key=Utils::key($type,48);if($key==='')throw new Error('processor_id_invalid','Processor identifier is invalid.',500);self::$processors[$key]=$processor; }
    public static function scanAndPromote(string $assetId): array {
        Utils::requireRuntime();
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset) throw new Error('asset_not_found','Asset not found.',404);
        if(($asset['status']??'')!=='quarantined') throw new Error('asset_scan_state_invalid','Asset is not quarantined.',409);
        if(empty($asset['object_key'])||empty($asset['owner_domain'])||(int)($asset['object_version']??0)<1) throw new Error('asset_scan_record_incomplete','Asset scan record is incomplete.',500);
        try {
            $bytes=ProviderRegistry::store()->get((string)$asset['object_key']);
            if(isset($asset['size']) && (int)$asset['size']!==strlen($bytes)) throw new Error('asset_size_integrity_failed','Stored asset size does not match its record.',500);
            if(!empty($asset['content_sha256'])&&!hash_equals((string)$asset['content_sha256'],hash('sha256',$bytes))) throw new Error('asset_content_integrity_failed','Stored asset hash does not match its record.',500);
            $results=ScannerRegistry::scan($bytes,(array)$asset['required_scans'],['mime'=>$asset['mime'],'privacy_class'=>$asset['privacy_class']??'','asset'=>Utils::redact($asset)]);
            $decision=Auth::domainDecision((string)$asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'operation'=>'scan_promote']);
            if((int)$decision['object_version']!==(int)$asset['object_version']) throw new Error('domain_object_version_stale','Owning domain object changed before promotion.',409);
            $asset['scan_results']=$results;
            $asset['scan_status']='passed';
            $asset['status']='ready';
            $asset['scan_completed_at']=Utils::now();
            unset($asset['scan_failure_code']);
            $asset=RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);
            Audit::record('asset_scans_passed',['asset_id'=>$assetId,'scanners'=>array_keys($results)]);
            return Utils::redact($asset);
        } catch(Error $error){
            $current=RecordStore::get('asset',$assetId);
            if($current && ($current['status']??'')==='quarantined'){
                $current['scan_status']='failed';
                $current['scan_failure_code']=Utils::key($error->errorCode,64);
                $current['scan_failed_at']=Utils::now();
                try{RecordStore::put('asset',$assetId,$current,(int)$current['version']);}catch(Error){}
            }
            Audit::record('asset_scans_failed',['asset_id'=>$assetId,'failure_code'=>$error->errorCode]);
            throw $error;
        } catch(\Throwable $error){
            Audit::record('asset_scans_failed',['asset_id'=>$assetId,'failure_code'=>'unexpected']);
            throw new Error('asset_scan_failed','Asset scanning failed unexpectedly.',500);
        }
    }
    public static function execute(string $type,array $job): array {
        Utils::requireRuntime();
        $key=Utils::key($type,48);
        if(!isset(self::$processors[$key])) throw new Error('processor_unavailable','Required processor unavailable.',503,['type'=>$key]);
        try{$result=(self::$processors[$key])($job);}catch(Error $error){Audit::record('processing_failed',['type'=>$key,'asset_id'=>$job['asset_id']??'','failure_code'=>$error->errorCode]);throw $error;}catch(\Throwable $error){Audit::record('processing_failed',['type'=>$key,'asset_id'=>$job['asset_id']??'','failure_code'=>'unexpected']);throw new Error('processing_failed','Media processing failed.',500,['type'=>$key]);}
        if(!is_array($result)||($result['ok']??false)!==true) throw new Error('processing_failed','Media processing failed.',500,['type'=>$key]);
        Audit::record('processing_completed',['type'=>$key,'asset_id'=>$job['asset_id']??'']);
        return Utils::redact($result);
    }
    public static function reset(): void { self::$processors=[]; }
}
