<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class RetentionService {
    public static function decision(array $asset): array { $d=DomainRegistry::call((string)$asset['owner_domain'],'retention_decision',['asset'=>$asset]); if(!is_array($d)||!isset($d['retain_until'])) throw new Error('retention_decision_incomplete','Owning domain retention decision incomplete.',503); return $d; }
}
