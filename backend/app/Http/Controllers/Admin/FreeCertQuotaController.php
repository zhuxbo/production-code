<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\FreeCertQuota\IndexRequest;
use App\Http\Requests\FreeCertQuota\StoreRequest;
use App\Models\FreeCertQuota;

class FreeCertQuotaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取免费证书配额列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = FreeCertQuota::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereHas('user', function ($userQuery) use ($validated) {
                    $userQuery->where('username', 'like', "%{$validated['quickSearch']}%");
                })
                    ->orWhere('type', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('order_id', 'like', "%{$validated['quickSearch']}%");
            });
        }
        if (! empty($validated['username'])) {
            $query->whereHas('user', function ($userQuery) use ($validated) {
                $userQuery->where('username', $validated['username']);
            });
        }
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }

        $total = $query->count();
        $items = $query->with([
            'user' => function ($query) {
                $query->select(['id', 'username']);
            },
        ])
            ->select([
                'id', 'user_id', 'type', 'order_id', 'quota', 'quota_before', 'quota_after', 'created_at',
            ])
            ->orderBy('id', 'desc')
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
     * 添加免费证书配额
     */
    public function store(StoreRequest $request): void
    {
        $validated = $request->validated();

        // 获取用户当前配额
        $user = \App\Models\User::find($validated['user_id']);
        if (! $user) {
            $this->error('用户不存在');
        }

        $quotaBefore = $user->free_cert_quota;
        $quotaChange = $validated['quota'];
        $quotaAfter = $quotaBefore + $quotaChange;

        // 更新用户配额
        $user->free_cert_quota = $quotaAfter;
        $user->save();

        // 创建配额记录
        $validated['quota_before'] = $quotaBefore;
        $validated['quota_after'] = $quotaAfter;

        $freeCertQuota = FreeCertQuota::create($validated);

        if (! $freeCertQuota->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }
}
