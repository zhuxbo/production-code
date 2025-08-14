<?php

namespace App\Http\Controllers\User;

use App\Http\Requests\Order\GetIdsRequest;
use App\Http\Requests\Order\IndexRequest;
use App\Models\Order;
use App\Services\Order\Action;
use Throwable;

class OrderController extends BaseController
{
    protected Action $action;

    public function __construct()
    {
        parent::__construct();

        $this->guard->id() || $this->error('用户不存在');
        $this->action = new Action($this->guard->id());
    }

    use \App\Http\Traits\OrderController;

    /**
     * 获取订单列表
     *
     * @throws Throwable
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Order::query();

        $statusSet = $validated['statusSet'] ?? 'activating';
        // 活动中的状态
        if ($statusSet === 'activating') {
            $query->whereHas('latestCert', function ($latestCertQuery) {
                $latestCertQuery->whereIn('status', ['unpaid', 'pending', 'processing', 'active', 'approving', 'cancelling']);
            });
        }
        // 已存档的状态
        if ($statusSet === 'archived') {
            $query->whereHas('latestCert', function ($latestCertQuery) {
                $latestCertQuery->whereIn('status', ['cancelled', 'renewed', 'replaced', 'reissued', 'expired', 'revoked', 'failed']);
            });
        }

        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('id', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('remark', 'like', "%{$validated['quickSearch']}%")
                    ->orWhereHas('product', function ($productQuery) use ($validated) {
                        $productQuery->where('name', 'like', "%{$validated['quickSearch']}%");
                    })
                    ->orWhereHas('latestCert', function ($latestCertQuery) use ($validated) {
                        $latestCertQuery->where('common_name', 'like', "%{$validated['quickSearch']}%")
                            ->orWhere('alternative_names', 'like', "%{$validated['quickSearch']}%");
                    });
            });
        }
        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
        }
        if (! empty($validated['period'])) {
            $query->where('period', $validated['period']);
        }
        if (! empty($validated['amount'])) {
            if (isset($validated['amount'][0]) && isset($validated['amount'][1])) {
                $query->whereBetween('amount', $validated['amount']);
            } elseif (isset($validated['amount'][0])) {
                $query->where('amount', '>=', $validated['amount'][0]);
            } elseif (isset($validated['amount'][1])) {
                $query->where('amount', '<=', $validated['amount'][1]);
            }
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }
        if (! empty($validated['product_name'])) {
            $query->whereHas('product', function ($productQuery) use ($validated) {
                $productQuery->where('name', 'like', "%{$validated['product_name']}%");
            });
        }
        if (! empty($validated['domain'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('common_name', 'like', "%{$validated['domain']}%")
                    ->orWhere('alternative_names', 'like', "%{$validated['domain']}%");
            });
        }
        if (! empty($validated['channel'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('channel', $validated['channel']);
            });
        }
        if (! empty($validated['action'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('action', $validated['action']);
            });
        }
        if (! empty($validated['expires_at'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->whereBetween('expires_at', $validated['expires_at']);
            });
        }
        if (! empty($validated['status'])) {
            $query->whereHas('latestCert', function ($latestCertQuery) use ($validated) {
                $latestCertQuery->where('status', $validated['status']);
            });
        }

        $total = $query->count();
        $items = $query->with([
            'product' => function ($query) {
                $query->select(['id', 'name', 'refund_period']);
            }, 'latestCert' => function ($query) {
                $query->select(['id', 'common_name', 'channel', 'action', 'dcv', 'status', 'amount']);
            },
        ])
            ->select(['id', 'product_id', 'latest_cert_id', 'period', 'amount', 'created_at'])
            ->orderBy('latest_cert_id', 'desc')
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
     * 获取订单资料
     */
    public function show($id): void
    {
        $order = Order::with([
            'product' => function ($query) {
                $query->select(['id', 'name', 'ca', 'refund_period', 'validation_methods', 'validation_type', 'common_name_types', 'alternative_name_types']);
            }, 'latestCert',
        ])->find($id);

        if (! $order) {
            $this->error('订单不存在');
        }

        $order->makeHidden([
            'user_id',
            'plus',
            'admin_remark',
            'latestCert.last_cert_id',
            'latestCert.api_id',
            'latestCert.params',
            'latestCert.csr_md5',
        ]);

        $this->success($order->toArray());
    }

    /**
     * 批量获取订单资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $orders = Order::whereIn('id', $ids)
            ->with([
                'product' => function ($query) {
                    $query->select(['id', 'name', 'ca', 'refund_period', 'validation_methods', 'validation_type', 'common_name_types', 'alternative_name_types']);
                }, 'latestCert',
            ])
            ->get();

        foreach ($orders as $order) {
            $order->makeHidden([
                'user_id',
                'plus',
                'latestCert.last_cert_id',
                'latestCert.api_id',
                'latestCert.channel',
                'latestCert.params',
                'latestCert.csr_md5',
            ]);
        }

        if ($orders->isEmpty()) {
            $this->error('订单不存在');
        }

        $this->success($orders->toArray());
    }

    /**
     * 申请
     *
     * @throws Throwable
     */
    public function new(): void
    {
        $params = request()->post();
        $params['action'] = 'new';
        $params['channel'] = 'web';
        $this->action->new($params);
    }

    /**
     * 批量申请
     *
     * @throws Throwable
     */
    public function batchNew(): void
    {
        $params = request()->post();
        $params['action'] = 'new';
        $params['channel'] = 'web';
        $params['is_batch'] = true;
        $this->action->batchNew($params);
    }

    /**
     * 续费
     *
     * @throws Throwable
     */
    public function renew(): void
    {
        $params = request()->post();
        $params['action'] = 'renew';
        $params['channel'] = 'web';
        $this->action->renew($params);
    }

    /**
     * 重签
     *
     * @throws Throwable
     */
    public function reissue(): void
    {
        $params = request()->post();
        $params['action'] = 'reissue';
        $params['channel'] = 'web';
        $this->action->reissue($params);
    }
}
