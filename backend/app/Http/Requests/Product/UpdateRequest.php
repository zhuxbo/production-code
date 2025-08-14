<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseRequest;
use App\Models\Product;
use Illuminate\Validation\Validator;

class UpdateRequest extends BaseRequest
{
    private ?int $productId = null;

    /**
     * 设置产品 ID
     */
    public function setProductId(int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function rules(): array
    {
        $productId = $this->productId ?? $this->route('id', 0);

        return [
            'code' => 'nullable|string|max:100|unique:products,code,'.$productId,
            'name' => 'string|max:100',
            'api_id' => 'string|max:128',
            'source' => 'string|max:20',
            'brand' => 'string|max:50',
            'ca' => 'string|max:200',
            'warranty_currency' => 'string|max:10',
            'warranty' => 'numeric|min:0',
            'server' => 'integer|min:0',
            'encryption_standard' => 'string|max:50',
            'encryption_alg' => 'array',
            'encryption_alg.*' => 'in:rsa,ecdsa,sm2',
            'signature_digest_alg' => 'array',
            'signature_digest_alg.*' => 'in:sha256,sha384,sha512,sm3',
            'validation_type' => 'string|max:50',
            'common_name_types' => 'array',
            'common_name_types.*' => 'in:standard,wildcard,ipv4,ipv6',
            'alternative_name_types' => 'nullable|array',
            'alternative_name_types.*' => 'in:standard,wildcard,ipv4,ipv6',
            'validation_methods' => 'array',
            'periods' => 'array',
            'standard_min' => 'integer|min:0',
            'standard_max' => 'integer|min:0',
            'wildcard_min' => 'integer|min:0',
            'wildcard_max' => 'integer|min:0',
            'total_min' => 'integer|min:1',
            'total_max' => 'integer|min:1',
            'add_san' => 'boolean',
            'replace_san' => 'boolean',
            'reissue' => 'boolean',
            'renew' => 'boolean',
            'reuse_csr' => 'boolean',
            'gift_root_domain' => 'boolean',
            'refund_period' => 'integer|min:0',
            'remark' => 'nullable|string|max:255',
            'weight' => 'integer|min:0',
            'status' => 'integer|in:0,1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            $productId = $this->productId ?? $this->route('id', 0);

            // 检查 source 和 api_id 组合的唯一性（排除当前记录）
            if (isset($data['source']) && isset($data['api_id'])) {
                $exists = Product::where('source', $data['source'])
                    ->where('api_id', $data['api_id'])
                    ->where('id', '!=', $productId)
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
