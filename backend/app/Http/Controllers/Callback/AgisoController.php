<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Http\Controllers\Controller;
use App\Models\Agiso;

class AgisoController extends Controller
{
    public function index(): void
    {
        $params = request()->all();
        $requiredParams = ['timestamp', 'aopic', 'sign', 'json'];
        if (! $this->hasRequiredParams($params, $requiredParams)) {
            $this->error('Missing required parameters');
        }

        if ($this->isValidSignature($params)) {
            $params['data'] = json_decode($params['json'], true);

            if (! isset($params['data']['Tid'])) {
                $this->error('Missing Tid');
            }

            if ($this->isRepeatOrder($params)) {
                $this->error('Repeat order');
            }

            $this->processOrder($params);
        } else {
            $this->error('Invalid signature');
        }
    }

    private function hasRequiredParams(array $params, array $requiredParams): bool
    {
        foreach ($requiredParams as $param) {
            if (! isset($params[$param])) {
                return false;
            }
        }

        return true;
    }

    private function isValidSignature(array $params): bool
    {
        $appSecret = get_system_setting('site', 'agisoAppSecret', '');
        $str = $appSecret.'json'.$params['json'].'timestamp'.$params['timestamp'].$appSecret;
        $create_sign = md5($str);

        return strcasecmp($params['sign'], $create_sign) == 0;
    }

    private function isRepeatOrder(array $params): bool
    {
        if (isset($params['data']['RefundId'])) {
            $order = Agiso::where([
                'type' => $params['aopic'],
                'refund_id' => $params['data']['RefundId'],
            ])->first();

            return $order !== null;
        }

        $order = Agiso::where([
            'type' => $params['aopic'],
            'tid' => $params['data']['Tid'],
        ])->first();

        return $order !== null;
    }

    private function processOrder(array $params): void
    {
        $platform = $params['fromPlatform'] ?? $params['data']['Platform'] ?? null;

        match ($platform) {
            'PddAlds' => $this->processPinduoduoOrder($params),
            'TbAlds' => $this->processTaobaoOrder($params),
            default => $this->error('Invalid platform'),
        };
    }

    private function processPinduoduoOrder(array $params): void
    {
        $price = '0.00';
        $count = 0;

        foreach (($params['data']['ItemList'] ?? []) as $item) {
            $goods_price = bcadd((string) $item['goods_price'], '0.00', 2);
            $goods_price = $this->getGiftPrice($goods_price);

            $goods_price = bcmul($goods_price, (string) $item['goods_count'], 2);

            $price = bcadd($goods_price, $price, 2);
            $count += (int) $item['goods_count'];
        }

        $price !== '0.00'
        && Agiso::create([
            'platform' => $params['fromPlatform'] ?? $params['data']['Platform'] ?? null,
            'sign' => $params['sign'],
            'timestamp' => $params['timestamp'],
            'type' => $params['aopic'],
            'data' => $params['json'],
            'tid' => $params['data']['Tid'],
            'price' => $price,
            'count' => $count,
            'amount' => $params['data']['PayAmount'] ?? null,
        ]);

        $this->success();
    }

    private function processTaobaoOrder(array $params): void
    {
        $price = '0.00';
        $amount = '0.00';
        $count = 0;

        foreach (($params['data']['Orders'] ?? []) as $order) {
            $amount = bcadd($amount, (string) $order['Payment'], 2);

            // 商品单价 如果有赠送金额则替换
            $goods_price = bcadd((string) $order['Price'], '0.00', 2);
            $goods_price = $this->getGiftPrice($goods_price);

            // 商品价格 = 商品单价 * 商品数量
            $goods_price = bcmul($goods_price, (string) $order['Num'], 2);

            $price = bcadd($goods_price, $price, 2);
            $count += (int) $order['Num'];
        }

        $price !== '0.00'
        && Agiso::create([
            'platform' => $params['fromPlatform'] ?? $params['data']['Platform'] ?? null,
            'sign' => $params['sign'],
            'timestamp' => $params['timestamp'],
            'type' => $params['aopic'],
            'data' => $params['json'],
            'tid' => $params['data']['Tid'],
            'status' => $params['data']['Status'] ?? null,
            'price' => $price,
            'count' => $count,
            'amount' => $amount,
        ]);

        $this->success();
    }

    private function getGiftPrice(string $price): string
    {
        $gift = get_system_setting('site', 'agisoGift', []);

        return (string) ($gift[$price] ?? $price);
    }
}
