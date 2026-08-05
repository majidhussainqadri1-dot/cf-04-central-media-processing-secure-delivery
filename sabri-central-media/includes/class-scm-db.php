<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Db {
    private static array $memory=[];
    public static function table(string $name): string { global $wpdb; return isset($wpdb->prefix)?$wpdb->prefix.'scm_'.Utils::key($name,32):'scm_'.Utils::key($name,32); }
    public static function memoryPut(string $type,string $id,array $row): void { self::$memory[$type][$id]=$row; }
    public static function memoryGet(string $type,string $id): ?array { return self::$memory[$type][$id]??null; }
    public static function memoryAll(string $type): array { return array_values(self::$memory[$type]??[]); }
    public static function memoryDelete(string $type,string $id): void { unset(self::$memory[$type][$id]); }
}
