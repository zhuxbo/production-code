<?php

namespace App\Http\Requests\FreeCertQuota;

use App\Http\Requests\BaseRequest;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|string|max:50',
            'order_id' => 'nullable|integer',
            'quota' => 'required|integer|min:0',
        ];
    }
}
