<?php

namespace App\Http\Requests\Callback;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id|unique:callbacks',
            'url' => 'required|string|url|max:255',
            'token' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ];
    }
}
