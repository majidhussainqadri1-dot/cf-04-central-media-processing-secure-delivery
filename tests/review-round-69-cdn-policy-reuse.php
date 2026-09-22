<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r69($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 69 FAIL: $m\n");exit(1);}echo "ROUND 69 PASS: $m\n";}
$privacy=<<<'PATTERN'
($mapping['privacy_class']??'')===$asset['privacy_class']
PATTERN;
$policy=<<<'PATTERN'
hash_equals((string)($mapping['policy_hash']??''),(string)$asset['policy_hash'])
PATTERN;
$rights=<<<'PATTERN'
hash_equals((string)($mapping['rights_hash']??''),(string)$asset['rights']['policy_hash'])
PATTERN;
$cache=<<<'PATTERN'
$asset['rights']['policy_hash']).'/'.$derivative['sha256']
PATTERN;
r69(str_contains($s,$privacy),'public CDN mapping reuse is privacy-class bound');
r69(str_contains($s,$policy),'public CDN mapping reuse is current upload/delivery-policy bound');
r69(str_contains($s,$rights),'public CDN mapping reuse is current rights-policy bound');
r69(str_contains($s,$cache),'new CDN cache keys remain rights-aware');
echo "REVIEW ROUND 69 CDN POLICY REUSE: PASS\n";
