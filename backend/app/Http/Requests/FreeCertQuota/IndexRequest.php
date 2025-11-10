<?php

namespace App\Http\Requests\FreeCertQuota;

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
            'type' => 'nullable|string|max:50',
            'order_id' => 'nullable|integer',
            'quota' => 'nullable|integer',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }
}
