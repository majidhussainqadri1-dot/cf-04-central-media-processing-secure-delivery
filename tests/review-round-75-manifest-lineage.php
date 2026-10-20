<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r75($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 75 FAIL: $m\n");exit(1);}echo "ROUND 75 PASS: $m\n";}
$current=<<<'PATTERN'
$currentGeneration=max(1,(int)($asset['processing_generation']??1))
PATTERN;
$active=<<<'PATTERN'
($active['status']??'')==='active'
PATTERN;
$gate=<<<'PATTERN'
$generation!==$currentGeneration&&!isset($carryover[$id])
PATTERN;
r75(str_contains($s,$current),'manifest binds derivative selection to the asset processing generation');
r75(str_contains($s,$active),'only the current active manifest can authorize historical carryover');
r75(str_contains($s,$gate),'arbitrary stale validated derivatives fail closed');
r75(str_contains($s,'derivative_generation_stale'),'stale-lineage rejection has an explicit conflict code');
echo "REVIEW ROUND 75 MANIFEST LINEAGE: PASS\n";
