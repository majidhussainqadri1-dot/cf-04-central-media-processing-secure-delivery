<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class CompanionDomainAdapters {
    public static function register(): void {
        foreach(['file10'=>'svw','file11'=>'sr','file12'=>'spl','file17'=>'sn','file21'=>'snp','file22'=>'supc'] as $domain=>$prefix){
            if(DomainRegistry::version($domain)!=='') continue;
            $callbacks=[]; foreach(['authorize_upload','authorize_delivery','authorize_deletion','authorize_hold','authorize_reprocess','authorize_download','authorize_transfer_create','authorize_transfer_delivery','authorize_transfer_revoke','current_object_state','retention_decision'] as $op){
                $callbacks[$op]=static function(array $ctx) use($domain,$prefix,$op): array { $decision=function_exists('apply_filters')?apply_filters("scm_{$prefix}_{$op}",null,$ctx):null; if(!is_array($decision)) return ['authorized'=>false,'allowed'=>false,'object_version'=>0,'reason_code'=>'owner_adapter_unavailable']; if(str_starts_with($op,'authorize_') && (($decision['authorized']??$decision['allowed']??false)!==true)) throw new Error('domain_owner_denied','Owning domain denied media operation.',403,['domain'=>$domain,'operation'=>$op]); return $decision; };
            }
            DomainRegistry::register($domain,'1.0.0',$callbacks);
        }
    }
}
