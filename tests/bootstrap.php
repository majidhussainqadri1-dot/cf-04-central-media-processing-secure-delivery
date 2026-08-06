<?php
declare(strict_types=1);
define('SCM_VERSION','1.2.0-rc.2');
define('SCM_SCHEMA_VERSION','1.4.0');
define('SCM_CONTRACT_VERSION','1.4.0');
define('SCM_TEST_MODE',true);
define('SCM_RUNTIME_ENABLED',true);
define('SCM_HASH_KEY',str_repeat('h',32));
define('SCM_PRIVATE_ROOT',sys_get_temp_dir().'/scm-full-tests-'.getmypid());
$GLOBALS['scm_filters']=[];$GLOBALS['scm_actions']=[];$GLOBALS['scm_user_id']=11;
if(!function_exists('add_filter')){function add_filter(string $tag,callable $callback): void {$GLOBALS['scm_filters'][$tag][]=$callback;}}
if(!function_exists('apply_filters')){function apply_filters(string $tag,mixed $value,mixed ...$args): mixed {foreach($GLOBALS['scm_filters'][$tag]??[] as $cb)$value=$cb($value,...$args);return $value;}}
if(!function_exists('do_action')){function do_action(string $tag,mixed ...$args): void {$GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];}}
if(!function_exists('get_current_user_id')){function get_current_user_id(): int {return (int)$GLOBALS['scm_user_id'];}}
if(!function_exists('current_user_can')){function current_user_can(string $cap): bool {return true;}}
if(!function_exists('__')){function __(string $text,string $domain=''): string {return $text;}}
if(!function_exists('esc_html__')){function esc_html__(string $text,string $domain=''): string {return $text;}}
if(!function_exists('esc_html')){function esc_html(string $text): string {return htmlspecialchars($text,ENT_QUOTES);}}
if(!function_exists('esc_attr__')){function esc_attr__(string $text,string $domain=''): string {return $text;}}

$base=dirname(__DIR__).'/sabri-central-media/includes/';
foreach(['class-scm-core.php','class-scm-persistence.php','class-scm-storage.php','class-scm-contracts.php','class-scm-upload.php','class-scm-validation.php','class-scm-processing.php','class-scm-delivery.php','class-scm-transfer.php','class-scm-lifecycle.php','class-scm-operations.php','class-scm-rest.php','class-scm-plugin.php'] as $file)require_once $base.$file;

use Sabri\CentralMedia\{Keyring,ProviderRegistry,LocalObjectStore,CdnRegistry,CdnAdapter,DomainRegistry,ScannerRegistry,RecordStore,Audit,Utils};
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),'test-v2'=>str_repeat('m',64)],'test-v1');
RecordStore::resetMemory();ProviderRegistry::reset();CdnRegistry::reset();DomainRegistry::reset();ScannerRegistry::reset();Audit::verifyChain();
ProviderRegistry::register('source-private',new LocalObjectStore(SCM_PRIVATE_ROOT.'/source'),['region'=>'test-a','class'=>'private','replication'=>'single']);ProviderRegistry::activate('source-private');
ProviderRegistry::register('target-private',new LocalObjectStore(SCM_PRIVATE_ROOT.'/target'),['region'=>'test-b','class'=>'private','replication'=>'single']);

final class TestCdn implements CdnAdapter {public array $published=[];public array $purged=[];public function publish(array $d): array {$this->published[]=$d;return ['published'=>true,'url'=>'https://cdn.invalid/'.rawurlencode($d['cache_key']),'version_key'=>$d['cache_key']];}public function purge(array $keys): array {$this->purged=array_merge($this->purged,$keys);return ['accepted'=>true,'purged'=>true,'keys'=>$keys,'purge_id'=>Utils::id('purge')];}public function health(): array{return ['healthy'=>true];}}
CdnRegistry::register('test-cdn',new TestCdn());

