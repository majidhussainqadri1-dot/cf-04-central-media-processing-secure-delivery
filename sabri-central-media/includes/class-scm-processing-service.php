<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class ProcessingService {
    private static array $processors=[];
    public static function register(string $type,callable $processor): void { self::$processors[Utils::key($type,48)]=$processor; }
    public static function scanAndPromote(string $assetId): array { Utils::requireRuntime(); $asset=RecordStore::get('asset',$assetId); if(!$asset) throw new Error('asset_not_found','Asset not found.',404); if($asset['status']!=='quarantined') throw new Error('asset_scan_state_invalid','Asset is not quarantined.',409); $bytes=ProviderRegistry::store()->get((string)$asset['object_key']); $results=ScannerRegistry::scan($bytes,(array)$asset['required_scans'],['mime'=>$asset['mime'],'asset'=>$asset]); Auth::domainDecision((string)$asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'operation'=>'scan_promote']); $asset['scan_results']=$results;$asset['scan_status']='passed';$asset['status']='ready';$asset=RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);Audit::record('asset_scans_passed',['asset_id'=>$assetId,'scanners'=>array_keys($results)]);return Utils::redact($asset); }
    public static function execute(string $type,array $job): array { Utils::requireRuntime(); $k=Utils::key($type,48); if(!isset(self::$processors[$k])) throw new Error('processor_unavailable','Required processor unavailable.',503,['type'=>$k]); $result=(self::$processors[$k])($job); if(!is_array($result)||($result['ok']??false)!==true) throw new Error('processing_failed','Media processing failed.',500,['type'=>$k]); Audit::record('processing_completed',['type'=>$k,'asset_id'=>$job['asset_id']??'']); return Utils::redact($result); }
}
