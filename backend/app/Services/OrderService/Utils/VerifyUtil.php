<?php

declare(strict_types=1);

namespace App\Services\OrderService\Utils;

use App\Models\Order;
use App\Traits\ApiResponseStatic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VerifyUtil
{
    use ApiResponseStatic;

    private const array API_URLS = [
        'hk' => 'https://43.128.4.245/',
        'us' => 'https://43.130.58.132/',
        'eu' => 'https://43.157.80.238/',
    ];

    /**
     * 验证域名DNS记录，支持故障转移
     */
    private static function verifyDomains(string $ca, string $domains): array
    {
        $client = new Client([
            'timeout' => 3.0, // 设置超时时间为3秒
            'verify' => false, // 关闭SSL证书验证
        ]);

        foreach (self::API_URLS as $url) {
            try {
                $response = $client->post($url.'autoVerify', [
                    'form_params' => [
                        'brand' => $ca,
                        'domains' => $domains,
                    ],
                ]);

                return json_decode($response->getBody()->getContents(), true);
            } catch (GuzzleException) {
                continue; // 尝试下一个API
            }
        }

        // 如果所有API都失败，返回成功信息，忽略验证
        return ['code' => 1, 'data' => null];
    }

    /**
     * 自动验证订单域名
     */
    public static function autoVerify(array $order_ids): void
    {
        // 查询符合条件的订单
        $orders = Order::with(['latestCert', 'product'])
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'unpaid'))
            ->whereIn('id', $order_ids)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $verifyResult = [];

        // 遍历订单进行验证
        foreach ($orders as $order) {
            $result = self::verifyDomains($order->product->ca, $order->latestCert->alternative_names);

            // 如果验证返回了错误信息
            if ($result['code'] == 0) {
                $verifyResult[$order->id] = $result['data'];
            }
        }

        empty($verifyResult) || self::error('域名基础验证失败，请联系管理员', $verifyResult);
    }

    /**
     * 验证域名验证记录，支持故障转移
     */
    public static function verifyValidation(array $validation): bool
    {
        $client = new Client([
            'timeout' => 3.0, // 设置超时时间为3秒
            'verify' => false, // 关闭SSL证书验证
        ]);

        foreach (self::API_URLS as $url) {
            try {
                // 发送json数据
                $response = $client->post($url.'batchVerify', [
                    'json' => $validation,
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                return boolval($result['code'] ?? false);
            } catch (GuzzleException) {
                continue; // 尝试下一个API
            }
        }

        return false;
    }
}
