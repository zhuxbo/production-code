<?php

declare(strict_types=1);

namespace App\Services\Order\Api\default;

use App\Utils\SnowFlake;

class Api
{
    private Sdk $sdk;

    public function __construct()
    {
        $this->sdk = new Sdk;
    }

    /**
     * 获取产品
     */
    public function getProducts(string $brand = '', string $code = ''): array
    {
        return $this->sdk->getProducts($brand, $code);
    }

    /**
     * 获取订单
     */
    public function getOrders(int $page = 1, int $pageSize = 100, $status = 'active'): array
    {
        return $this->sdk->getOrders($page, $pageSize, $status);
    }

    /**
     * 购买SSL
     */
    public function new($data): array
    {
        $params = $this->getNewSslParams($data);

        $result = $this->sdk->new($params);

        return $this->getResult($result);
    }

    /**
     * 续费SSL
     */
    public function renew($data): array
    {
        $params = $this->getNewSslParams($data);
        $params['order_id'] = $data['last_api_id'];

        $result = $this->sdk->renew($params);

        return $this->getResult($result);
    }

    /**
     * 重新颁发
     */
    public function reissue(array $data): array
    {
        $params['refer_id'] = $data['refer_id'];
        $params['order_id'] = $data['last_api_id'];
        $params['csr'] = $data['csr'];
        $params['domains'] = $data['alternative_names'];
        $params['validation_method'] = $data['dcv']['method'];

        if (isset($data['unique_value'])) {
            $params['unique_value'] = $data['unique_value'] ?: $this->generateUniqueValue();
        }

        $result = $this->sdk->reissue($params);

        return $this->getResult($result);
    }

    /**
     * 整理购买SSL参数
     */
    protected function getNewSslParams(array $data): array
    {
        $params['refer_id'] = $data['refer_id'];
        $params['plus'] = $data['plus'];
        $params['product_code'] = $data['product_api_id'];
        $params['period'] = $data['period'];
        $params['csr'] = $data['csr'];
        $params['domains'] = $data['alternative_names'];
        $params['validation_method'] = $data['dcv']['method'];

        if (isset($data['unique_value'])) {
            $params['unique_value'] = $data['unique_value'] ?: $this->generateUniqueValue();
        }

        $params['contact'] = $data['contact'];

        if (isset($data['organization'])) {
            $params['organization'] = $data['organization'];
        }

        return $params;
    }

    /**
     * 封装返回结果
     */
    protected function getResult(array $result): array
    {
        $apiId = $result['data']['order_id'] ?? '';

        if ($result['code'] === 1 && $apiId) {
            return [
                'data' => [
                    'api_id' => $apiId,
                    'cert_apply_status' => $result['data']['cert_apply_status'] ?? 0,
                    'dcv' => $result['data']['dcv'],
                    'validation' => $result['data']['validation'],
                ],
                'code' => 1,
            ];
        }

        return $result;
    }

    /**
     * 获取订单信息
     */
    public function get(string|int $apiId): array
    {
        return $this->sdk->get($apiId);
    }

    /**
     * 取消订单
     */
    public function cancel(string|int $apiId): array
    {
        return $this->sdk->cancel($apiId);
    }

    /**
     * 重新验证
     */
    public function revalidate(string|int $apiId): array
    {
        return $this->sdk->revalidate($apiId);
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string|int $apiId, string $method): array
    {
        return $this->sdk->updateDCV($apiId, $method);
    }

    /**
     * 生成唯一值 Unique Value
     */
    protected function generateUniqueValue(): string
    {
        return 'cn'.SnowFlake::generateParticle();
    }
}
