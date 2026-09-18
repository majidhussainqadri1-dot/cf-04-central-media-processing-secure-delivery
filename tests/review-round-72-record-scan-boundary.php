<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Error,RecordStore};
function r72($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 72 FAIL: $m\n");exit(1);}echo "ROUND 72 PASS: $m\n";}
RecordStore::resetMemory();
for($i=0;$i<3;$i++)RecordStore::put('r72','row-'.$i,['actor_id'=>0,'status'=>'active']);
$exact=RecordStore::all('r72',0,null,3);
r72(count($exact)===3,'exactly-full bounded scan returns its records instead of a false record_scan_limit');
RecordStore::put('r72','row-3',['actor_id'=>0,'status'=>'active']);
$overflow=false;
try{RecordStore::all('r72',0,null,3);}catch(Error $e){$overflow=$e->errorCode==='record_scan_limit';}
r72($overflow,'true record population beyond the explicit maximum still fails closed');
$src=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-persistence.php');
r72(str_contains($src,'$probe=self::scanPage($type,$actor,$status,1,$after)'),'boundary overflow is determined by an explicit stable-keyset one-record probe');
echo "REVIEW ROUND 72 RECORD SCAN BOUNDARY: PASS\n";
