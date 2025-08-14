<?php

namespace App\Http\Requests\ProductPrice;

use App\Http\Requests\BaseRequest;

class GetIdsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:product_prices,id',
        ];
    }
}
