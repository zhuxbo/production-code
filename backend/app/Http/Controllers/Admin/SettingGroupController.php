<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SettingGroup\GetIdsRequest;
use App\Http\Requests\SettingGroup\StoreRequest;
use App\Http\Requests\SettingGroup\UpdateRequest;
use App\Models\SettingGroup;
use Illuminate\Support\Facades\Config;

class SettingGroupController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取所有设置组列表
     */
    public function index(): void
    {
        $groups = SettingGroup::orderBy('weight', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $this->success([
            'groups' => $groups,
        ]);
    }

    /**
     * 添加设置组
     */
    public function store(StoreRequest $request): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止添加新设置组');
        }

        $group = SettingGroup::create($request->validated());

        if (! $group->exists) {
            $this->error('添加失败');
        }

        $this->success(['id' => $group->id]);
    }

    /**
     * 获取设置组详情
     */
    public function show($id): void
    {
        $group = SettingGroup::find($id);
        if (! $group) {
            $this->error('设置组不存在');
        }

        $this->success($group->toArray());
    }

    /**
     * 批量获取设置组详情
     */
    public function batchShow(GetIdsRequest $request): void
    {
        $ids = $request->input('ids');

        $groups = SettingGroup::whereIn('id', $ids)->get();
        if ($groups->isEmpty()) {
            $this->error('设置组不存在');
        }

        $this->success($groups->toArray());
    }

    /**
     * 更新设置组
     */
    public function update(UpdateRequest $request, $id): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止修改设置组');
        }

        $group = SettingGroup::find($id);
        if (! $group) {
            $this->error('设置组不存在');
        }

        $group->fill($request->validated());
        $group->save();

        $this->success();
    }

    /**
     * 删除设置组
     */
    public function destroy($id): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止删除设置组');
        }

        $group = SettingGroup::find($id);
        if (! $group) {
            $this->error('设置组不存在');
        }

        // 删除设置组时将级联删除关联的设置项
        $group->delete();
        $this->success();
    }

    /**
     * 批量删除设置组
     */
    public function batchDestroy(GetIdsRequest $request): void
    {
        // 检查是否锁定
        if (Config::get('settings.locked', false)) {
            $this->error('设置已锁定，禁止删除设置组');
        }

        $ids = $request->input('ids');

        $groups = SettingGroup::whereIn('id', $ids)->get();
        if ($groups->isEmpty()) {
            $this->error('设置组不存在');
        }

        // 删除设置组时将级联删除关联的设置项
        SettingGroup::destroy($ids);
        $this->success();
    }
}
