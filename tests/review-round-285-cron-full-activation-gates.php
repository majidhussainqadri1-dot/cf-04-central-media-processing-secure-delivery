<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-plugin.php');
function r285assert(bool $valid,string $label): void {
    if(!$valid){fwrite(STDERR,"ROUND 285 FAIL: $label\n");exit(1);}
    echo "ROUND 285 PASS: $label\n";
}
r285assert(is_string($source),'plugin source readable');
foreach(['cronJobs','cronRetention','cronDeletions','cronIntegrity'] as $method){
    $pattern='/public static function '.preg_quote($method,'/').'\(\): void \{([\s\S]*?)(?=\n    (?:public|private) static function |\n\})/';
    r285assert(preg_match($pattern,$source,$m)===1,"$method body found");
    $body=$m[1];
    $ready=strpos($body,"RuntimeGuard::requireReady(['streaming'])");
    $lock=strpos($body,'self::acquire(');
    r285assert($ready!==false&&$lock!==false&&$ready<$lock,
        "$method requires fully approved runtime before acquiring a durable lock");
    r285assert(str_contains($body,"catch(\\Throwable \$e){self::cronAlert('runtime_activation_not_ready',\$e);return;}"),
        "$method blocks cron mutations and safely reports missing activation");
}
echo "REVIEW ROUND 285 CRON FULL ACTIVATION GATES: PASS\n";
