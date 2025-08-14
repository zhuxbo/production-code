<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\ApiResponseException;
use App\Models\Task as TaskModel;
use App\Services\OrderService\Action;
use App\Utils\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class Task implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 执行任务
     */
    public function handle(): void
    {
        $task = TaskModel::where('id', $this->data['id'] ?? 0)
            ->where('status', 'executing')
            ->where('started_at', '<=', now())
            ->lockForUpdate()
            ->first();

        $failedException = null;
        if ($task) {
            $action = $task->action;

            try {
                (new Action($task->user_id ?? 0))->$action($task->task_id);
            } catch (ApiResponseException $e) {
                $response = $e->getApiResponse();
                $data['result'] = $response;
                if ($response['code'] === 1) {
                    $data['status'] = 'successful';
                } else {
                    $data['status'] = 'failed';
                }
            } catch (Throwable $e) {
                $data['result'] = [
                    'code' => 0,
                    'msg' => $e->getMessage(),
                    'data' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'error_code' => $e->getCode(),
                        'previous' => $e->getPrevious()?->getMessage(),
                        'trace' => $e->getTrace(),
                    ],
                ];
                $data['status'] = 'failed';
                $failedException = $e;
            }

            $data['attempts'] = ($task['attempts'] ?? 0) + 1;
            $data['weight'] = 0;
            $data['last_execute_at'] = now();
            $task->update($data);
        }

        if ($failedException) {
            $this->fail($failedException);
        }
    }

    /**
     * 任务失败
     *
     * @throws Throwable
     */
    public function failed(Throwable $e): void
    {
        $task = TaskModel::where('id', $this->data['id'] ?? 0)->first();

        if ($task) {
            $body = '<!DOCTYPE html><html lang="zh"><head>
<style>
table {border-collapse: collapse; width: 100%;}
table, th, td {border: 1px solid black}
td {padding: 6px; text-align: center}
</style>
<title></title>
</head>
<body>
<div style="width: 800px; margin: 20px auto">
<table>';

            $body .= '<tr><td>错误信息</td><td>'.$e->getMessage().'</td></tr>';
            $body .= '<tr><td>任务ID</td><td>'.$task->id.'</td></tr>';
            $body .= '<tr><td>参数ID</td><td>'.$task->task_id.'</td></tr>';
            $body .= '<tr><td>动作</td><td>'.$task->action.'</td></tr>';
            $body .= '<tr><td style="white-space: nowrap">执行次数</td><td>'.$task->attempts.'</td></tr>';

            $result = 'Code: '.$task->result['code'].'<br>';
            $result .= 'Msg: '.$task->result['msg'].'<br>';
            $result .= 'Time: '.date('Y-m-d H:i:s', $task->last_execute_at->timestamp).'<br>';
            $data = is_array($task->result['data'])
                ? json_encode($task->result['data'], JSON_UNESCAPED_UNICODE)
                : $task->result['data'];
            $result .= 'Data: '.$data;

            $body .= '<tr><td>返回结果</td><td>'.$result.'</td></tr>';
            $body .= '<tr><td>创建时间</td><td>'.date('Y-m-d H:i:s', $task->created_at->timestamp).'</td></tr>';
            $body .= '<tr><td>执行时间</td><td>'.date('Y-m-d H:i:s', $task->last_execute_at->timestamp).'</td></tr>';
            $body .= '<tr><td>执行状态</td><td>'.$task->status.'</td></tr>';
            $body .= '</table></div></body></html>';
        }

        $adminEmail = get_system_setting('site', 'adminEmail');
        if ($adminEmail) {
            $mail = new Email;
            $mail->isSMTP();
            $mail->isHTML();
            $mail->setFrom(get_system_setting('mail', 'senderMail'), get_system_setting('mail', 'senderName'));
            $mail->addAddress($adminEmail);
            $mail->setSubject(get_system_setting('site', 'name', 'SSL证书管理系统').'后台队列错误');
            $mail->Body = $body ?? $e->getMessage();
            $mail->send();
        }
    }
}
