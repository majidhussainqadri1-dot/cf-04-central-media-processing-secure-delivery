<?php
declare(strict_types=1); require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,Auth,Error,Validator,TransferService,WorkspaceUploadService,ScannerRegistry};
DomainRegistry::reset(); err(fn()=>Auth::domainDecision('file17','authorize_transfer_create',[]),'domain_contract_unavailable','missing owner contract fails closed');
err(fn()=>Validator::archiveRatio(10,2000,100),'decompression_bomb_detected','archive bomb rejected');
err(fn()=>TransferService::validateEnvelope(['native_transfer_id'=>'x','native_transfer_version'=>1,'sender_user_id'=>5,'recipient_type'=>'user','recipient_reference'=>'5','expected_size'=>1,'media_class'=>'document','declared_name'=>'x.pdf']),'transfer_self_recipient_invalid','self recipient rejected');
err(fn()=>WorkspaceUploadService::normalizeContext('image',['compression_quality'=>20]),'image_compression_quality_invalid','unsafe compression rejected');
ScannerRegistry::registerBuiltins(); err(fn()=>ScannerRegistry::scan('clean',['malware'],[]),'media_scan_failed','missing malware provider fails closed');
echo "THREE PLAN ADVERSARIAL: PASS\n";
