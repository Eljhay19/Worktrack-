<?php

namespace App\Support;

final class MySqlClientOptionsFile
{
    public static function create(array $connection): string
    {
        $root = storage_path('app/private');
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Could not create the private storage directory.');
        }

        $path = tempnam($root, '.worktrack-mysql-');
        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary MySQL client file.');
        }

        $lines = [
            '[client]',
            'user='.self::optionValue((string) ($connection['username'] ?? '')),
            'password='.self::optionValue((string) ($connection['password'] ?? '')),
        ];

        if (!empty($connection['unix_socket'])) {
            $lines[] = 'socket='.self::optionValue((string) $connection['unix_socket']);
        } else {
            $lines[] = 'host='.self::optionValue((string) ($connection['host'] ?? '127.0.0.1'));
            $lines[] = 'port='.(int) ($connection['port'] ?? 3306);
            $lines[] = 'protocol=tcp';
        }

        if (file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX) === false) {
            @unlink($path);
            throw new \RuntimeException('Could not write the temporary MySQL client file.');
        }

        self::restrictFilePermissions($path);

        return $path;
    }

    public static function remove(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    private static function optionValue(string $value): string
    {
        $escaped = str_replace(
            ["\\", '"', "\n", "\r"],
            ["\\\\", '\\"', '\\n', '\\r'],
            $value,
        );

        return '"'.$escaped.'"';
    }

    public static function restrictFilePermissions(string $path): void
    {
        if (DIRECTORY_SEPARATOR !== '\\' && is_file($path)) {
            @chmod($path, 0600);
        }
    }
}
