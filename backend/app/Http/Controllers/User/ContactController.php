<?php

namespace App\Http\Controllers\User;

use App\Http\Requests\Contact\GetIdsRequest;
use App\Http\Requests\Contact\IndexRequest;
use App\Http\Requests\Contact\StoreRequest;
use App\Http\Requests\Contact\UpdateRequest;
use App\Models\Contact;

class ContactController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取联系人列表
     */
    public function index(IndexRequest $request): void
    {
        $validated = $request->validated();
        $currentPage = (int) ($validated['currentPage'] ?? 1);
        $pageSize = (int) ($validated['pageSize'] ?? 10);

        $query = Contact::query();

        // 添加搜索条件
        if (! empty($validated['quickSearch'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('first_name', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('last_name', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('email', 'like', "%{$validated['quickSearch']}%")
                    ->orWhere('phone', 'like', "%{$validated['quickSearch']}%");
            });
        }
        if (! empty($validated['first_name'])) {
            $query->where('first_name', 'like', "%{$validated['first_name']}%");
        }
        if (! empty($validated['last_name'])) {
            $query->where('last_name', 'like', "%{$validated['last_name']}%");
        }
        if (! empty($validated['email'])) {
            $query->where('email', 'like', "%{$validated['email']}%");
        }
        if (! empty($validated['phone'])) {
            $query->where('phone', 'like', "%{$validated['phone']}%");
        }
        if (! empty($validated['created_at'])) {
            $query->whereBetween('created_at', $validated['created_at']);
        }

        $total = $query->count();
        $items = $query->select([
            'id', 'first_name', 'last_name', 'identification_number', 'title', 'email', 'phone', 'created_at',
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
     * 添加联系人
     */
    public function store(StoreRequest $request): void
    {
        $validated = $request->validated();
        $validated['user_id'] = $this->guard->id();
        $contact = Contact::create($validated);

        if (! $contact->exists) {
            $this->error('添加失败');
        }

        $this->success();
    }

    /**
     * 获取联系人资料
     */
    public function show($id): void
    {
        $contact = Contact::find($id);
        if (! $contact) {
            $this->error('联系人不存在');
        }

        $contact->makeHidden(['user_id']);

        $this->success($contact->toArray());
    }

    /**
     * 批量获取联系人资料
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $contacts = Contact::whereIn('id', $ids)->get();
        if ($contacts->isEmpty()) {
            $this->error('联系人不存在');
        }

        foreach ($contacts as $contact) {
            $contact->makeHidden(['user_id']);
        }

        $this->success($contacts->toArray());
    }

    /**
     * 更新联系人资料
     */
    public function update(UpdateRequest $request, $id): void
    {
        $contact = Contact::find($id);
        if (! $contact) {
            $this->error('联系人不存在');
        }

        $contact->fill($request->validated());
        $contact->save();

        $this->success();
    }

    /**
     * 删除联系人
     */
    public function destroy($id): void
    {
        $contact = Contact::find($id);
        if (! $contact) {
            $this->error('联系人不存在');
        }

        $contact->delete();
        $this->success();
    }

    /**
     * 批量删除联系人
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        $ids = $request->validated('ids');

        $contacts = Contact::whereIn('id', $ids)->get();
        if ($contacts->isEmpty()) {
            $this->error('联系人不存在');
        }

        Contact::destroy($ids);
        $this->success();
    }
}
