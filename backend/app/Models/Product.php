<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'api_id',
        'source',
        'brand',
        'ca',
        'warranty_currency',
        'warranty',
        'server',
        'encryption_standard',
        'encryption_alg',
        'signature_digest_alg',
        'validation_type',
        'common_name_types',
        'alternative_name_types',
        'validation_methods',
        'periods',
        'standard_min',
        'standard_max',
        'wildcard_min',
        'wildcard_max',
        'total_min',
        'total_max',
        'add_san',
        'replace_san',
        'reissue',
        'renew',
        'reuse_csr',
        'gift_root_domain',
        'refund_period',
        'remark',
        'weight',
        'cost',
        'status',
    ];

    protected $casts = [
        'warranty' => 'decimal:2',
        'encryption_alg' => 'array',
        'signature_digest_alg' => 'array',
        'common_name_types' => 'array',
        'alternative_name_types' => 'array',
        'validation_methods' => 'array',
        'periods' => 'array',
        'add_san' => 'integer',
        'replace_san' => 'integer',
        'reissue' => 'integer',
        'renew' => 'integer',
        'reuse_csr' => 'integer',
        'gift_root_domain' => 'integer',
        'refund_period' => 'integer',
        'cost' => 'array',
        'status' => 'integer',
    ];

    public function getCostAttribute(): array
    {
        $cost = is_json($this->attributes['cost']) ? json_decode($this->attributes['cost'], true) : [];
        return $this->getCost($cost);
    }

    public function setCostAttribute(array $cost): void
    {
        $data = $this->getCost($cost);
        $this->attributes['cost'] = json_encode($data);
    }

    /**
     * 获取订单
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * 获取价格
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * 获取结构化的价格
     */
    protected function getCost(array $cost): array
    {
        $data = [];
        foreach ($this->periods as $period) {
            $period = (string) $period;
            $data['price'][$period] = $cost['price'][$period] ?? 0;
            if (in_array('standard', $this->alternative_name_types)) {
                $data['alternative_standard_price'][$period] = $cost['alternative_standard_price'][$period] ?? 0;
            }
            if (in_array('wildcard', $this->alternative_name_types)) {
                $data['alternative_wildcard_price'][$period] = $cost['alternative_wildcard_price'][$period] ?? 0;
            }
        }

        return $data;
    }
}
