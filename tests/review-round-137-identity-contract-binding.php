<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Auth,DomainRegistry};

DomainRegistry::register('round137','1.0.0',['authorize'=>static fn()=>['allowed'=>true,'object_version'=>'1.5']]);
err(fn()=>DomainRegistry::decision('round137','authorize',[]),'domain_authorization_incomplete','Domain object version rejects fractional text');

DomainRegistry::reset();
DomainRegistry::register('file00','1.0.0',['verify_user'=>static fn(array $context)=>['verified'=>true,'approved'=>true,'active'=>true,'eligible'=>true,'assertion_version'=>1,'user_id'=>(int)$context['user_id']+1]]);
err(fn()=>Auth::verifiedUser(11,'round137','file17'),'verification_subject_mismatch','Verification assertion is subject-bound');

DomainRegistry::reset();
DomainRegistry::register('file00','1.0.0',['verify_user'=>static fn(array $context)=>['verified'=>true,'approved'=>true,'active'=>true,'eligible'=>true,'assertion_version'=>'1.5','user_id'=>$context['user_id']]]);
err(fn()=>Auth::verifiedUser(11,'round137','file17'),'verification_incomplete','Verification version rejects fractional text');

DomainRegistry::reset();
err(fn()=>Auth::transferParties(['sender_user_id'=>'11.5','recipient_type'=>'user','recipient_user_id'=>12],'round137'),'transfer_party_invalid','Transfer sender identity rejects fractional text');

echo "REVIEW ROUND 137 IDENTITY CONTRACT BINDING: PASS\n";