$ownerDecision=static function(array $context): array {$asset=$context['asset']??[];$transfer=$context['transfer']??[];return ['allowed'=>true,'authorized'=>true,'object_version'=>(int)($asset['object_version']??$context['object_version']??$transfer['native_transfer_version']??1),'hold_allowed'=>true,'source_retain_until'=>0,'derivative_retain_until'=>0,'backup_expiry_at'=>time()+86400,'relationship_allowed'=>true,'consent_valid'=>true,'copyright_valid'=>true,'confidentiality_allowed'=>true,'abuse_policy_allowed'=>true,'recipient_authorized'=>true,'active'=>true,'eligible'=>true];};
DomainRegistry::register('file00','1.0.0',['verify_user'=>static fn(array $c)=>['verified'=>true,'approved'=>true,'active'=>true,'eligible'=>true,'suspended'=>false,'rejected'=>false,'erasure_pending'=>false,'expired'=>false,'sanctioned'=>false,'assertion_version'=>3,'user_id'=>(int)$c['user_id']]]);
DomainRegistry::register('file17','1.0.0',[
 'authorize_upload'=>$ownerDecision,'authorize_processing'=>$ownerDecision,'authorize_delivery'=>$ownerDecision,'authorize_download'=>$ownerDecision,'authorize_deletion'=>$ownerDecision,'authorize_grant_revoke'=>$ownerDecision,'retention_decision'=>$ownerDecision,
 'authorize_transfer_create'=>$ownerDecision,'verify_transfer_group'=>$ownerDecision,'authorize_transfer_ready'=>$ownerDecision,'authorize_transfer_delivery'=>$ownerDecision,'authorize_transfer_revoke'=>$ownerDecision,'authorize_reprocess'=>$ownerDecision,'authorize_hold'=>$ownerDecision,
]);
foreach(['file10','file11','file12','file21','file22','cf01'] as $domain)DomainRegistry::register($domain,'1.0.0',['authorize_upload'=>$ownerDecision,'authorize_processing'=>$ownerDecision,'authorize_delivery'=>$ownerDecision,'authorize_download'=>$ownerDecision,'authorize_deletion'=>$ownerDecision,'authorize_grant_revoke'=>$ownerDecision,'retention_decision'=>$ownerDecision,'authorize_reprocess'=>$ownerDecision,'authorize_hold'=>$ownerDecision]);

foreach(['malware','archive','polyglot','decompression_bomb','metadata'] as $scanner)ScannerRegistry::register($scanner,static fn($stream,array $context)=>['passed'=>true,'engine'=>'test-'.$scanner,'signature'=>'clean','version'=>'1'],['version'=>'1.0.0','timeout_seconds'=>30,'max_depth'=>5,'capabilities'=>[$scanner]]);

function scm_clone_stream($source){$out=Utils::tempStream();if(is_resource($source)){rewind($source);stream_copy_to_stream($source,$out);rewind($source);}rewind($out);return $out;}
add_filter('scm_sandbox_tool_result',static function($value,string $operation,array $request): array {
    $output=[];
    if($operation==='probe'){$output=['supported'=>true,'duration_seconds'=>10,'codec'=>'test','container'=>'test','width'=>640,'height'=>360,'pages'=>1];}
    elseif($operation==='transform'){
        $op=(string)($request['operation']??'');$source=$request['source']['stream']??null;
        if($op==='metadata-policy'){$output=['output_stream'=>scm_clone_stream($source),'metadata_removed'=>['gps','location','device','author'],'metadata_preserved'=>['orientation','rights','accessibility']];}
        elseif($op==='image-pipeline'){$s=scm_clone_stream($source);$output=['outputs'=>[['kind'=>'thumbnail','stream'=>$s,'width'=>320,'height'=>240,'format'=>'webp','preset_version'=>'1'],['kind'=>'large','stream'=>scm_clone_stream($source),'width'=>1280,'height'=>960,'format'=>'avif','preset_version'=>'1']]];}
        elseif($op==='audio-pipeline'){$s=scm_clone_stream($source);$output=['probe'=>['supported'=>true,'duration_seconds'=>10],'outputs'=>[['kind'=>'audio-aac','stream'=>$s,'sha256'=>hash('sha256',stream_get_contents($s, -1, 0)),'bitrate'=>128000,'preset_version'=>'1'],['kind'=>'waveform','stream'=>scm_clone_stream($source),'sha256'=>hash('sha256',stream_get_contents(scm_clone_stream($source),-1,0)),'preset_version'=>'1']]];rewind($s);foreach($output['outputs'] as &$o){$stats=Utils::streamHash($o['stream']);$o['sha256']=$stats['sha256'];}unset($o);}
        elseif($op==='video-pipeline'){$kinds=['hls-manifest','hls-segment','dash-manifest','poster','waveform'];$outs=[];foreach($kinds as $kind){$s=scm_clone_stream($source);$stats=Utils::streamHash($s);$outs[]=['kind'=>$kind,'stream'=>$s,'sha256'=>$stats['sha256'],'bitrate'=>800000,'preset_version'=>'1'];}$output=['probe'=>['supported'=>true,'duration_seconds'=>10],'outputs'=>$outs];}
        elseif($op==='document-pipeline'){$outs=[];foreach(['preview','text','ocr'] as $kind){$s=scm_clone_stream($source);$outs[]=['kind'=>$kind,'stream'=>$s,'preset_version'=>'1'];}$output=['structure'=>['valid'=>true,'pages'=>1],'active_content_suppressed'=>true,'outputs'=>$outs];}
        else{$output=['output_stream'=>scm_clone_stream($source)];}
    }
    return ['passed'=>true,'worker_id'=>'test-sandbox','worker_version'=>'1.0.0','non_root'=>true,'network_isolated'=>true,'ephemeral'=>true,'resource_limits'=>['cpu_seconds'=>30,'memory_bytes'=>134217728,'files'=>1000],'output'=>$output];
});
add_filter('scm_technical_safety_signal',static fn($value,$stream,array $asset)=>['confidence'=>0.99,'signals'=>['technical_safe'],'requires_review'=>false,'model_id'=>'test-signal','model_version'=>'1.0']);

