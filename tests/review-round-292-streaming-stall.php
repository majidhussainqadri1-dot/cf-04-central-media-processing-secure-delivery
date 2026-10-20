<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{StreamingEndpoint,Utils};

$method=new \ReflectionMethod(StreamingEndpoint::class,'readChunk');
$normal=Utils::tempStream();
Utils::writeAll($normal,'verified-payload');rewind($normal);
ok($method->invoke(null,$normal)==='verified-payload','R292 readChunk delivers available bytes');
ok($method->invoke(null,$normal)==='','R292 readChunk allows empty chunk at authoritative EOF');
fclose($normal);

$pipes=stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,STREAM_IPPROTO_IP);
ok(is_array($pipes)&&count($pipes)===2,'R292 socket-backed stream fixture created');
stream_set_blocking($pipes[0],false);
err(static fn()=>$method->invoke(null,$pipes[0]),'delivery_stream_stalled',
    'R292 transient empty read before EOF fails closed instead of silently completing');
ok(fwrite($pipes[1],'verified-after-stall')===strlen('verified-after-stall'),
    'R292 blocked producer can later supply additional bytes');
ok($method->invoke(null,$pipes[0])==='verified-after-stall',
    'R292 reader consumes resumed valid data in isolation');
fclose($pipes[0]);fclose($pipes[1]);
echo "REVIEW ROUND 292 STREAMING STALL FAIL-CLOSED: PASS\n";
