<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-plan-parity.php');
$t=file_get_contents($root.'/tests/new-plan-parity.php');
function r111($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 111 FAIL: $m\n");exit(1);}echo "ROUND 111 PASS: $m\n";}
r111(str_contains($p,"C0-only public CDN/index; C1-C5 require non-public owner-authorized delivery"),'CF04-CEN-05 manifest matches the C0-only public-delivery constitution');
r111(str_contains($t,'NEW-PLAN C1 public CDN denied')&&str_contains($t,'CF04-CEN-05 C1 asset cannot reach public CDN'),'C1 is explicitly regression-tested against policy normalization and publication');
echo "REVIEW ROUND 111 PLAN PARITY: PASS\n";
