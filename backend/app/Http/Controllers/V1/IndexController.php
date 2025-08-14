<?php

namespace App\Http\Controllers\V1;

use App\Exceptions\ApiResponseException;
use App\Http\Controllers\Controller;
use App\Http\Traits\OrderIdCompatTrait;
use App\Models\ApiToken;
use App\Models\Order;
use App\Models\Product;
use App\Services\Order\Action;
use App\Services\Order\Utils\OrderUtil;
use DB;
use Exception;
use Illuminate\Auth\TokenGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

class IndexController extends Controller
{
    use OrderIdCompatTrait;

    protected Order $model;

    protected Action $action;

    protected int $user_id;

    protected TokenGuard $guard;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        // @phpstan-ignore assign.propertyType
        $this->guard = Auth::guard('api');

        /** @var ApiToken $apiToken */
        $apiToken = $this->guard->user();

        $this->user_id = $apiToken->user_id;
        $this->model = new Order;
        $this->action = new Action($this->user_id);
    }

    /**
     * 获取产品列表
     */
    public function getProducts(): void
    {
        $brand = $this->request->input('brand', '');
        $code = $this->request->input('code', '');

        $where = [];
        $brand && $where[] = ['brand', '=', $brand];
        $code && $where[] = ['code', 'like', '%'.$code.'%'];
        $where[] = ['status', '=', 1];

        $res = Product::where($where)->orderBy('weight', 'ASC')->get();
        $res->makeHidden(['id', 'api_id', 'status', 'cost', 'created_at', 'updated_at']);

        // 遍历查询结果并获取会员价格
        $data = [];
        foreach ($res as $item) {
            $cost = [];
            foreach ($item->periods as $period) {
                $minPrice = OrderUtil::getMinPrice($this->user_id, $item->id, (int) $period);
                $period = (string) $period;
                $cost['price'][$period] = $minPrice['price'];
                if (in_array('standard', $item->alternative_name_types)) {
                    $cost['alternative_standard_price'][$period] = $minPrice['alternative_standard_price'];
                }
                if (in_array('wildcard', $item->alternative_name_types)) {
                    $cost['alternative_wildcard_price'][$period] = $minPrice['alternative_wildcard_price'];
                }
            }
            $item = $item->toArray();
            $item['periods'] = array_map('intval', $item['periods']);
            $item['cost'] = $cost;
            $data[] = $item;
        }

        $this->success($data);
    }

    /**
     * 申请
     * [(string)refer_id,plus,pid,period,csr_generate,encryption,csr,auto_verify,
     *  validation_method,domains,administrator,organization]
     *
     * @throws Throwable
     */
    public function new(): void
    {
        $params = $this->request->all();

        $this->checkReferId($params['refer_id'] ?? '');

        $product = Product::where('code', $params['pid'] ?? null)->where('status', 1)->first();
        if (! $product) {
            $this->error('Product not found');
        }
        $params['product_id'] = $product->id;

        // 转换V1参数格式为新系统格式
        $params = $this->convertV1Params($params);

        $params['action'] = 'new';
        $params['channel'] = 'api';

        try {
            DB::beginTransaction();

            $result = $this->getData('new', [$params]);

            $order_id = $result['data']['order_id'] ?? null;

            $this->getData('pay', [$order_id, true, boolval($params['auto_verify'] ?? 0)]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (isset($order_id)) {
            $order = Order::with(['latestCert'])->where('orders.id', $order_id)->first();
        }

        $this->success([
            'oid' => $order_id ?? '',
            'application_status' => $this->getProcessStatus($order->latestCert->cert_apply_status ?? 0),
            'dcv' => $order->latestCert->dcv ?? null,
            'validation' => $order->latestCert->validation ?? null,
        ]);
    }

    /**
     * 续费
     * [(string)refer_id,plus,oid,period,csr_generate,encryption,csr,auto_verify,
     *  validation_method,domains,administrator,organization]
     *
     * @throws Throwable
     */
    public function renew(): void
    {
        $params = $this->request->all();

        $this->checkReferId($params['refer_id'] ?? '');

        // 处理OID参数转换
        $this->processOrderIdParamInArray($params, 'oid');

        // 转换V1参数格式为新系统格式
        $params = $this->convertV1Params($params);

        $params['action'] = 'renew';
        $params['channel'] = 'api';

        try {
            DB::beginTransaction();

            $result = $this->getData('renew', [$params]);

            $order_id = $result['data']['order_id'] ?? '';

            $this->getData('pay', [$order_id, true, boolval($params['auto_verify'] ?? 0)]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (isset($order_id)) {
            $order = Order::with(['latestCert'])->where('orders.id', $order_id)->first();
        }

        $this->success([
            'oid' => $order_id ?? '',
            'application_status' => $this->getProcessStatus($order->latestCert->cert_apply_status ?? 0),
            'dcv' => $order->latestCert->dcv ?? null,
            'validation' => $order->latestCert->validation ?? null,
        ]);
    }

    /**
     * 重签
     * [(string)refer_id,oid,csr_generate,encryption,csr,auto_verify,
     *  validation_method,domains,organization]
     *
     * @throws Throwable
     */
    public function reissue(): void
    {
        $params = $this->request->all();

        $this->checkReferId($params['refer_id'] ?? '');

        // 处理OID参数转换
        $this->processOrderIdParamInArray($params, 'oid');

        // 转换V1参数格式为新系统格式
        $params = $this->convertV1Params($params);

        $params['action'] = 'reissue';
        $params['channel'] = 'api';

        try {
            DB::beginTransaction();

            $result = $this->getData('reissue', [$params]);

            $order_id = $result['data']['order_id'] ?? '';

            $order = Order::with(['latestCert'])->where('orders.id', $order_id)->where('user_id', $this->user_id)->first();

            if (! $order) {
                $this->error('Order not found');
            }

            $this->getData('pay', [$order_id, true, boolval($params['auto_verify'] ?? 0)]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if (isset($order_id)) {
            $order = Order::with(['latestCert'])->where('orders.id', $order_id)->first();
        }

        $this->success([
            'oid' => $order_id ?? '',
            'application_status' => $this->getProcessStatus($order->latestCert->cert_apply_status ?? 0),
            'dcv' => $order->latestCert->dcv ?? null,
            'validation' => $order->latestCert->validation ?? null,
        ]);
    }

    /**
     * 获取订单ID
     */
    public function getOrderIdByReferId(): void
    {
        $refer_id = $this->request->input('refer_id', '');

        $order = $this->model
            ->whereHas('latestCert', function ($query) use ($refer_id) {
                $query->where('refer_id', $refer_id);
            })
            ->where('user_id', $this->user_id)
            ->with(['latestCert'])
            ->first();

        if ($order) {
            $this->success(['oid' => $order->id]);
        } else {
            $this->error('Refer id not found');
        }
    }

    /**
     * 获取订单
     *
     * @throws Throwable
     */
    public function get(): void
    {
        $order_id = $this->processOrderIdParam('oid');

        $order = $this->model
            ->with(['latestCert'])
            ->where('orders.id', $order_id)
            ->where('user_id', $this->user_id)
            ->first();

        if (! $order) {
            $this->error('Order not found');
        }

        $cacheKey = 'api_get_'.$order_id;
        // 获取上次缓存的时间戳
        $lastTime = Cache::get($cacheKey);
        // 签发状态120秒 其他状态10秒 内不能重复调用接口
        if (! $lastTime) {
            // 待验证、待审批、已签发的订单同步
            if (in_array($order->latestCert->status, ['processing', 'approving', 'active'])) {
                $this->action->sync($order_id, true);
            }

            // 未支付订单支付
            if ($order->latestCert->status === 'unpaid') {
                try {
                    $this->action->pay($order_id);
                } catch (ApiResponseException) {
                }
            }

            // 待提交的订单提交
            if ($order->latestCert->status === 'pending') {
                try {
                    $this->action->commit($order_id);
                } catch (ApiResponseException) {
                }
            }

            // 重新查询
            $order = $this->model
                ->with(['latestCert'])
                ->where('orders.id', $order_id)
                ->where('user_id', $this->user_id)
                ->first();
        }

        // 更新缓存时间
        $cacheTime = $order->latestCert->status === 'active' ? 120 : 10;
        Cache::set($cacheKey, time(), $cacheTime);

        // 未支付 和 待提交 的订单状态改为处理中再返回
        if (in_array($order->latestCert->status, ['unpaid', 'pending'])) {
            $order->latestCert->status = 'processing';
        }

        $cert = $order->latestCert;

        // Laravel 不需要 hidden 和 visible，可以用 unset 替代
        $orderArray = $order->toArray();
        $certArray = $cert->toArray();

        // 保留需要的字段
        $orderData = array_intersect_key($orderArray, array_flip(['organization', 'contact', 'period_from', 'period_till']));
        $certData = array_intersect_key($certArray, array_flip([
            'vendor_id',
            'vendor_cert_id',
            'common_name',
            'alternative_names',
            'dcv',
            'validation',
            'csr',
            'cert',
            'intermediate_cert',
            'issued_at',
            'expires_at',
            'cert_apply_status',
            'domain_verify_status',
            'org_verify_status',
            'status',
        ]));

        $result = array_merge($orderData, $certData);

        // 转换为V1 API格式
        if (isset($result['organization']['registration_number'])) {
            $result['organization']['identification_number'] = $result['organization']['registration_number'];
            unset($result['organization']['registration_number']);
        }

        if (isset($result['contact'])) {
            $result['administrator'] = $result['contact'];
            unset($result['contact']);
            $result['administrator']['job'] = $result['administrator']['title'] ?? 'CEO';
            unset($result['administrator']['title']);
        }

        $result['issue_time'] = $result['issued_at'];
        $result['expiry_time'] = $result['expires_at'];

        $result['application_status'] = $this->getProcessStatus($result['cert_apply_status']);
        $result['dcv_status'] = $this->getProcessStatus($result['domain_verify_status']);
        $result['ov_status'] = $this->getProcessStatus($result['org_verify_status']);

        unset($result['issued_at']);
        unset($result['expires_at']);
        unset($result['cert_apply_status']);
        unset($result['domain_verify_status']);
        unset($result['org_verify_status']);

        $result = array_filter(
            $result,
            fn ($v) => $v !== null
        );

        $this->success($result);
    }

    /**
     * 取消订单
     *
     * @throws Throwable
     */
    public function cancel(): void
    {
        $order_id = $this->processOrderIdParam('oid');

        $order = Order::with(['latestCert', 'product'])
            ->where('orders.id', $order_id)
            ->where('user_id', $this->user_id)
            ->first();

        if (! $order) {
            $this->error('Order not found');
        }

        // 待支付订单删除
        if ($order->latestCert->status === 'unpaid') {
            try {
                $this->action->delete($order_id);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 0) {
                    $this->error($result['msg'], $result['errors'] ?? null);
                }
            }
        }

        // 待提交的订单取消
        if ($order->latestCert->status === 'pending') {
            // 取消前删除提交任务
            $this->action->deleteTask($order_id, 'commit');
            try {
                $this->action->cancelPending($order_id);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                if ($result['code'] === 0) {
                    $this->error($result['msg'], $result['errors'] ?? null);
                }
            }
        }

        // 重新查询 如果订单不存在或为已取消状态直接返回成功 否测继续取消
        $order = Order::with(['latestCert', 'product'])
            ->where('orders.id', $order_id)
            ->where('user_id', $this->user_id)
            ->first();

        if (! $order || $order->latestCert->status === 'cancelled') {
            $this->success();
        }

        $status = $order->latestCert->status;
        $refund_period = $order->product->refund_period ?? 0;

        if ($order->created_at->timestamp < time() - 86400 * $refund_period) {
            $this->error("Order cannot be cancelled after $refund_period days");
        }

        if ($status === 'cancelled') {
            $this->error('Order already cancelled');
        }
        if ($status === 'expired') {
            $this->error('Order has expired');
        }
        if ($status === 'renewed') {
            $this->error('Order has been renewed');
        }
        if ($status === 'reissued') {
            $this->error('Order has been reissued');
        }
        if ($status === 'revoked') {
            $this->error('Order has been revoked');
        }
        if ($status === 'failed') {
            $this->error('Order has failed');
        }

        if (in_array($status, ['processing', 'approving', 'active', 'cancelling'])) {
            // 取消前删除相关任务
            $this->action->deleteTask($order_id, 'sync,revalidate,cancel');

            // 立即取消
            $order->latestCert->update(['status' => 'cancelling']);
            $this->action->cancel($order_id);
        } else {
            $this->error('Order cannot be cancelled');
        }
    }

    /**
     * 重新验证
     */
    public function revalidate(): void
    {
        $order_id = $this->processOrderIdParam('oid');
        $this->action->revalidate($order_id);
    }

    /**
     * 更新 DCV
     */
    public function updateDCV(): void
    {
        $order_id = $this->processOrderIdParam('oid');
        $method = $this->request->input('method', '');

        $this->action->updateDCV($order_id, $method);
    }

    /**
     * 移除 MDC 域名
     */
    public function removeMdcDomain(): void
    {
        $order_id = $this->processOrderIdParam('oid');
        $this->action->removeMdcDomain($order_id);
    }

    /**
     * 下载证书
     */
    public function download(): void
    {
        $order_id = $this->processOrderIdParam('oid');
        $type = $this->request->input('type', 'all');

        $this->action->download($order_id, $type);
    }

    /**
     * 检测重复 refer_id
     */
    private function checkReferId(string $refer_id): void
    {
        if ($refer_id) {
            $order = $this->model
                ->whereHas('latestCert', function ($query) use ($refer_id) {
                    $query->where('refer_id', $refer_id);
                })
                ->where('user_id', $this->user_id)
                ->with(['latestCert'])
                ->first();

            if ($order) {
                $this->error('Refer id already exists');
            }
        }
    }

    /**
     * 获取数据
     *
     * @throws Exception
     */
    private function getData(string $action, array $params): array
    {
        try {
            $this->action->$action(...$params);
        } catch (ApiResponseException $e) {
            $result = $e->getApiResponse();
            if ($result['code'] === 0) {
                $this->error($result['msg'], $result['errors'] ?? null);
            }
        }

        return $result ?? [];
    }

    /**
     * 转换V1 API参数为新系统格式
     */
    private function convertV1Params(array $params): array
    {
        // 加密算法转换
        if (isset($params['encryption']['digest_alg'])) {
            $params['encryption']['signature_digest_alg'] = $params['encryption']['digest_alg'];
            unset($params['encryption']['digest_alg']);
        }

        // 管理员信息转换
        if (isset($params['administrator'])) {
            $params['contact'] = $params['administrator'];
            unset($params['administrator']);
            $params['contact']['title'] = $params['contact']['job'];
            unset($params['contact']['job']);
        }

        // 组织信息转换
        if (isset($params['organization']['identification_number'])) {
            $params['organization']['registration_number'] = $params['organization']['identification_number'];
            unset($params['organization']['identification_number']);
        }

        return $params;
    }

    /**
     * 健康检查接口
     */
    public function health(): void
    {
        $this->success([
            'status' => 'ok',
            'version' => 'v1',
            'timestamp' => time(),
        ]);
    }

    /**
     * 获取处理状态 - 转换为V1 API格式
     */
    protected function getProcessStatus(int $status): string
    {
        $statusMap = [
            0 => 'notdone',
            1 => 'ongoing',
            2 => 'done',
        ];

        return $statusMap[$status] ?? 'notdone';
    }
}
