<?php

declare(strict_types=1);

namespace App\Services\Order\Utils;

use App\Models\Order;
use App\Traits\ApiResponseStatic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class VerifyUtil
{
    use ApiResponseStatic;

    /**
     * 获取验证工具URLs
     */
    private static function getDnsToolsUrls(): array
    {
        $urls = get_system_setting('site', 'dnsTools');

        // 确保返回数组格式
        return is_array($urls) ? $urls : [];
    }

    /**
     * 验证域名DNS记录，支持故障转移
     */
    private static function verifyDomains(string $ca, string $domains): array
    {
        $client = new Client([
            'timeout' => 3.0, // 设置超时时间为3秒
            'verify' => false, // 关闭SSL证书验证
        ]);

        foreach (self::getDnsToolsUrls() as $url) {
            try {
                $response = $client->post($url.'/api/domain/issue-verify', [
                    'json' => [
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
     * 验证订单域名是否能签发
     */
    public static function issueVerify(array $order_ids): void
    {
        // 查询符合条件的订单
        $orders = Order::with(['latestCert', 'product'])
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'unpaid'))
            ->whereIn('id', $order_ids)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $resultErrors = [];
        $lastErrorMsg = '域名签发验证失败，请联系管理员';

        // 遍历订单进行验证
        foreach ($orders as $order) {
            // 检查必要字段是否存在
            if (empty($order->product->ca) || empty($order->latestCert->alternative_names)) {
                continue;
            }

            $result = self::verifyDomains($order->product->ca, $order->latestCert->alternative_names);

            // 如果验证返回了错误信息
            if ($result['code'] == 0) {
                $lastErrorMsg = $result['msg'] ?? $lastErrorMsg;
                $errors = [];
                foreach ($result['errors'] as $error) {
                    if ($error['valid'] === false) {
                        $errors[$error['display_domain']]['说明'] = $error['message'];
                        $errors[$error['display_domain']]['错误'] = $error['errors'];
                    }
                }
                $resultErrors[] = $errors;
            }
        }

        empty($resultErrors) || self::error($lastErrorMsg, $resultErrors);
    }

    /**
     * 验证域名验证记录，支持故障转移
     */
    public static function verifyValidation(array $validation): array
    {
        $urls = self::getDnsToolsUrls();

        // 检查是否有可用的 DNS Tools URLs
        if (empty($urls)) {
            Log::error('DNS Tools URLs 未配置');

            return [
                'code' => 0,
                'msg' => 'DNS Tools URLs 未配置，无法进行域名验证',
            ];
        }

        $client = new Client([
            'timeout' => 3.0, // 设置超时时间为3秒
            'verify' => false, // 关闭SSL证书验证
        ]);

        $lastError = '';
        foreach ($urls as $url) {
            try {
                // 发送json数据
                $response = $client->post($url.'/api/dcv/verify', [
                    'json' => $validation,
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                if ($result === null) {
                    Log::error('DNS Tools API 返回无效 JSON', ['url' => $url]);
                    $lastError = 'API 返回无效数据';

                    continue;
                }

                return [
                    'code' => $result['code'] ?? 0,
                    'msg' => $result['msg'] ?? '',
                    'errors' => $result['errors'] ?? [],
                ];
            } catch (GuzzleException $e) {
                $lastError = $e->getMessage();
                Log::error('DNS Tools API 请求失败', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                continue; // 尝试下一个API
            }
        }

        return [
            'code' => 0,
            'msg' => 'DNS Tools API 请求失败: '.$lastError,
        ];
    }
}
