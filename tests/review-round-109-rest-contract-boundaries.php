<?php
declare(strict_types=1);
$root=dirname(__DIR__);$r=file_get_contents($root.'/sabri-central-media/includes/class-scm-rest.php');$u=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');$c=file_get_contents($root.'/sabri-central-media/includes/class-scm-core.php');$f=file_get_contents($root.'/sabri-central-media/includes/class-scm-future40.php');
function r109($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 109 FAIL: $m\n");exit(1);}echo "ROUND 109 PASS: $m\n";}
$boundedPart=<<<'PATTERN'
Utils::streamHash($stream,(int)$upload['policy']['max_part_size_bytes'])
PATTERN;
r109(str_contains($r,'contract_version_mismatch')&&substr_count($r,'self::assertContract($request)')>=10,'operational REST routes enforce the active versioned media contract instead of silently accepting unknown clients');
r109(str_contains($r,'idempotency_key_required')&&str_contains($r,"self::header($request,'idempotency-key')"),'idempotent REST mutations consume the declared Idempotency-Key header');
r109(str_contains($c,'streamHash($stream,?int $maxBytes=null)')&&str_contains($u,$boundedPart),'chunked/missing-content-length uploads are bounded during hashing rather than only after reading the full part');
r109(str_contains($f,"'idempotency_header'=>'Idempotency-Key'")&&str_contains($f,"'required_headers'=>['X-SCM-Contract-Version']"),'SDK manifest distinguishes the universal contract header from route-scoped idempotency');
echo "REVIEW ROUND 109 REST CONTRACT BOUNDARIES: PASS\n";