function ok(bool $condition,string $message): void {if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
function err(callable $fn,string $code,string $message): void {try{$fn();}catch(Sabri\CentralMedia\Error $e){ok($e->errorCode===$code,$message.' ['.$e->errorCode.']');return;}catch(Throwable $e){fwrite(STDERR,"UNEXPECTED ".get_class($e).": {$e->getMessage()}\n");exit(1);}ok(false,$message.' (no error)');}
function stream_of(string $bytes){$s=Utils::tempStream();fwrite($s,$bytes);rewind($s);return $s;}
function rights(array $ops=['view','download','extract_text','ocr'],bool $public=false): array {return ['rights_id'=>'rights-1','rights_version'=>1,'copyright_basis'=>'owner','license_id'=>'license-1','consent_status'=>'granted','consent_id'=>'consent-1','allowed_audiences'=>$public?['private','recipient','user','group','public']:['private','recipient','user','group'],'allowed_territories'=>['GLOBAL'],'allowed_operations'=>$ops,'clinical_confidentiality'=>!$public,'expires_at'=>time()+86400];}
function policy(string $media='document',string $privacy='C3',array $ops=['view','download','extract_text','ocr']): array {return Sabri\CentralMedia\Policy::normalize(['policy_id'=>'p-'.$media,'policy_version'=>1,'owner_domain'=>'file17','purpose'=>'verified-user-transfer','privacy_class'=>$privacy,'media_class'=>$media,'max_size_bytes'=>1073741824,'max_part_size_bytes'=>8388608,'max_upload_parts'=>20000,'allowed_mime_types'=>$media==='document'?['application/pdf']:($media==='image'?['image/png']:($media==='video'?['video/mp4']:['audio/mpeg'])),'allowed_extensions'=>$media==='document'?['pdf']:($media==='image'?['png']:($media==='video'?['mp4']:['mp3'])),'required_scans'=>['hash','magic','mime','malware','archive','polyglot','decompression_bomb','metadata'],'derivative_set'=>['preview','thumbnail','text','ocr','hls','dash','poster','waveform'],'max_width'=>4096,'max_height'=>4096,'max_pixels'=>20000000,'max_duration_seconds'=>3600,'max_pages'=>500,'max_archive_ratio'=>100,'max_archive_depth'=>5,'max_archive_entries'=>10000,'retention'=>['class'=>'private-standard','source_seconds'=>86400,'derivative_seconds'=>86400,'temporary_seconds'=>3600,'backup_expiry_seconds'=>2592000],'rights'=>rights($ops,$privacy==='C1'),'delivery'=>['modes'=>['same_origin_proxy','token_endpoint'],'grant_ttl_seconds'=>300,'allow_ranges'=>true,'max_range_bytes'=>8388608,'allow_download'=>in_array('download',$ops,true),'public_cdn'=>$privacy==='C1','content_disposition'=>'inline'],'metadata'=>['strip_location'=>true,'strip_device'=>true,'strip_author'=>true,'preserve_orientation'=>true,'preserve_rights'=>true,'preserve_accessibility'=>true],'safety'=>['require_reviewer_for_low_confidence'=>true,'minimum_confidence'=>0.80]],true);}
