<?php

namespace App\Console\Commands;

use App\Models\DomainValidationRecord;
use App\Models\Order;
use App\Services\Order\Action;
use App\Services\Order\Utils\VerifyUtil;
use Illuminate\Console\Command;
use Throwable;

/**
 * 定时验证证书
 * 每1分钟执行一次
 */
class ValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:validate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto verify processing certificate';

    /**
     * 时间节点（分钟）
     * 表示从创建时间开始的累积时间点：创建后3分钟、6分钟、10分钟、20分钟...
     */
    protected array $time_nodes = [
        3, 6, 10, 20, 30, 45, 60, 120, 180, 240, 360, 540, 360 * 2, 360 * 3, 360 * 4, 360 * 5, 360 * 6, 360 * 7, 360 * 8,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // 查询所有待验证的订单：状态为processing或approving且有DCV配置的证书
        $orders = Order::with(['latestCert'])
            ->whereHas('latestCert', function ($query) {
                $query->whereIn('status', ['processing', 'approving'])->where('dcv', '!=', null);
            })
            ->get();

        $siteName = get_system_setting('site', 'name', 'SSL证书管理系统');
        $this->info("[$siteName] 证书验证命令开始执行");
        $this->info("待验证订单数量: {$orders->count()}");

        foreach ($orders as $order) {
            try {
                // 查找或创建域名验证记录
                $record = DomainValidationRecord::where('order_id', $order->id)->first();

                if (! $record) {
                    // 首次创建验证记录：1分钟后开始首次验证
                    $record = new DomainValidationRecord([
                        'order_id' => $order->id,
                        'last_check_at' => now(),
                        'next_check_at' => now()->addMinutes(), // 首次验证在1分钟后
                    ]);
                    $record->save();
                }

                // 检查验证是否已超过最大时间限制（48小时）
                $elapsed_hours = $record->created_at->diffInHours(now());
                if ($elapsed_hours < 48) {

                    // 检查是否到了验证时间
                    if ($record->next_check_at->timestamp <= time()) {

                        // 根据证书状态和验证方法决定验证方式
                        if ($order->latestCert->status === 'processing' && in_array($order->latestCert->dcv['method'] ?? '',
                            ['txt', 'cname', 'file', 'http', 'https'])) {

                            // 执行域名验证（DNS/HTTP/HTTPS验证）
                            $verified = VerifyUtil::verifyValidation($order->latestCert->validation);

                            if ($verified['code'] == 1) {
                                // 验证成功：创建重新验证任务
                                $action = new Action($order->user_id);
                                $action->createTask($order->id, 'revalidate');
                                $this->info("订单 #$order->id: 验证成功，已创建提交CA验证的任务");
                            } else {
                                // 验证失败
                                $errorMsg = $verified['msg'] ?: '验证失败';
                                $this->warn("订单 #$order->id: $errorMsg");
                            }
                        } else {
                            // 其他状态：直接创建同步任务（如approving状态等待CA处理）
                            $action = new Action($order->user_id);
                            $action->createTask($order->id, 'sync');
                            $this->info("订单 #$order->id: 已创建同步任务");
                        }

                        // 无论验证成功失败，都基于创建时间设置下次检测时间
                        $this->setNextCheckAt($record);
                    }

                    $nextCheckTime = $record->next_check_at->format('Y-m-d H:i:s');
                    $this->info("订单 #$order->id: 下次检测时间 $nextCheckTime");
                } else {
                    $this->warn("订单 #$order->id: 验证超时（超过48小时），停止检测");
                }
            } catch (Throwable $e) {
                $this->error("订单 #$order->id: 验证异常 - {$e->getMessage()}");
            }
        }
    }

    /**
     * 设置下次验证时间
     *
     * 基于创建时间和时间节点数组，设置下次验证的绝对时间
     *
     * @param  DomainValidationRecord  $record  域名验证记录
     */
    protected function setNextCheckAt(DomainValidationRecord $record): void
    {
        // 计算从创建时间到现在的分钟数
        $elapsed_minutes = $record->created_at->diffInMinutes(now());

        // 找到下一个时间节点
        $next_time_node = $this->getNextTimeNode($elapsed_minutes);

        if ($next_time_node > 0) {
            // 更新验证记录
            $record->last_check_at = now();

            // 基于创建时间计算下次验证的绝对时间
            $record->next_check_at = $record->created_at->addMinutes($next_time_node);

            $record->save();

            $interval_minutes = intval($next_time_node - $elapsed_minutes);
            $this->info("订单 #$record->order_id: 将在 $interval_minutes 分钟后再次检测（距创建 $next_time_node 分钟）");
        }
    }

    /**
     * 根据已过去的时间获取下一个时间节点
     *
     * @param  int  $elapsed_minutes  从创建时间已过去的分钟数
     * @return int 下一个时间节点（分钟），0表示没有更多节点
     */
    protected function getNextTimeNode(int $elapsed_minutes): int
    {
        // 找到第一个大于已过去时间的时间节点
        foreach ($this->time_nodes as $time_node) {
            if ($time_node > $elapsed_minutes) {
                return $time_node;
            }
        }

        // 如果所有时间节点都已过去，返回0（停止验证）
        return 0;
    }
}
