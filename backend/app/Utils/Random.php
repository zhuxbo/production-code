<?php

declare(strict_types=1);

namespace App\Utils;

use Exception;

class Random
{
    /**
     * 获取全球唯一标识
     */
    public static function uuid(): string
    {
        $rand = self::getRandomFunction();

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            $rand(0, 0xFFFF), $rand(0, 0xFFFF), // 时间低位
            $rand(0, 0xFFFF), // 时间中位
            $rand(0, 0x0FFF) | 0x4000, // 时间高位和版本，这里是版本4
            $rand(0, 0x3FFF) | 0x8000, // 变体
            $rand(0, 0xFFFF), $rand(0, 0xFFFF), $rand(0, 0xFFFF) // 随机数字段
        );
    }

    /**
     * 获取不带横线的全球唯一标识
     */
    public static function uuidWithoutHyphens(): string
    {
        $rand = self::getRandomFunction();

        return sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            $rand(0, 0xFFFF), $rand(0, 0xFFFF), // 时间低位
            $rand(0, 0xFFFF), // 时间中位
            $rand(0, 0x0FFF) | 0x4000, // 时间高位和版本，这里是版本4
            $rand(0, 0x3FFF) | 0x8000, // 变体
            $rand(0, 0xFFFF), $rand(0, 0xFFFF), $rand(0, 0xFFFF) // 随机数字段
        );
    }

    /**
     * 随机字符生成
     *
     * @param  string  $type  类型 alpha/alnum/numeric/noZero/unique/md5/encrypt/sha256
     * @param  int  $length  长度 (类型为 alpha/alnum/numeric/noZero 时有效)
     */
    public static function build(string $type = 'alnum', int $length = 8): string
    {
        if (! in_array($type, ['alpha', 'alnum', 'numeric', 'noZero', 'unique', 'md5', 'encrypt', 'sha256'], true)) {
            $type = 'alnum';
        }

        if ($length < 8) {
            $length = 8;
        } elseif ($length > 512) {
            $length = 512;
        }

        $rand = self::getRandomFunction();

        return match ($type) {
            'alpha', 'alnum', 'numeric', 'noZero' => self::generateFromPool($type, $length, $rand),
            'unique', 'md5' => md5(uniqid(self::safeRandomBytes($length), true)),
            'encrypt', 'sha256' => hash('sha256', uniqid(self::safeRandomBytes($length), true)),
        };
    }

    /**
     * 根据类型和长度生成随机字符串
     */
    private static function generateFromPool(string $type, int $length, callable $rand): string
    {
        if (! in_array($type, ['alpha', 'alnum', 'numeric', 'noZero'], true)) {
            $type = 'alnum';
        }

        $pool = match ($type) {
            'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numeric' => '0123456789',
            'noZero' => '123456789',
        };

        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $pool[$rand(0, strlen($pool) - 1)];
        }

        return $str;
    }

    /**
     * 安全随机字节生成
     */
    private static function safeRandomBytes(int $length = 16): string
    {
        $length = max(16, min($length, 1024));

        try {
            return random_bytes($length);
        } catch (Exception) {
            return openssl_random_pseudo_bytes($length);
        }
    }

    /**
     * 获取随机数生成函数，优先使用 random_int，降级到 mt_rand
     */
    private static function getRandomFunction(): callable
    {
        return function_exists('random_int')
            ? fn ($min, $max) => random_int($min, $max)
            : fn ($min, $max) => mt_rand($min, $max);
    }
}
