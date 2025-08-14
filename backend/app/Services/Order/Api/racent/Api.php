<?php

declare(strict_types=1);

namespace App\Services\Order\Api\racent;

use App\Services\Order\Utils\DomainUtil;
use App\Utils\SnowFlake;

class Api
{
    protected Sdk $sdk;

    public function __construct()
    {
        $this->sdk = new Sdk;
    }

    /**
     * 获取产品
     */
    public function getProducts(string $brand = '', string $code = ''): array
    {
        if (! $brand || ! $code) {
            return ['code' => 0, 'data' => '仅支持获取指定产品'];
        }

        $products = $this->sdk->getProducts($brand)['data'] ?? [];

        foreach ($products as $v) {
            if (strtolower($v['code']) === strtolower($code)) {
                // 转换 price 结构为 cost 结构
                $cost = $this->convertPriceToCost($v['price']);

                return [
                    'code' => 1,
                    'data' => [
                        [
                            'api_id' => $v['code'],
                            'cost' => $cost,
                        ],
                    ],
                ];
            }
        }

        return ['code' => 0, 'msg' => '未找到产品'];
    }

    /**
     * 转换 price 结构为 cost 结构
     */
    protected function convertPriceToCost(array $priceData): ?array
    {
        // 处理基础价格
        if (isset($priceData['basePrice'])) {
            foreach ($priceData['basePrice'] as $period => $price) {
                // 将 price012 转换为 12，price024 转换为 24，price036 转换为 36
                $periodNumber = (string) (intval(substr($period, -3)));
                $cost['price'][$periodNumber] = floatval($price);
            }
        }

        // 处理 SAN 价格
        if (isset($priceData['sanPrice'])) {
            // 处理通配符价格
            if (isset($priceData['sanPrice']['wildPrice'])) {
                foreach ($priceData['sanPrice']['wildPrice'] as $period => $price) {
                    $periodNumber = (string) (intval(substr($period, -3)));
                    $cost['alternative_wildcard_price'][$periodNumber] = floatval($price);
                }
            }

            // 处理标准价格
            if (isset($priceData['sanPrice']['normalPrice'])) {
                foreach ($priceData['sanPrice']['normalPrice'] as $period => $price) {
                    $periodNumber = (string) (intval(substr($period, -3)));
                    $cost['alternative_standard_price'][$periodNumber] = floatval($price);
                }
            }
        }

        return $cost ?? null;
    }

    /**
     * 购买SSL
     */
    public function new(array $data): array
    {
        $years = intval(ceil($data['period'] / 12));
        $params = $this->getNewSslParams($data);
        $result = $this->sdk->new($data['product_api_id'], $years, $params);

        $apiId = $result['data']['certId'] ?? '';

        if ($result['code'] === 1 && $apiId) {
            return ['data' => ['api_id' => $apiId], 'code' => 1];
        }

        return $result;
    }

    /**
     * 续费SSL
     */
    public function renew(array $data): array
    {
        $renewId = $data['last_api_id'];
        $years = intval(ceil($data['period'] / 12));
        $params = $this->getNewSslParams($data);
        $result = $this->sdk->renew($renewId, $years, $params);

        $apiId = $result['data']['certId'] ?? '';

        if ($result['code'] === 1 && $apiId) {
            return ['data' => ['api_id' => $apiId], 'code' => 1];
        }

        return $result;
    }

    /**
     * 重新颁发
     */
    public function reissue(array $data): array
    {
        $reissuedId = $data['last_api_id'];

        $params = $this->getParams($data);

        if (isset($data['organization'])) {
            $params['organizationInfo'] = $this->convertOrganizationToApi($data['organization']);
        }

        $result = $this->sdk->replace($reissuedId, $params);

        $apiId = $result['data']['certId'] ?? '';

        if ($result['code'] === 1 && $apiId) {
            return ['data' => ['api_id' => $apiId], 'code' => 1];
        }

        return $result;
    }

