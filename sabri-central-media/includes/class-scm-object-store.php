<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
interface ObjectStore { public function put(string $key,string $bytes,array $meta=[]): array; public function get(string $key): string; public function delete(string $key): bool; public function exists(string $key): bool; public function healthy(): bool; }
