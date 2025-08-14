<?php

declare(strict_types=1);

namespace App\Services\Order\Api;

use App\Models\Order;
use App\Traits\ApiResponse;

class Api
{
    use ApiResponse;

    /**
     * 获取产品
     */
    public function getProducts(string $source = 'default', string $brand = '', string $code = ''): array
    {
        $api = $this->getSourceApi($source);
        $this->checkMethodExists($api, 'getProducts');

        return $this->handleResult($api->getProducts($brand, $code));
    }

    /**
     * 获取订单
     */
    public function getOrders(string $source = 'default', int $page = 1, int $pageSize = 100, $status = 'active'): array
    {
        $api = $this->getSourceApi($source);
        $this->checkMethodExists($api, 'getOrders');

        return $this->handleResult($api->getOrders($page, $pageSize, $status));
    }

    /**
     * 购买SSL
     */
    public function new(array $data): array
    {
        $source = $data['source'] ?? '';
        $api = $this->getSourceApi($source);
        $this->checkMethodExists($api, 'new');

        return $this->handleResult($api->new($data));
    }

    /**
     * 续订SSL
     */
    public function renew(array $data): array
    {
        $source = $data['source'] ?? '';
        $api = $this->getSourceApi($source);
        $this->checkMethodExists($api, 'renew');

        return $this->handleResult($api->renew($data));
    }

    /**
     * 重新颁发
     */
    public function reissue(array $data): array
    {
        $source = $data['source'] ?? '';
        $api = $this->getSourceApi($source);
        $this->checkMethodExists($api, 'reissue');

        return $this->handleResult($api->reissue($data));
    }

    /**
     * 获取订单信息
     */
    public function get(int $orderId): array
    {
        $order = $this->findOrder($orderId);
        $api = $this->getSourceApi($order->product->source ?? 'default');
        $this->checkMethodExists($api, 'get');

        return $this->handleResult($api->get($order->latestCert->api_id));
    }

    /**
     * 取消订单
     */
    public function cancel(int $orderId): array
    {
        $order = $this->findOrder($orderId);
        $api = $this->getSourceApi($order->product->source ?? 'default');
        $this->checkMethodExists($api, 'cancel');

        return $this->handleResult($api->cancel($order->latestCert->api_id, $order->latestCert->toArray()));
    }

    /**
     * 重新验证
     */
    public function revalidate(int $orderId): array
    {
        $order = $this->findOrder($orderId);
        $api = $this->getSourceApi($order->product->source ?? 'default');
        $this->checkMethodExists($api, 'revalidate');

        return $this->handleResult($api->revalidate($order->latestCert->api_id, $order->latestCert->toArray()));
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(int $orderId, string $method): array
    {
        $order = $this->findOrder($orderId);
        $api = $this->getSourceApi($order->product->source ?? 'default');
        $this->checkMethodExists($api, 'updateDCV');

        return $this->handleResult($api->updateDCV($order->latestCert->api_id, $method, $order->latestCert->toArray()));
    }

    /**
     * 删除DCV验证未通过域名
     */
    public function removeMdcDomain(int $orderId): array
    {
        $order = $this->findOrder($orderId);
        $api = $this->getSourceApi($order->product->source ?? 'default');
        $this->checkMethodExists($api, 'removeMdcDomain');

        return $this->handleResult($api->removeMdcDomain($order->latestCert->api_id, $order->latestCert->toArray()));
    }

    /**
     * 设置品牌
     */
    private function getSourceApi(string $source): mixed
    {
        ! $source && $this->error('来源不能为空');

        $class = __NAMESPACE__.'\\'.strtolower($source).'\\Api';
        if (! class_exists($class)) {
            $class = __NAMESPACE__.'\\'.'default\\Api';
        }

        return new $class;
    }

    /**
     * 检查方法是否存在
     */
    private function checkMethodExists(object $instance, string $method): void
    {
        if (! method_exists($instance, $method)) {
            $this->error('不支持此方法');
        }
    }

    /**
     * 根据API ID查找订单
     */
    private function findOrder(int $orderId): Order
    {
        $order = Order::with(['product', 'latestCert'])
            ->whereHas('user')
            ->whereHas('product')
            ->whereHas('latestCert')
            ->find($orderId);

        if (! $order) {
            $this->error('未找到对应的订单');
        }

        return $order;
    }

    /**
     * 统一处理返回结果
     */
    private function handleResult(array $result): array
    {
        if ($result['code'] !== 1) {
            $errors = config('app.debug') ? ($result['errors'] ?? null) : null;
            $this->error($result['msg'] ?? 'CA 接口调用失败', $errors);
        }

        return $result;
    }
}