    /**
     * 整理购买SSL参数
     */
    protected function getNewSslParams(array $data): array
    {
        $params = $this->getParams($data);

        $params['Administrator'] = $this->convertContactToAdministrator($data['contact'] ?? []);

        if (isset($data['organization'])) {
            $params['organizationInfo'] = $this->convertOrganizationToApi($data['organization']);

            $params['Administrator']['organation'] = $params['organizationInfo']['organizationName'] ?? '';
            $params['Administrator']['country'] = $params['organizationInfo']['organizationCountry'] ?? '';
            $params['Administrator']['state'] = $params['organizationInfo']['organizationState'] ?? '';
            $params['Administrator']['city'] = $params['organizationInfo']['organizationCity'] ?? '';
            $params['Administrator']['address'] = $params['organizationInfo']['organizationAddress'] ?? '';
            $params['Administrator']['postCode'] = $params['organizationInfo']['organizationPostCode'] ?? '';

            $params['tech'] = $params['Administrator'];
            $params['finance'] = $params['Administrator'];
        }

        // 默认赠送时间
        $params['originalfromOthers'] = $data['plus'] ? 1 : 0;

        return $params;
    }

    protected function getParams(array $data): array
    {
        $params['csr'] = $data['csr'];
        $params['uniqueValue'] = $data['unique_value'] ?? $this->generateUniqueValue();
        $params['refId'] = $data['refer_id'];

        $method = $this->convertDcvToApi($data['dcv']['method']);

        foreach (explode(',', $data['alternative_names']) as $domain) {
            $params['domainInfo'][] = [
                'domainName' => $domain,
                'dcvMethod' => $method,
                'dcvEmail' => $method === 'EMAIL' ? $data['dcv']['method'].'@'.DomainUtil::getRootDomain($domain) : '',
            ];
        }

        return $params;
    }

    /**
     * 获取订单信息
     */
    public function get(string $apiId): array
    {
        $result = $this->sdk->getStatus($apiId);
        if ($result['code'] === 1) {
            $data = $this->getSsl($result['data'] ?? []);

            return ['data' => $data, 'code' => 1];
        } else {
            return $result;
        }
    }

