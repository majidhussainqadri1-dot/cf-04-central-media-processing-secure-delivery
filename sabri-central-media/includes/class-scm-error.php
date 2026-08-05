<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Error extends \RuntimeException {
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpStatus=400, public readonly array $context=[]) {
        parent::__construct($message);
    }
    public function asArray(): array { return ['code'=>$this->errorCode,'message'=>$this->getMessage(),'status'=>$this->httpStatus,'context'=>Utils::redact($this->context)]; }
}
