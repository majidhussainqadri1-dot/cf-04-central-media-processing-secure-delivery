<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/README.md');
function r102($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 102 FAIL: $m\n");exit(1);}echo "ROUND 102 PASS: $m\n";}
r102(str_contains($s,'subsequent focused rounds 72–101'),'repository-facing hardening summary no longer stops at the older 62–71 batch');
r102(str_contains($s,'corrected only after that round ends')&&str_contains($s,'verified before the next round begins'),'README preserves the mandated review-then-fix-then-next sequencing law');
echo "REVIEW ROUND 102 EVIDENCE FRESHNESS: PASS\n";