    /**
     * 整理SSL信息
     */
    protected function getSsl(array $data): array
    {
        $result['vendor_id'] = $data['vendorId'] ?? '';
        $result['vendor_cert_id'] = $data['vendorCertId'] ?? '';
        $result['cert_apply_status'] = $this->getProcessStatus($data['application']['status'] ?? 'notdone');
        $result['domain_verify_status'] = $this->getProcessStatus($data['dcv']['status'] ?? 'notdone');
        $result['org_verify_status'] = $this->getProcessStatus($data['ov']['status'] ?? 'notdone');
        $result['status'] = $this->convertStatusToStandard($data['status'] ?? '');

        // 完成申请的状态才返回validation信息
        if ($result['cert_apply_status'] === 2) {
            $result['common_name'] = $data['dcvList'][0]['domainName'] ?? '';

            foreach (($data['dcvList'] ?? []) as $k => $v) {
                $result['alternative_names'][] = $v['domainName'] ?? '';
                $result['validation'][$k]['domain'] = $v['domainName'] ?? '';

                $method = $this->convertDcvToStandard($v['dcvMethod'] ?? '');
                if ($method == 'email') {
                    $method = explode('@', $v['dcvEmail'])[0];
                }
                $result['validation'][$k]['method'] = $method;

                if ($method == 'cname') {
                    $result['validation'][$k]['host'] = $data['DCVdnsHost'] ?? '';
                    $result['validation'][$k]['value'] = str_replace('comodoca.com', 'sectigo.com', $data['DCVdnsValue'] ?? '');
                } elseif ($method == 'http' || $method == 'https') {
                    $result['validation'][$k]['link'] = $method.'://'.$v['domainName'].'/.well-known/pki-validation/'.($data['DCVfileName'] ?? '');
                    $result['validation'][$k]['name'] = $data['DCVfileName'] ?? '';
                    $result['validation'][$k]['content'] = str_replace('comodoca.com', 'sectigo.com', $data['DCVfileContent'] ?? '');
                } else {
                    $result['validation'][$k]['email'] = $v['dcvEmail'] ?? '';
                }

                $result['validation'][$k]['verified'] = ($v['is_verify'] ?? 0) ? 1 : 0;
            }

            if (isset($result['alternative_names'])) {
                $result['alternative_names'] = implode(',', array_unique($result['alternative_names']));
            }

            if (isset($result['validation'])) {
                foreach ($result['validation'] as $v) {
                    if (! $v['verified']) {
                        $result['dcv']['method'] = $v['method'];
                        if ($v['method'] == 'cname') {
                            $result['dcv']['dns']['host'] = $v['host'];
                            $result['dcv']['dns']['type'] = mb_strtoupper($v['method']);
                            $result['dcv']['dns']['value'] = $v['value'];
                        } elseif ($v['method'] == 'http' || $v['method'] == 'https') {
                            $result['dcv']['file']['name'] = $v['name'];
                            $result['dcv']['file']['path'] = '/.well-known/pki-validation/'.$v['name'];
                            $result['dcv']['file']['content'] = $v['content'];
                        } else {
                            $result['dcv']['method'] = explode('@', $v['email'])[0];
                        }
                    }
                    break;
                }
            }
        }

        if (array_filter(($data['applyParams']['Administrator'] ?? []))) {
            $result['contact'] = $this->convertAdministratorToContact($data['applyParams']['Administrator']);
            if ($this->isDefaultAdministrator($data['applyParams']['Administrator'])) {
                unset($result['contact']);
            }
        }

        if (array_filter(($data['applyParams']['organizationInfo'] ?? []))) {
            $result['organization'] = $this->convertOrganizationToStandard($data['applyParams']['organizationInfo']);
        }

        ($data['applyParams']['csr'] ?? false) && $result['csr'] = $data['applyParams']['csr'];
        ($data['certificate'] ?? false) && $result['cert'] = str_replace("\r\n", "\n", trim($data['certificate']));
        ($data['caCertificate'] ?? false) && $result['intermediate_cert'] = str_replace("\r\n", "\n", trim($data['caCertificate']));

        return $result;
    }

    /**
     * 获取 dcv 验证信息
     */
    protected function getDcv(string $method, string $recode, string $value): ?array
    {
        if ($method) {
            $dcv['method'] = $method;

            if ($method == 'cname') {
                $dcv['dns']['host'] = $recode;
                $dcv['dns']['type'] = mb_strtoupper($method);
                $dcv['dns']['value'] = $value;
            }

            if ($method == 'http' || $method == 'https') {
                $dcv['file']['name'] = $recode;
                $dcv['file']['path'] = '/.well-known/pki-validation/'.$recode;
                $dcv['file']['content'] = $value;
            }

            return $dcv;
        }

        return null;
    }

    /**
     * 获取全部域名验证信息
     */
    protected function getValidation(string $method, string $recode, string $value, string $domains): ?array
    {
        if ($method) {
            $validation = [];
            foreach (explode(',', $domains) as $k => $domain) {
                $validation[$k]['domain'] = $domain;
                $validation[$k]['method'] = $method;

                if ($method == 'cname') {
                    $validation[$k]['host'] = $recode;
                    $validation[$k]['value'] = $value;
                } elseif ($method == 'http' || $method == 'https') {
                    $validation[$k]['link'] = $method.'://'.$domain.'/.well-known/pki-validation/'.$recode;
                    $validation[$k]['name'] = $recode;
                    $validation[$k]['content'] = $value;
                } else {
                    $validation[$k]['email'] = $method.'@'.DomainUtil::getRootDomain($domain);
                }
            }

            return $validation;
        }

        return null;
    }

