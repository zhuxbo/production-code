<?php

namespace App\Http\Controllers\User;

use App\Http\Requests\Product\IndexRequest;
use App\Models\Product;
use App\Services\Order\Utils\OrderUtil;

class ProductController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取产品列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Product::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($query) use ($validated) {
                $query->where('name', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('code', 'like', "%{$validated['quickSearch']}%");
            });
        }
        if (isset($validated['domains'])) {
            if ($validated['domains'] === 'mixed') {
                $query->where('total_max', '>', 1)->where('standard_max', '>', 1)->where('wildcard_max', '>', 1);
            }
            if ($validated['domains'] === 'multiple') {
                $query->where(function ($query) {
                    $query->orWhere('standard_max', '>', 1)->orWhere('wildcard_max', '>', 1);
                });
            }
            if ($validated['domains'] === 'single') {
                $query->where('total_max', 1);
            }
        }
        if (! empty($validated['brand'])) {
            $query->where('brand', $validated['brand']);
        }
        if (! empty($validated['encryption_standard'])) {
            $query->where('encryption_standard', $validated['encryption_standard']);
        }
        if (! empty($validated['validation_type'])) {
            $query->where('validation_type', $validated['validation_type']);
        }
        if (! empty($validated['name_type'])) {
            $query->where(function ($query) use ($validated) {
                $query->whereJsonContains('common_name_types', $validated['name_type'])
                    ->orWhereJsonContains('alternative_name_types', $validated['name_type']);
            });
        }

        $total = $query->where('status', 1)->count();
        $items = $query->select(['id', 'name', 'brand', 'periods', 'encryption_standard', 'validation_type',
            'common_name_types', 'alternative_name_types', 'weight', 'remark', 'refund_period'])
            ->orderBy('weight', 'asc')
            ->orderBy('id', 'asc')
            ->where('status', 1)
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        // 遍历查询结果并获取会员价格
        foreach ($items as $item) {
            // 获取最小的period
            $period = min($item->periods);
            $price = OrderUtil::getMinPrice($this->guard->user()->id, $item->id, (int) $period);
            $price['period'] = $period;
            $item->price = $price;
        }

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 获取产品资料
     */
    public function show($id): void
    {
        $product = Product::where('status', 1)->find($id);
        if (! $product) {
            $this->error('产品不存在');
        }

        // 获取会员价格
        $price = [];
        foreach ($product->periods as $period) {
            $price[$period] = array_filter(OrderUtil::getMinPrice($this->guard->user()->id, $product->id, (int) $period));
        }
        $product->price = $price;

        $product->makeHidden([
            'api_id',
            'source',
            'cost',
            'created_at',
            'updated_at',
        ]);

        $this->success($product->toArray());
    }
}
