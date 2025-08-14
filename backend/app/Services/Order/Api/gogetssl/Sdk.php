<?php

declare(strict_types=1);

namespace App\Services\Order\Api\gogetssl;

use App\Bootstrap\ApiExceptions;
use App\Models\CaLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class Sdk
{
    /**
     * 获取产品价格
     */
    public function getProduct(int $productId): array
    {
        return $this->call('get', '/products/ssl/'.$productId);
    }

    /**
     * 申请证书
     */
    public function new(array $data): array
    {
        return $this->call('post', '/orders/add_ssl_order', $data);
    }

    /**
     * 续费证书
     */
    public function renew(array $data): array
    {
        return $this->call('post', '/orders/add_ssl_renew_order', $data);
    }

    /**
     * 添加 SAN
     */
    public function addSans(array $data): array
    {
        return $this->call('post', '/orders/add_ssl_san_order', $data);
    }

    /**
     * 重签证书
     */
    public function reissue(string $orderId, array $data): array
    {
        return $this->call('post', '/orders/ssl/reissue/'.$orderId, $data);
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string $orderId, string $domain, string $newMethod): array
    {
        $data['domain'] = $domain;
        $data['new_method'] = $newMethod;

        return $this->call('post', '/orders/ssl/change_validation_method/'.$orderId, $data);
    }

    /**
     * 多域名修改验证方法
     */
    public function batchUpdateDCV(string $orderId, string $domains, string $newMethods): array
    {
        $data['domains'] = $domains;
        $data['new_methods'] = $newMethods;

        return $this->call('post', '/orders/ssl/change_domains_validation_method/'.$orderId, $data);
    }

    /**
     * 重新验证
     */
    public function revalidate(string $orderId, string $domain): array
    {
        $data['domain'] = $domain;

        return $this->call('post', '/orders/ssl/revalidate/'.$orderId, $data);
    }

    /**
     * 重新发送验证邮件
     */
    public function resend(string $orderId, string $domain): array
    {
        $data['domain'] = $domain;

        return $this->call('post', '/orders/ssl/resend/'.$orderId, $data);
    }

    /**
     * 获取订单信息
     */
    public function getStatus(string $orderId): array
    {
        return $this->call('get', '/orders/status/'.$orderId);
    }

    /**
     * 取消证书
     */
    public function cancel(string $orderId): array
    {
        $data['order_id'] = $orderId;
        $data['reason'] = 'Other';

        return $this->call('post', '/orders/cancel_ssl_order', $data);
    }

    protected function call(string $method, string $uri, array $data = []): array
    {
        $apiUrl = get_system_setting('ca', 'gogetsslUrl');
        $username = get_system_setting('ca', 'gogetsslUsername');
        $password = get_system_setting('ca', 'gogetsslPassword');

        if (! $apiUrl || ! $username || ! $password) {
            return ['code' => 0, 'msg' => 'CA 接口配置错误'];
        }

        $url = $apiUrl.$uri;
        $key = $this->getKey($apiUrl, $username, $password);

        if (! $key) {
            return ['code' => 0, 'msg' => 'CA 接口连接失败'];
        }

        $client = new Client;
        try {
            $response = $client->request($method, $url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'query' => [
                    'auth_key' => $key,
                ],
                'form_params' => $data,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            app(ApiExceptions::class)->logException($e);

            return ['code' => 0, 'msg' => 'CA 接口连接失败'];
        }

        $result = json_decode($response->getBody()->getContents(), true);

        $httpStatusCode = $response->getStatusCode();

        CaLog::create([
            'url' => $apiUrl,
            'api' => $uri,
            'params' => $data,
            'response' => $result,
            'status_code ' => $httpStatusCode,
            'status' => intval($result['success'] ?? 0) === 1 ? 1 : 0,
        ]);

        // http 状态码 200 为成功
        if ($httpStatusCode == 200) {
            if (isset($result['success']) && $result['success']) {
                return ['code' => 1, 'data' => $result ?? null];
            }

            $error = [
                'code' => 0,
                'msg' => 'Unknown error. Please contact the administrator.',
            ];

            if (isset($result['error']) && $result['error']) {
                // 取消订单时，如果订单已被取消，返回成功
                if ($uri == '/orders/cancel_ssl_order' && str_contains($result['message'], 'already')) {
                    return ['code' => 1, 'data' => null];
                }

                if (! str_contains($result['message'], 'auth_key') && ! str_contains($result['message'], 'balance')) {
                    $error['msg'] = $result['message'];
                }
            }

            return $error;
        } else {
            return ['code' => 0, 'msg' => 'Http status code '.$httpStatusCode];
        }
    }

    private function getKey(string $apiUrl, string $username, string $password): string
    {
        $cacheKey = 'gogetssl_key';
        $key = Cache::get($cacheKey);
        if ($key) {
            return $key;
        }

        $url = $apiUrl.'/auth';
        $data = [
            'user' => $username,
            'pass' => $password,
        ];

        $client = new Client;
        try {
            $response = $client->request('post', $url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $data,
            ]);
        } catch (GuzzleException $e) {
            app(ApiExceptions::class)->logException($e);

            return '';
        }

        $result = json_decode($response->getBody()->getContents(), true);

        $key = $result['key'] ?? '';

        try {
            Cache::set($cacheKey, $key, 3600 * 24 * 365);
        } catch (InvalidArgumentException $e) {
            app(ApiExceptions::class)->logException($e);

            return '';
        }

        return $key;
    }
}