    /**
     * 取消订单
     */
    public function cancel(string $apiId): array
    {
        return $this->sdk->cancel($apiId);
    }

    /**
     * 重新验证
     */
    public function revalidate(string $apiId, array $cert = []): array
    {
        return $this->updateDCV($apiId, $cert['dcv']['method'] ?? '', $cert);
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string $apiId, string $method, array $cert = []): array
    {
        $apiMethod = $this->convertDcvToApi($method);
        $domainsArray = explode(',', $cert['alternative_names'] ?? '');

        if (count($domainsArray) > 1) {
            $validation = [];
            foreach ($domainsArray as $domain) {
                $validation[] = [
                    'domainName' => $domain,
                    'dcvMethod' => $apiMethod,
                    'dcvEmail' => $apiMethod === 'EMAIL' ? $method.'@'.DomainUtil::getRootDomain($domain) : '',
                ];
            }

            $result = $this->sdk->batchUpdateDCV($apiId, $validation);

            if ($result['code'] !== 1) {
                return $result;
            }

            $result = $this->sdk->getStatus($apiId);
        } else {
            $domain = $domainsArray[0];
            $email = $apiMethod === 'EMAIL' ? $method.'@'.DomainUtil::getRootDomain($domain) : '';

            $result = $this->sdk->updateDCV($apiId, $apiMethod, $domain, $email);
        }

        if ($result['code'] !== 1) {
            return $result;
        }

        if ($method == 'cname') {
            $recode = $result['data']['DCVdnsHost'] ?? $result['data']['data']['DCVdnsHost'] ?? '';
            $value = $result['data']['DCVdnsValue'] ?? $result['data']['data']['DCVdnsValue'] ?? '';
        }

        if ($method == 'http' || $method == 'https') {
            $recode = $result['data']['DCVfileName'] ?? $result['data']['data']['DCVfileName'] ?? '';
            $value = $result['data']['DCVfileContent'] ?? $result['data']['data']['DCVfileContent'] ?? '';
        }

        return [
            'data' => [
                'dcv' => $this->getDcv($method, $recode ?? '', $value ?? ''),
                'validation' => $this->getValidation($method, $recode ?? '', $value ?? '', $cert['alternative_names'] ?? ''),
            ],
            'code' => 1,
        ];
    }

    /**
     * 删除DCV验证未通过域名
     */
    public function removeMdcDomain(string $apiId): array
    {
        return $this->sdk->batchRemoveMdcDomain($apiId);
    }

    /**
     * 生成唯一值 Unique Value
     */
    protected function generateUniqueValue(): string
    {
        return 'cn'.SnowFlake::generateParticle();
    }

    /**
     * 转换状态为标准
     */
    protected function convertStatusToStandard(string $status): string
    {
        $status = strtolower($status);
        $convert = [
            'pending' => 'processing',
            'complete' => 'active',
        ];
        isset($convert[$status]) && $status = $convert[$status];

        $allStatus = ['processing', 'active', 'cancelled', 'reissued', 'renewed', 'revoked', 'failed', 'expired', 'approving'];
        in_array($status, $allStatus) || ($status = 'failed');

        return strtolower($status);
    }

    /**
     * 转换验证方法为Sectigo
     */
    protected function convertDcvToApi(string $dcv): string
    {
        $convert = [
            'cname' => 'CNAME_CSR_HASH',
            'http' => 'HTTP_CSR_HASH',
            'https' => 'HTTPS_CSR_HASH',
            'admin' => 'EMAIL',
            'administrator' => 'EMAIL',
            'hostmaster' => 'EMAIL',
            'webmaster' => 'EMAIL',
            'postmaster' => 'EMAIL',
        ];

        return $convert[$dcv] ?? $dcv;
    }

