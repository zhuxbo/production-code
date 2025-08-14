<?php

declare(strict_types=1);

namespace App\Services\Order\Api\trustasia;

use App\Bootstrap\ApiExceptions;
use App\Models\CaLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Sdk
{
    /**
     * 申请证书
     */
    public function new(string|int $product_id, array $params): array
    {
        $result = $this->call('POST', '/orders/'.$product_id, $params);

        if (isset($result['msg']) && $result['msg'] === '备用订单 ID 重复') {
            $getApiIdResult = $this->getOrderIdByAlternativeOrderId($params['alternative_order_id']);
            if ($getApiIdResult['code'] === 1 && $getApiIdResult['data']['order_id']) {
                return [
                    'data' => [
                        'id' => $getApiIdResult['data']['order_id'],
                    ],
                    'code' => 1,
                ];
            }
        }

        return $result;
    }

    /**
     * 获取订单信息
     */
    public function getOrder(string $order_id): array
    {
        return $this->call('GET', '/orders/'.$order_id);
    }

    /**
     * 获取证书信息
     */
    public function getCert(string $cert_id): array
    {
        return $this->call('GET', '/certs/'.$cert_id);
    }

    /**
     * 通过备用id获取订单id
     */
    public function getOrderIdByAlternativeOrderId(string $alternative_order_id): array
    {
        return $this->call('GET', '/orders/alternate/'.$alternative_order_id);
    }

    /**
     * 修改域名验证方式
     */
    public function updateDCV(string $order_id, array $params): array
    {
        return $this->call('PUT', '/orders/'.$order_id.'/dcv-method', $params);
    }

    /**
     * 重新验证域名
     */
    public function revalidate(string $order_id): array
    {
        return $this->call('PUT', '/orders/'.$order_id.'/dcv-completed');
    }

    /**
     * 取消订单
     */
    public function cancel(string $order_id): array
    {
        return $this->call('PUT', '/orders/'.$order_id.'/cancel');
    }

    /**
     * 提交接口请求
     */
    private function call(string $method, string $uri, array $params = []): array
    {
        $apiUrl = get_system_setting('ca', 'trustasiaUrl');
        $keyId = get_system_setting('ca', 'trustasiaKeyId');
        $authKey = get_system_setting('ca', 'trustasiaAuthKey');

        if (! $apiUrl || ! $keyId || ! $authKey) {
            return ['code' => 0, 'msg' => 'CA 接口配置错误'];
        }

        $client = new Client;

        try {
            $response = $client->request($method, $apiUrl.$uri, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-CC-Key-ID' => $keyId,
                    'X-CC-Auth-Key' => $authKey,
                ],
                'body' => json_encode($params),
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
            'params' => $params,
            'response' => $result,
            'status_code ' => $httpStatusCode,
            'status' => strtolower($result['code'] ?? '') === 'success' ? 1 : 0,
        ]);

        if ($httpStatusCode != 200) {
            return ['code' => 0, 'msg' => $this->convertMessageToCN($result['code'] ?? '')];
        }

        return ['code' => 1, 'data' => $result['data'] ?? null];
    }

    /**
     * 转换返回信息为中文
     */
    private function convertMessageToCN(string $message): string
    {
        $map = [
            'success' => '请求成功',
            'invalid_parameters' => '参数无效',
            'permission_denied' => '系统内部错误，请联系管理员', // API 请求未经过鉴权
            'unauthenticated_request' => '系统内部错误，请联系管理员', // API 请求未经过鉴权
            'ip_limit' => '系统内部错误，请联系管理员', // IP 不在白名单中
            'request_too_fast' => '请求太频繁，请稍后再试',
            'service_internal_error' => '服务器内部错误',
            'service_busy' => '服务器繁忙，请稍后再试',
            'api_key_disabled' => '系统内部错误，请联系管理员', // OpenAPI 密钥已经被禁用
            'order_not_found' => '未找到订单',
            'product_not_found' => '未找到产品',
            'invalid_domain' => '无效的域名',
            'invalid_sans' => '无效的备用名称',
            'invalid_csr' => '无效的 CSR',
            'invalid_dcv_method' => '无效的 DCV 信息',
            'invalid_organization_info' => '无效的组织信息',
            'invalid_user_info' => '无效的用户信息',
            'invalid_cert_format' => '无效的证书格式',
            'invalid_alias' => '无效的别名',
            'invalid_password' => '无效的密码',
            'invalid_private_key' => '无效的私钥',
            'invalid_cert' => '无效的证书',
            'private_key_cert_not_match' => '公私钥不匹配',
            'dcv_not_completed' => '域名验证尚未完成',
            'duplicate_alternative_order_id' => '备用订单 ID 重复',
            'order_need_revoke' => '订单需要吊销',
            'cancel_not_allowed' => '订单不允许取消',
            'insufficient_balance' => '系统内部错误，请联系管理员', // 余额不足
        ];

        return $map[$message] ?? '系统未知错误，请联系管理员';
    }
}
