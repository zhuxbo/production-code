<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiResponseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\GetIdsRequest;
use App\Http\Requests\Task\IndexRequest;
use App\Models\Task;
use App\Services\Order\Action;

class TaskController extends Controller
{
    /**
     * 获取任务列表
     */
    public function index(IndexRequest $request)
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Task::query();

        if (isset($validated['task_id'])) {
            $query->where('task_id', 'like', "%{$validated['task_id']}%");
        }
        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (isset($validated['source'])) {
            $query->where('source', 'like', "%{$validated['source']}%");
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }

        $total = $query->count();
        $tasks = $query->select([
            'id', 'task_id', 'action', 'attempts', 'source',  'weight', 'status', 'started_at', 'last_execute_at', 'created_at',
        ])
            ->orderBy('weight', 'desc')
            ->orderBy('id', 'desc')
            ->offset(($currentPage - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $this->success([
            'items' => $tasks,
            'total' => $total,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
        ]);
    }

    /**
     * 获取任务详情
     */
    public function show($id)
    {
        $task = Task::find($id);
        if (! $task) {
            $this->error('任务不存在');
        }

        $this->success($task->toArray());
    }

    /**
     * 删除任务
     */
    public function destroy($id)
    {
        $task = Task::find($id);
        if (! $task) {
            $this->error('任务不存在');
        }

        $task->delete();

        $this->success();
    }

    /**
     * 批量删除任务
     */
    public function batchDestroy(GetIdsRequest $request)
    {
        $ids = $request->validated('ids');

        $tasks = Task::whereIn('id', $ids)->get();
        if ($tasks->isEmpty()) {
            $this->error('任务不存在');
        }

        Task::destroy($ids);

        $this->success();
    }

    /**
     * 执行任务
     * 只有 commit revalidate sync cancel callback sendActive sendExpire 可以加入任务队列
     */
    public function batchExecute(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $tasks = Task::whereIn('id', $ids)
            ->where('status', 'executing')
            ->get();
        if ($tasks->isEmpty()) {
            $this->error('任务不存在或已完成');
        }

        foreach ($tasks as $task) {
            $action = $task->action;
            try {
                (new Action)->$action($task->task_id);
            } catch (ApiResponseException $e) {
                $result = $e->getApiResponse();
                $data['result'] = $result;
                $data['attempts'] = ($task['attempts'] ?? 0) + 1;

                if ($result['code'] === 1) {
                    $data['status'] = 'successful';
                } else {
                    $data['status'] = 'failed';
                }

                $data['last_execute_at'] = now();
                $data['weight'] = 0;
                $task->update($data);
            }
        }

        $this->success();
    }

    /**
     * 启动任务
     */
    public function batchStart(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $tasks = Task::whereIn('id', $ids)
            ->whereIn('status', ['stopped', 'failed'])
            ->get();
        if ($tasks->isEmpty()) {
            $this->error('任务不存在或已启动');
        }

        foreach ($tasks as $task) {
            $data = ['status' => 'executing', 'weight' => $task->id];
            if ($task->action === 'cancel') {
                $data['started_at'] = now()->addSeconds(120);
            } else {
                $data['started_at'] = now();
            }
            $task->update($data);
            \App\Jobs\Task::dispatch(['id' => $task->id])->onQueue('Task');
        }

        $this->success();
    }

    /**
     * 停止任务
     */
    public function batchStop(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $stopped = Task::whereIn('id', $ids)
            ->where('status', 'executing')
            ->update(['status' => 'stopped']);
        if ($stopped === 0) {
            $this->error('任务不存在或已停止');
        }

        $this->success();
    }
}
