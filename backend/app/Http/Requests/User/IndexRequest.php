<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'quickSearch' => 'nullable|string|max:100',
            'username' => 'nullable|string|max:20',
            'email' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:20',
            'level_code' => 'nullable|string|max:50',
            'custom_level_code' => 'nullable|string|max:50',
            'status' => 'nullable|integer|in:0,1',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }
}
