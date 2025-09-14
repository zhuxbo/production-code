<?php

declare(strict_types=1);

namespace App\Services\Order\Utils;

class FilterUtil
{
    private const array FILTER_FIELDS = [
        'new' => [
            'action',
            'channel',
            'is_batch',
            'plus',
            'refer_id',
            'unique_value',
            'user_id',
            'product_id',
            'period',
            'csr_generate',
            'encryption',
            'csr',
            'validation_method',
            'domains',
            'contact',
            'organization',
            'issue_verify',
        ],
        'renew' => [
            'order_id',
            'action',
            'channel',
            'refer_id',
            'unique_value',
            'period',
            'csr_generate',
            'encryption',
            'csr',
            'validation_method',
            'domains',
            'contact',
            'organization',
            'issue_verify',
        ],
        'reissue' => [
            'order_id',
            'action',
            'channel',
            'refer_id',
            'unique_value',
            'csr_generate',
            'encryption',
            'csr',
            'validation_method',
            'domains',
            'issue_verify',
        ],
        'organization' => [
            'name',
            'registration_number',
            'phone',
            'address',
            'city',
            'state',
            'country',
            'postcode',
        ],
        'contact' => [
            'first_name',
            'last_name',
            'title',
            'email',
            'phone',
        ],
    ];

    public static function filterSslParamsField(array $params): array
    {
        $action = $params['action'] ?? 'new';
        $allowedFields = self::FILTER_FIELDS[$action] ?? self::FILTER_FIELDS['new'];

        $params = self::arrayFilterAllowedKeys($params, $allowedFields);

        if (isset($params['organization'])) {
            $params['organization'] = self::filterOrganization($params['organization']);
        }
        if (isset($params['contact'])) {
            $params['contact'] = self::filterContact($params['contact']);
        }

        return $params;
    }

    public static function filterOrganization(array|int|string $organization): array|int
    {
        if (is_int($organization) || is_string($organization)) {
            return (int) $organization;
        }
        return self::arrayFilterAllowedKeys($organization, self::FILTER_FIELDS['organization']);
    }

    public static function filterContact(array|int|string $contact): array|int
    {
        if (is_int($contact) || is_string($contact)) {
            return (int) $contact;
        }
        return self::arrayFilterAllowedKeys($contact, self::FILTER_FIELDS['contact']);
    }

    public static function arrayFilterAllowedKeys(array $data, array|string $allowedFields = []): array
    {
        $allowedFields = is_array($allowedFields) ? $allowedFields : explode(',', $allowedFields);
        return array_filter($data, function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        }, ARRAY_FILTER_USE_KEY);
    }
}
