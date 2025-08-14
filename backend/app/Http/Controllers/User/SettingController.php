<?php

namespace App\Http\Controllers\User;

use App\Http\Requests\ApiToken\StoreRequest as ApiTokenUpdateRequest;
use App\Http\Requests\Callback\StoreRequest as CallbackUpdateRequest;
use App\Models\ApiToken;
use App\Models\Callback;

/**
 * 设置
 */
class SettingController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    // 获取ApiToken
    public function getApiToken()
    {
        $apiToken = ApiToken::where('user_id', $this->guard->id())->first();
        $this->success($apiToken?->toArray());
    }

    // 更新ApiToken
    public function updateApiToken(ApiTokenUpdateRequest $request)
    {
        $validated = $request->validated();
        $apiToken = ApiToken::where('user_id', $this->guard->id())->first();
        // 如果apiToken不存在，则创建
        if (! $apiToken) {
            $validated['user_id'] = $this->guard->id();
            ApiToken::create($validated);
        } else {
            $apiToken->update($validated);
        }
        $this->success();
    }

    // 获取回调设置
    public function getCallback()
    {
        $callback = Callback::where('user_id', $this->guard->id())->first();
        // 如果不存在则返回空
        if (! $callback) {
            $this->success();
        } else {
            $callback->setVisible(['url', 'token', 'status']);
            $this->success($callback->toArray());
        }
    }

    // 更新回调设置
    public function updateCallback(CallbackUpdateRequest $request)
    {
        $validated = $request->validated();
        $callback = Callback::where('user_id', $this->guard->id())->first();
        // 如果不存在则创建
        if (! $callback) {
            $validated['user_id'] = $this->guard->id();
            Callback::create($validated);
        } else {
            $callback->update($validated);
        }
        $this->success();
    }
}
