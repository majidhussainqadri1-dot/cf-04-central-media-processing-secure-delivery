<?php
declare(strict_types=1);
$root=dirname(__DIR__);$matrix=file_get_contents($root.'/docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md');$readme=file_get_contents($root.'/README.md');$plugin=file_get_contents($root.'/sabri-central-media/sabri-central-media.php');
function gate(bool $v,string $m): void {if(!$v){fwrite(STDERR,"ROUND 11 FAIL: $m\n");exit(1);}echo "ROUND 11 PASS: $m\n";}
for($i=1;$i<=33;$i++)gate(str_contains($matrix,sprintf('CF04-FR-%03d',$i)),'traceability '.sprintf('CF04-FR-%03d',$i));
gate(str_contains($matrix,'CHAT-XFER-001')&&str_contains($matrix,'CHAT-QA-001'),'cross-plan directives mapped');
gate(str_contains($readme,'External acceptance')&&str_contains($readme,'does **not** authorize production use'),'truthful completion boundary');
gate((bool)preg_match("/define\('SCM_RUNTIME_ENABLED',false\)/",$plugin),'runtime disabled by default');
gate(str_contains($plugin,"SCM_SCHEMA_VERSION','1.4.0")&&str_contains($plugin,"SCM_CONTRACT_VERSION','1.4.0"),'version alignment');
echo "REVIEW ROUND 11 GOVERNANCE: PASS\n";