    /**
     * 转换验证方法为标准
     */
    protected function convertDcvToStandard(string $dcv): string
    {
        $convert = [
            'CNAME_CSR_HASH' => 'cname',
            'HTTP_CSR_HASH' => 'http',
            'HTTPS_CSR_HASH' => 'https',
            'EMAIL' => 'email',
        ];

        return $convert[$dcv] ?? $dcv;
    }

    /**
     * 转换组织信息为Sectigo
     */
    protected function convertOrganizationToApi(array $organization): array
    {
        $convert = [
            'name' => 'organizationName',
            'phone' => 'organizationMobile',
            'address' => 'organizationAddress',
            'city' => 'organizationCity',
            'state' => 'organizationState',
            'country' => 'organizationCountry',
            'postcode' => 'organizationPostCode',
        ];

        foreach ($convert as $key => $value) {
            $organizationApi[$value] = $organization[$key] ?? '';
        }

        $organizationApi['organizationDuns'] = '';
        $organizationApi['organizationDivision'] = 'IT';

        return $organizationApi;
    }

    /**
     * 转换组织信息为标准
     */
    protected function convertOrganizationToStandard(array $organization): array
    {
        $convert = [
            'organizationName' => 'name',
            'organizationMobile' => 'phone',
            'organizationAddress' => 'address',
            'organizationCity' => 'city',
            'organizationState' => 'state',
            'organizationCountry' => 'country',
            'organizationPostCode' => 'postcode',
        ];

        foreach ($convert as $key => $value) {
            $organizationStandard[$value] = $organization[$key] ?? '';
        }

        return $organizationStandard;
    }

    /**
     * 转换联系人信息为Sectigo管理员
     */
    protected function convertContactToAdministrator(array $contact): array
    {
        $administrator = $this->getDefaultAdministrator();

        $convert = [
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'phone' => 'mobile',
            'email' => 'email',
            'job' => 'job',
        ];

        foreach ($convert as $key => $value) {
            isset($contact[$key]) && $administrator[$value] = $contact[$key];
        }

        return $administrator;
    }

    /**
     * 转换Sectigo管理员信息为联系人
     */
    protected function convertAdministratorToContact(array $administrator): array
    {
        $convert = [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'mobile' => 'phone',
            'email' => 'email',
            'job' => 'job',
        ];

        foreach ($convert as $key => $value) {
            $contact[$value] = $administrator[$key] ?? '';
        }

        return $contact;
    }

    /**
     * 获取Sectigo默认证书管理员
     */
    protected function getDefaultAdministrator(): array
    {
        return [
            'firstName' => 'default',
            'lastName' => 'default',
            'job' => 'IT',
            'mobile' => '13900000000',
            'email' => 'zhuxbo@qq.com',
            'organation' => 'default',
            'address' => 'shanghai',
            'city' => 'shanghai',
            'state' => 'shanghai',
            'country' => 'CN',
            'postCode' => '200000',
        ];
    }

    /**
     * 是否Sectigo默认证书管理员
     */
    protected function isDefaultAdministrator(?array $administrator): bool
    {
        $isDefault_1 = (isset($administrator['firstName']) && $administrator['firstName'] === 'default')
            && (isset($administrator['lastName']) && $administrator['lastName'] === 'default')
            && (isset($administrator['organization']) && $administrator['organization'] === 'default');
        $isDefault_2 = (isset($administrator['firstName']) && $administrator['firstName'] === '安')
            && (isset($administrator['lastName']) && $administrator['lastName'] === '静')
            && (isset($administrator['organization']) && $administrator['organization'] === '上海静安');

        if ($isDefault_1 || $isDefault_2) {
            return true;
        }

        return false;
    }

    /**
     * 获取处理状态
     */
    protected function getProcessStatus(string $status): int
    {
        $statusMap = [
            'notdone' => 0,
            'processing' => 1,
            'done' => 2,
        ];

        return $statusMap[$status] ?? 0;
    }
}
