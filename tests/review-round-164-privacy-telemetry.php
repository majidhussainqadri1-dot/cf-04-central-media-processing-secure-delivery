<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Observability,OperationsFutureService,PrivacyTelemetry,RecordStore};
RecordStore::resetMemory();
$good=OperationsFutureService::qoe('media_playback_startup_ms',120.0,['domain'=>'file17','media_class'=>'video','privacy_class'=>'C3','status'=>'ready']);
ok($good['metric']==='media_playback_startup_ms','Round 164 approved QoE');
PrivacyTelemetry::metric('media_processing_completed',1.0,['domain'=>'file17']);
foreach(['unapproved_metric','MEDIA_PLAYBACK_STARTUP_MS','media_playback_startup_ms_extra'] as $bad)
    err(fn()=>PrivacyTelemetry::metric($bad,1.0),'privacy_metric_name_denied','Round 164 metric vocabulary');
foreach([['unknown'=>'x'],[0=>'file17']] as $bad)
    err(fn()=>PrivacyTelemetry::metric('media_playback_startup_ms',1.0,$bad),'privacy_metric_label_denied','Round 164 label key');
foreach([['domain'=>'unapproved'],['status'=>'arbitrary'],['privacy_class'=>'C9'],
         ['domain'=>123],['domain'=>true],['domain'=>null],['domain'=>[]]] as $bad)
    err(fn()=>PrivacyTelemetry::metric('media_playback_startup_ms',1.0,$bad),'privacy_metric_label_value_denied','Round 164 label value or type');
foreach([-1.0,INF,NAN,1000000001.0] as $bad)
    err(fn()=>OperationsFutureService::qoe('media_playback_startup_ms',$bad),'privacy_metric_value_invalid','Round 164 numeric bounds');
err(fn()=>PrivacyTelemetry::metric('media_playback_rebuffer_count',1.5),'privacy_metric_value_invalid','Round 164 fractional count');
err(fn()=>PrivacyTelemetry::metric('media_processing_completed',0.5),'privacy_metric_value_invalid','Round 164 fractional completion');
err(fn()=>Observability::metric('unapproved_metric',1.0),'privacy_metric_name_denied','Round 164 direct metric gate');
err(fn()=>Observability::metric('media_playback_startup_ms',1.0,['domain'=>'unapproved']),'privacy_metric_label_value_denied','Round 164 direct label gate');
$legacy=Observability::metric('queue.depth',3.0,['tenant'=>'file17']);
ok($legacy['metric']==='queue.depth','Round 164 FR-033 bounded operational queue metric preserved');
err(fn()=>Observability::metric('queue.depth',3.0,['tenant'=>'unapproved']),
    'metric_label_invalid','Round 164 operational metric tenant bounded');
ok(count(RecordStore::all('metric',0,null,1000))===3,'Round 164 no rejected persistence');
echo "REVIEW ROUND 164 PRIVACY TELEMETRY: PASS\n";
