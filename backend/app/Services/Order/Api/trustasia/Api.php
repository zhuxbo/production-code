<?php

declare(strict_types=1);

namespace App\Services\Order\Api\trustasia;

use App\Services\Order\Utils\DomainUtil;
use Illuminate\Support\Facades\Log;

class Api
{
    private Sdk $sdk;

    public function __construct()
    {
        $this->sdk = new Sdk;
    }

    /**
     * 购买SSL
     */
    public function new(array $data): array
    {
        $product = explode('-', $data['product_api_id']);

        $params = $this->getNewSslParams($data);

        $params['pay_product_id'] = (int) $product[1];

        $result = $this->sdk->new($product[0], $params);
        $apiId = $result['data']['id'] ?? '';

        if ($apiId) {
            $ssl = $this->get($apiId);

            return [
                'data' => [
                    'api_id' => $apiId,
                    'cert_apply_status' => 2,
                    'dcv' => $ssl['data']['dcv'] ?? null,
                    'validation' => $ssl['data']['validation'] ?? null,
                ],
                'code' => 1,
            ];
        }

        return $result;
    }

    /**
     * 整理购买SSL参数
     */
    protected function getNewSslParams(array $data): array
    {
        $params['certificate']['csr'] = $data['csr'];
        $params['certificate']['common_name'] = $data['common_name'];

        $params['validity_months'] = $data['period'];
        $params['alternative_order_id'] = $data['refer_id'];

        $params['dcv_method'] = $data['dcv']['method'];
        if (in_array($data['dcv']['method'], ['admin', 'administrator', 'webmaster', 'hostmaster', 'postmaster'])) {
            $params['dcv_method'] = 'email';
        }

        if (in_array($data['dcv']['method'], ['txt', 'cname'])) {
            $params['dcv_method'] = 'dns';
        }

        if (in_array($data['dcv']['method'], ['http', 'https', 'file'])) {
            $params['dcv_method'] = 'file';
        }

        foreach (explode(',', $data['alternative_names']) as $domain) {
            $params['certificate']['dns_names'][] = $domain;
        }

        return $params;
    }

    /**
     * 获取订单信息
     */
    public function get(string $apiId): array
    {
        $result = $this->sdk->getOrder($apiId);
        if (isset($result['code']) && $result['code'] === 1) {
            return [
                'data' => $this->getSsl($result['data'] ?? []),
                'code' => 1,
            ];
        } else {
            return $result;
        }
    }

    /**
     * 整理SSL信息
     */
    protected function getSsl(array $apiResultData): array
    {
        $data['validation'] = $this->getValidation($apiResultData['dcv_val'] ?? []);
        $data['common_name'] = $apiResultData['certificate']['common_name'] ?? '';
        $data['alternative_names'] = implode(',', $apiResultData['certificate']['dns_names'] ?? []);

        $data['status'] = $this->convertStatusToStandard($apiResultData['status'] ?? '');

        if ($data['status'] === 'pending') {
            $data['cert_apply_status'] = 2;
            $data['domain_verify_status'] = 0;
            $data['org_verify_status'] = 0;
        } else {
            if ($data['status'] === 'processing') {
                $data['cert_apply_status'] = 2;
                $data['domain_verify_status'] = 1;
                $data['org_verify_status'] = 1;
            } else {
                $data['cert_apply_status'] = 2;
                $data['domain_verify_status'] = 2;
                $data['org_verify_status'] = 2;
            }
        }

        $data['dcv'] = $this->getDcv($apiResultData['dcv_val'] ?? []);

        $data['csr'] = ($apiResultData['certificate']['csr'] ?? '') ?: null;
        ($apiResultData['certificate']['pem'] ?? false)
        && $data['cert'] = str_replace("\r\n", "\n", trim($apiResultData['certificate']['pem']));
        // 获取中级证书
        $cert = $this->sdk->getCert($apiResultData['certificate']['id'] ?? '');
        ($cert['data']['Certificate']['ica_pem'] ?? false)
        && $data['intermediate_cert'] = str_replace("\r\n", "\n", trim($cert['data']['Certificate']['ica_pem']));
        Log::info(json_encode($data));

        return $data;
    }

