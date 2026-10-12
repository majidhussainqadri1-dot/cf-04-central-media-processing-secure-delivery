<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,Rest,Utils};

// R207: REST route identities, upload-part ordinals and HTTP body lengths must
// be canonical. Do not let truncation or (int) coercion alias other requests.
final class R207Request implements \ArrayAccess {
    public function __construct(
        public array $route = [],
        public array $headers = [],
        public string $body = ''
    ) {}
    public function offsetExists(mixed $offset): bool {return array_key_exists((string)$offset,$this->route);}
    public function offsetGet(mixed $offset): mixed {return $this->route[(string)$offset]??null;}
    public function offsetSet(mixed $offset,mixed $value): void {throw new \LogicException('Fixture route is immutable');}
    public function offsetUnset(mixed $offset): void {throw new \LogicException('Fixture route is immutable');}
    public function get_header(string $name): string {return (string)($this->headers[strtolower($name)]??'');}
    public function get_body(): string {return $this->body;}
}
function r207code(callable $fn,string $code,string $message): void {
    $result=$fn();
    ok(is_array($result)&&($result['code']??null)===$code,$message.' ['.($result['code']??'non-error').']');
}
RecordStore::resetMemory();
$id='r207-upload';
RecordStore::put('upload',$id,[
    'actor_id'=>11,'status'=>'uploading','policy'=>['max_part_size_bytes'=>64]
]);
$headers=['x-scm-contract-version'=>SCM_CONTRACT_VERSION];
foreach(['01','1garbage','1.0','-1','0',str_repeat('9',32)] as $invalid) {
    $req=new R207Request(['id'=>$id,'part'=>$invalid],$headers);
    r207code(fn()=>Rest::putPart($req),'part_number_invalid',
        'R207 noncanonical upload ordinal is rejected without aliasing an existing part');
}
foreach([$id.str_repeat('x',100),$id."\n",' '.$id,12345] as $invalidId){
    $req=new R207Request(['id'=>$invalidId,'part'=>'1'],$headers);
    r207code(fn()=>Rest::putPart($req),'route_identity_invalid',
        'R207 noncanonical/oversized upload identity is rejected before lookup');
}
foreach(['2garbage','01','-1',str_repeat('9',40)] as $invalidLength){
    $req=new R207Request(['id'=>$id,'part'=>'1'],$headers+['content-length'=>$invalidLength]);
    r207code(fn()=>Rest::putPart($req),'content_length_invalid',
        'R207 malformed upload Content-Length is rejected');
}
$req=new R207Request(['id'=>$id,'part'=>'1'],$headers+['content-length'=>'65']);
r207code(fn()=>Rest::putPart($req),'upload_part_size_invalid',
    'R207 canonical declared upload body above per-part limit is rejected');

$secret=str_repeat('s',32);
add_filter('scm_provider_webhook_secret',
    static fn($old,string $provider): string=>$secret);
$ts=Utils::now();
$body='{}';
$event='r207-valid-webhook';
$signature=Utils::b64url(hash_hmac('sha256',
    'provider|'.$event.'|'.$ts.'|'.hash('sha256',$body),$secret,true));
$webHeaders=['x-event-id'=>$event,'x-event-timestamp'=>(string)$ts,
    'x-event-signature'=>$signature];
foreach(['2junk','02','-1',str_repeat('9',40)] as $invalidLength){
    $req=new R207Request(['provider'=>'provider'],$webHeaders+['content-length'=>$invalidLength],$body);
    r207code(fn()=>Rest::providerWebhook($req),'content_length_invalid',
        'R207 malformed webhook Content-Length is denied before signature verification');
}
$req=new R207Request(['provider'=>'provider'],$webHeaders+['content-length'=>'1048577'],$body);
r207code(fn()=>Rest::providerWebhook($req),'request_body_too_large',
    'R207 declared webhook body beyond the safety limit is denied');
$req=new R207Request(['provider'=>'provider'],$webHeaders+['content-length'=>(string)strlen($body)],$body);
$accepted=Rest::providerWebhook($req);
ok(is_array($accepted)&&($accepted['replay']??null)===false,
    'R207 exact canonical length and signed webhook still succeed');
echo "REVIEW ROUND 207 REST CANONICAL INGRESS: PASS\n";
