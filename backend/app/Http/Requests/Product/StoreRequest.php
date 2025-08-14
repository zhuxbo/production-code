<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseRequest;
use App\Models\Product;
use Illuminate\Validation\Validator;

class StoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:100|unique:products',
            'name' => 'required|string|max:100',
            'api_id' => 'required|string|max:128',
            'source' => 'required|string|max:20',
            'brand' => 'required|string|max:50',
            'ca' => 'required|string|max:200',
            'warranty_currency' => 'required|in:$,€,¥',
            'warranty' => 'required|numeric|min:0',
            'server' => 'required|integer|min:0',
            'encryption_standard' => 'required|string|max:50',
            'encryption_alg' => 'required|array',
            'encryption_alg.*' => 'in:rsa,ecdsa,sm2',
            'signature_digest_alg' => 'required|array',
            'signature_digest_alg.*' => 'in:sha256,sha384,sha512,sm3',
            'validation_type' => 'required|string|max:50',
            'common_name_types' => 'required|array',
            'common_name_types.*' => 'in:standard,wildcard,ipv4,ipv6',
            'alternative_name_types' => 'nullable|array',
            'alternative_name_types.*' => 'in:standard,wildcard,ipv4,ipv6',
            'validation_methods' => 'required|array',
            'periods' => 'required|array',
            'periods.*' => 'integer|in:1,3,6,12,24,36,48,60,72,84,96,108,120',
            'standard_min' => 'required|integer|min:0',
            'standard_max' => 'required|integer|min:0',
            'wildcard_min' => 'required|integer|min:0',
            'wildcard_max' => 'required|integer|min:0',
            'total_min' => 'required|integer|min:1',
            'total_max' => 'required|integer|min:1',
            'add_san' => 'required|in:0,1',
            'replace_san' => 'required|in:0,1',
            'reissue' => 'required|in:0,1',
            'renew' => 'required|in:0,1',
            'reuse_csr' => 'required|in:0,1',
            'gift_root_domain' => 'required|in:0,1',
            'refund_period' => 'required|integer|min:0|max:30',
            'remark' => 'nullable|string|max:500',
            'weight' => 'required|integer|min:0',
            'status' => 'required|integer|in:0,1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            // 检查 source 和 api_id 组合的唯一性
            if (isset($data['source']) && isset($data['api_id'])) {
                $exists = Product::where('source', $data['source'])
                    ->where('api_id', $data['api_id'])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('api_id', 'source 和 api_id 的组合必须唯一');
                }
            }

            // 检查 standard_max 和 wildcard_max 至少有一个大于等于1
            if (($data['standard_max'] ?? 0) < 1 && ($data['wildcard_max'] ?? 0) < 1) {
                $validator->errors()->add('standard_max', 'standard_max 和 wildcard_max 至少有一个必须大于等于1');
                $validator->errors()->add('wildcard_max', 'standard_max 和 wildcard_max 至少有一个必须大于等于1');
            }

            // 确保 standard_max 大于等于 standard_min
            if (isset($data['standard_min']) && isset($data['standard_max']) && $data['standard_max'] < $data['standard_min']) {
                $validator->errors()->add('standard_max', 'standard_max 必须大于等于 standard_min');
            }

            // 确保 wildcard_max 大于等于 wildcard_min
            if (isset($data['wildcard_min']) && isset($data['wildcard_max']) && $data['wildcard_max'] < $data['wildcard_min']) {
                $validator->errors()->add('wildcard_max', 'wildcard_max 必须大于等于 wildcard_min');
            }

            // 确保 total_max 大于等于 total_min
            if (isset($data['total_min']) && isset($data['total_max']) && $data['total_max'] < $data['total_min']) {
                $validator->errors()->add('total_max', 'total_max 必须大于等于 total_min');
            }
        });
    }
}
