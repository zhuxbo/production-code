<?php

namespace App\Models;

use App\Models\Traits\HasSnowflakeId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cert extends BaseModel
{
    use HasSnowflakeId;

    protected $fillable = [
        'order_id',
        'last_cert_id',
        'api_id',
        'vendor_id',
        'vendor_cert_id',
        'refer_id',
        'unique_value',
        'issuer',
        'action',
        'channel',
        'params',
        'amount',
        'common_name',
        'alternative_names',
        'standard_count',
        'wildcard_count',
        'dcv',
        'validation',
        'issued_at',
        'expires_at',
        'csr_md5',
        'csr',
        'private_key',
        'cert',
        'intermediate_cert',
        'serial_number',
        'fingerprint',
        'encryption_alg',
        'encryption_bits',
        'signature_digest_alg',
        'cert_apply_status',
        'domain_verify_status',
        'org_verify_status',
        'status',
    ];

    protected $casts = [
        'params' => 'json',
        'dcv' => 'json',
        'validation' => 'json',
        'amount' => 'decimal:2',
        'standard_count' => 'integer',
        'wildcard_count' => 'integer',
        'encryption_bits' => 'integer',
        'cert_apply_status' => 'integer',
        'domain_verify_status' => 'integer',
        'org_verify_status' => 'integer',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = ['intermediate_cert'];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->csr_md5 = md5($model->csr);
        });
    }

    /**
     * 获取订单
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 获取上一个证书
     */
    public function lastCert(): BelongsTo
    {
        return $this->belongsTo(self::class, 'last_cert_id');
    }

    /**
     * 获取中间证书
     */
    public function getIntermediateCertAttribute(): ?string
    {
        if (empty($this->issuer)) {
            return null;
        }

        return Chain::where('common_name', $this->issuer)->value('intermediate_cert');
    }

    /**
     * 设置中间证书
     */
    public function setIntermediateCertAttribute(?string $value): void
    {
        if (empty($this->issuer) || empty($value)) {
            return;
        }

        $chain = Chain::where('common_name', $this->issuer)->first();

        if (! $chain) {
            Chain::create([
                'common_name' => $this->issuer,
                'intermediate_cert' => $value,
            ]);
        }
    }
}
