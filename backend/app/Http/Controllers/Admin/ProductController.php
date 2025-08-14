<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Product\CostRequest;
use App\Http\Requests\Product\GetIdsRequest;
use App\Http\Requests\Product\ImportRequest;
use App\Http\Requests\Product\IndexRequest;
use App\Http\Requests\Product\StoreRequest;
use App\Http\Requests\Product\UpdateRequest;
use App\Models\Product;
use App\Services\Order\Action;

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
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $total = $query->count();
        $items = $query->orderBy('weight')
            ->orderBy('id')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $this->success([
            'items' => $items,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 添加产品
     */
    public function store(StoreRequest $request): void
    {
        $product = Product::create($request->validated());

        if (! $product->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取产品资料
     */
    public function show($id): void
    {
        $product = Product::find($id);
        if (! $product) {
            $this->error('产品不存在');
        }

        $this->success($product->toArray());
    }

    /**
     * 批量获取产品资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $products = Product::whereIn('id', $ids)->get();
        if ($products->isEmpty()) {
            $this->error('产品不存在');
        }

        $this->success($products->toArray());
    }

    /**
     * 更新产品资料
     */
    public function update(UpdateRequest $request, $id): void
    {
        $product = Product::find($id);
        if (! $product) {
            $this->error('产品不存在');
        }

        $product->fill($request->validated());
        $product->save();

        $this->success();
    }

    /**
     * 删除产品
     */
    public function destroy($id): void
    {
        $product = Product::find($id);
        if (! $product) {
            $this->error('产品不存在');
        }

        $product->delete();
        $this->success();
    }

    /**
     * 批量删除产品
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $products = Product::whereIn('id', $ids)->get();
        if ($products->isEmpty()) {
            $this->error('产品不存在');
        }

        Product::destroy($ids);
        $this->success();
    }

    /**
     * 导入产品
     */
    public function import(ImportRequest $request): void
    {
        $validated = $request->validated();
        $source = $validated['source'] ?? 'default';
        $brand = $validated['brand'] ?? '';
        $apiId = $validated['apiId'] ?? '';
        $forceUpdate = $validated['forceUpdate'] ?? false;

        (new Action)->importProduct($source, $brand, $apiId, $forceUpdate);
    }

    /**
     * 获取产品成本信息
     */
    public function getCost(int $id): void
    {
        $product = Product::select(['periods', 'alternative_name_types', 'cost'])->find($id);
        if (! $product) {
            $this->error('产品不存在');
        }

        $this->success($product->toArray());
    }

    /**
     * 更新产品成本信息
     */
    public function updateCost(CostRequest $request, int $id): void
    {
        $product = Product::find($id);
        if (! $product) {
            $this->error('产品不存在');
        }

        // 获取验证后的数据
        $validated = $request->validated();

        // 更新产品的 cost 字段
        $product->cost = $validated['cost'];
        $product->save();

        $this->success();
    }

    /**
     * 获取来源列表
     */
    public function getSourceList(): void
    {
        $sources = get_system_setting('ca', 'sources');
        $result = [];

        // 判断是 key => value 数组还是 序列化数组
        if (is_array($sources)) {
            foreach ($sources as $key => $value) {
                if (is_int($key)) {
                    // 如果是数字索引，使用值作为key和label
                    $result[] = [
                        'value' => strtolower($value),
                        'label' => ucfirst(strtolower($value)),
                    ];
                } else {
                    // 如果是关联数组，使用key作为value，value作为label
                    $result[] = [
                        'value' => strtolower($key),
                        'label' => $value,
                    ];
                }
            }
        } else {
            // 默认值
            $result = [
                [
                    'value' => 'default',
                    'label' => 'Default',
                ],
            ];
        }

        $this->success($result);
    }
}