    /**
     * 获取全部域名验证信息
     */
    protected function getValidation(array $dcvVal): ?array
    {
        foreach ($dcvVal as $k => $v) {
            $validation[$k]['domain'] = $v['domain'] ?? '';
            $validation[$k]['method'] = $v['dcv_method'] ?? '';

            if ($validation[$k]['method'] == 'dns') {
                $validation[$k]['method'] = 'txt';
                $validation[$k]['host'] = mb_strtolower($v['auth_path'] ?? '');
                $validation[$k]['value'] = mb_strtolower($v['auth_val'] ?? '');
            } elseif ($validation[$k]['method'] == 'file') {
                $validation[$k]['name'] = str_replace('/.well-known/pki-validation/', '', $v['auth_path'] ?? '');
                $validation[$k]['link'] = '//'.$validation[$k]['domain'].($v['auth_path'] ?? '');
                $validation[$k]['content'] = $v['auth_val'] ?? '';
            } else {
                $validation[$k]['method'] = explode('@', $v['approval_email'])[0];
                $validation[$k]['email'] = $v['approval_email'] ?? '';
            }

            $validation[$k]['verified'] = ($v['verified'] ?? 0) ? 1 : 0;
        }

        return $validation ?? null;
    }

    /**
     * 获取通用验证信息
     */
    protected function getDcv(array $dcvVal): ?array
    {
        foreach ($dcvVal as $v) {
            $dcv['method'] = $v['dcv_method'];

            if ($v['dcv_method'] == 'dns') {
                $dcv['method'] = 'txt';
                $dcv['dns']['host'] = explode('.', mb_strtolower($v['auth_path'] ?? ''))[0];
                $dcv['dns']['type'] = 'TXT';
                $dcv['dns']['value'] = mb_strtolower($v['auth_val']);
            }

            if ($v['dcv_method'] == 'file') {
                $dcv['method'] = 'file';
                $dcv['file']['name'] = $v['auth_path']
                    ? str_replace('/.well-known/pki-validation/', '', $v['auth_path'])
                    : '';
                $dcv['file']['content'] = $v['auth_val'];
                $dcv['file']['path'] = $v['auth_path'];
            }

            if ($v['dcv_method'] == 'email') {
                $dcv['method'] = explode('@', $v['approval_email'])[0];
            }

            break;
        }

        return $dcv ?? null;
    }

    /**
     * 取消订单
     */
    public function cancel(string $apiId): array
    {
        $this->sdk->cancel($apiId);

        // 只有免费证书 取消全部返回成功
        return ['code' => 1];
    }

    /**
     * 重新验证
     */
    public function revalidate(string $apiId): array
    {
        return $this->sdk->revalidate($apiId);
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string $apiId, string $method, array $cert = []): array
    {
        $domains = $cert['alternative_names'] ?? '';
        $params['dcv_method'] = $method;

        if (in_array($method, ['admin', 'administrator', 'webmaster', 'hostmaster', 'postmaster'])) {
            $params['dcv_method'] = 'email';
            foreach (explode(',', $domains) as $domain) {
                $params['approval_emails'][] = ['domain' => $domain, 'email' => $method.'@'.DomainUtil::getRootDomain($domain)];
            }
        }

        $result = $this->sdk->updateDCV($apiId, $params);

        if ($result['code'] !== 1) {
            return $result;
        }

        return [
            'data' => [
                'dcv' => $this->getDcv($result['data']['dcv_vals'] ?? []),
                'validation' => $this->getValidation($result['data']['dcv_vals'] ?? []),
            ],
            'code' => 1,
        ];
    }

    /**
     * 转换状态为标准
     */
    protected function convertStatusToStandard(string $status): string
    {
        $processing = ['', 'auditing', 'submitting', 'domain_verifing', 'issuing', 'reissue', 'reissuing'];
        in_array($status, $processing) && ($status = 'processing');

        $approving = ['revoke_approving', 'revoke_confirming', 'revoking', 'cancel_confirm', 'confirming'];
        in_array($status, $approving) && ($status = 'approving');

        $convert = [
            'issued' => 'active', 'rejected' => 'failed', 'overtime' => 'failed', 'need_renew' => 'active',
            'canceled' => 'cancelled',
        ];
        isset($convert[$status]) && ($status = $convert[$status]);

        $allStatus = [
            'processing', 'active', 'cancelled', 'reissued', 'renewed', 'revoked', 'failed', 'expired', 'approving',
        ];
        in_array($status, $allStatus) || ($status = 'failed');

        return $status;
    }
}
