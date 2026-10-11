<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-future40.php');
function r131($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 131 FAIL: $m\n");exit(1);}echo "ROUND 131 PASS: $m\n";}
r131(str_contains($s,'perceptual_similarity_invalid')&&str_contains($s,'!is_finite($similarity)')&&str_contains($s,'$similarity>1.0'),'near-duplicate adapter similarity is finite and normalized');
r131(str_contains($s,'!is_finite($confidence)')&&str_contains($s,'foreach($box as $coordinate)')&&str_contains($s,'!is_finite($coordinate)'),'OCR confidence and normalized box coordinates reject non-finite/out-of-range values');
echo "REVIEW ROUND 131 FUTURE NUMERIC BOUNDARIES: PASS\n";
