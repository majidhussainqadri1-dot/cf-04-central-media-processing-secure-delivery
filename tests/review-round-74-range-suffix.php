<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Error,Validator};
function r74($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 74 FAIL: $m\n");exit(1);}echo "ROUND 74 PASS: $m\n";}
$failed=false;try{Validator::range('bytes=-0',100,50);}catch(Error $e){$failed=$e->errorCode==='range_not_satisfiable'&&$e->httpStatus===416;}
r74($failed,'zero-length suffix range fails closed');
$r=Validator::range('bytes=-10',100,50);r74($r['start']===90&&$r['end']===99&&$r['length']===10&&$r['partial']===true,'valid suffix range keeps exact requested semantics');
$r=Validator::range('bytes=-200',100,200);r74($r['start']===0&&$r['end']===99&&$r['length']===100,'suffix larger than representation safely resolves to the full representation');
echo "REVIEW ROUND 74 RANGE SUFFIX: PASS\n";
