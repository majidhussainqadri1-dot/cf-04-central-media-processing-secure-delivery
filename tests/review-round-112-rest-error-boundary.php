<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$r=file_get_contents($root.'/sabri-central-media/includes/class-scm-rest.php');
function r112($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 112 FAIL: $m\n");exit(1);}echo "ROUND 112 PASS: $m\n";}
foreach(['putPart','uploadAction','issueGrant','serveMetadata','createTransfer','listDownloads','createDownload','requestDeletion','processDeletion','placeHold','providerWebhook'] as $method){
    $start=strpos($r,'public static function '.$method.'(');
    r112($start!==false,$method.' handler exists');
    $slice=substr($r,$start,1800);
    r112(strpos($slice,'return self::wrap(')!==false,$method.' enters the standardized REST error boundary');
}
r112(!str_contains($r,"public static function putPart(\$request): mixed {self::assertContract"),'putPart contract/stream validation cannot escape REST error translation');
r112(!str_contains($r,"public static function providerWebhook(\$request): mixed {\$provider="),'webhook body/signature preprocessing cannot escape REST error translation');
echo "REVIEW ROUND 112 REST ERROR BOUNDARY: PASS\n";
