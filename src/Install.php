<?php
declare(strict_types=1);

namespace Thb\Rabbitmq;

class Install
{
    public const WEBMAN_PLUGIN = true;

    protected static array $pathRelation = [
        'config/plugin/thb/rabbitmq' => 'config/plugin/thb/rabbitmq',
    ];

    public static function install(): void
    {
        static::installByRelation();
        $dir = app_path() . '/queue/rabbitmq';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public static function uninstall(): void
    {
        static::uninstallByRelation();
    }

    protected static function installByRelation(): void
    {
        foreach (static::$pathRelation as $source => $dest) {
            $parent = base_path() . '/' . dirname($dest);
            if (!is_dir($parent)) {
                mkdir($parent, 0777, true);
            }
            copy_dir(__DIR__ . "/../$source", base_path() . "/$dest", true);
        }
    }

    protected static function uninstallByRelation(): void
    {
        foreach (static::$pathRelation as $dest) {
            $path = base_path() . "/$dest";
            // 保留用户配置，不自动删除
            unset($path);
        }
    }
}
