<?php

declare(strict_types=1);

namespace App\Services\Order\Api\gogetssl;

use App\Services\Order\Utils\DomainUtil;
use App\Utils\SnowFlake;

class Api
{
    private Sdk $sdk;

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

        $prices = $this->sdk->getProduct(intval($code))['data']['product']['prices'] ?? [];

        // 转换 price 结构为 cost 结构
        $cost = $this->convertPricesToCost($prices);

        if ($cost) {
            return [
                'code' => 1,
                'data' => [
                    [
                        'api_id' => $code,
                        'cost' => $cost,
                    ],
                ],
            ];
        } else {
            return ['code' => 0, 'msg' => '未找到产品'];
        }
    }

    /**
     * 转换 prices 结构为 cost 结构
     */
    protected function convertPricesToCost(array $pricesData): ?array
    {
        // 处理基础价格 - 将 prices.{period} 转换为 cost.price.{period}
        foreach ($pricesData as $period => $price) {
            if (is_numeric($period) && is_numeric($price)) {
                // 汇率转换
                $cost['price'][(string) $period] = bcmul((string) $price, '7.5', 2);
            }
        }

        // 处理标准 SAN 价格 - 将 prices.san.{period} 转换为 cost.alternative_standard_price.{period}
        if (isset($pricesData['san']) && is_array($pricesData['san'])) {
            foreach ($pricesData['san'] as $period => $price) {
                if (is_numeric($period) && is_numeric($price)) {
                    // 汇率转换
                    $cost['alternative_standard_price'][(string) $period] = bcmul((string) $price, '7.5', 2);
                }
            }
        }

        // 处理通配符 SAN 价格 - 将 prices.wildcard_san.{period} 转换为 cost.alternative_wildcard_price.{period}
        if (isset($pricesData['wildcard_san']) && is_array($pricesData['wildcard_san'])) {
            foreach ($pricesData['wildcard_san'] as $period => $price) {
                if (is_numeric($period) && is_numeric($price)) {
                    // 汇率转换
                    $cost['alternative_wildcard_price'][(string) $period] = bcmul((string) $price, '7.5', 2);
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
        $params = $this->getNewSslParams($data);
        if ($data['plus'] ?? 1) {
            $result = $this->sdk->renew($params);
        } else {
            $result = $this->sdk->new($params);
        }

        $api_id = $result['data']['order_id'] ?? '';

        if ($result['code'] === 1 && $api_id) {
            return ['data' => ['api_id' => $api_id], 'code' => 1];
        }

        return $result;
    }

    /**
     * 续费SSL
     */
    public function renew(array $data): array
    {
        $params = $this->getNewSslParams($data);
        $result = $this->sdk->renew($params);

        $api_id = $result['data']['order_id'] ?? '';

        if ($result['code'] === 1 && $api_id) {
            return ['data' => ['api_id' => $api_id], 'code' => 1];
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

        $result = $this->sdk->reissue($reissuedId, $params);

        $api_id = $result['data']['order_id'] ?? '';

        if ($result['code'] === 1 && $api_id) {
            return ['data' => ['api_id' => $api_id], 'code' => 1];
        }

        return $result;
    }

    /**
     * 整理购买SSL参数
     */
    protected function getNewSslParams(array $data): array
    {
        $params = $this->getParams($data);

        $params['admin_firstname'] = $data['contact']['first_name'] ?? 'default';
        $params['admin_lastname'] = $data['contact']['last_name'] ?? 'default';
        $params['admin_phone'] = $data['contact']['phone'] ?? '13900000000';
        $params['admin_title'] = $data['contact']['job'] ?? 'IT';
        $params['admin_email'] = $data['contact']['email'] ?? 'zhuxbo@qq.com';

        $params['tech_firstname'] = $params['admin_firstname'];
        $params['tech_lastname'] = $params['admin_lastname'];
        $params['tech_email'] = $params['admin_email'];
        $params['tech_phone'] = $params['admin_phone'];
        $params['tech_title'] = $params['admin_title'];

        if (isset($data['organization'])) {
            $params['org_name'] = $data['organization']['name'] ?? '';
            $params['org_division'] = $data['organization']['division'] ?? 'IT';
            $params['org_addressline1'] = $data['organization']['address'] ?? '';
            $params['org_city'] = $data['organization']['city'] ?? '';
            $params['org_region'] = $data['organization']['state'] ?? '';
            $params['org_country'] = $data['organization']['country'] ?? '';
            $params['org_phone'] = $data['organization']['phone'] ?? '';
            $params['org_postalcode'] = $data['organization']['postcode'] ?? '';

            $params['admin_organization'] = $data['organization']['name'] ?? '';
            $params['admin_addressline1'] = $data['organization']['address'] ?? '';
            $params['admin_city'] = $data['organization']['city'] ?? '';
            $params['admin_country'] = $data['organization']['country'] ?? '';
            $params['admin_fax'] = $data['organization']['phone'] ?? '';

            $params['tech_organization'] = $params['admin_organization'];
            $params['tech_addressline1'] = $params['admin_addressline1'];
            $params['tech_city'] = $params['admin_city'];
            $params['tech_country'] = $params['admin_country'];
        }

        return $params;
    }

    protected function getParams(array $data): array
    {
        $params['server_count'] = '-1';
        $params['webserver_type'] = '-1';
        $params['product_id'] = $data['product_api_id'];
        $params['period'] = $data['period'];
        $params['csr'] = $data['csr'];
        $params['unique_code'] = $data['unique_value'] ?? $this->generateUniqueValue();

        if (str_contains($data['alternative_names'], ',')) {
            $params['dns_names'] = substr($data['alternative_names'], strpos($data['alternative_names'], ',') + 1);
        }

        $params['dcv_method'] = $this->convertDcvToApi($data['dcv']['method']);
        if ($params['dcv_method'] === 'email') {
            $params['approver_email'] = $data['dcv']['method'].'@'.DomainUtil::getRootDomain($data['domain']);
            if (isset($params['dns_names']) && str_contains($params['dns_names'], ',')) {
                $emails = [];
                foreach (explode(',', $params['dns_names']) as $domain) {
                    $emails[] = $data['dcv']['method'].'@'.DomainUtil::getRootDomain($domain);
                }
                $params['approver_emails'] = implode(',', $emails);
            }
        }

        return $params;
    }

    /**
     * 获取订单信息
     */
    public function get(string $api_id): array
    {
        $result = $this->sdk->getStatus($api_id);
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
        $result['vendor_id'] = $data['partner_order_id'] ?? '';
        $result['common_name'] = $data['domain'] ?? '';
        $result['status'] = $this->convertStatusToStandard($data['status'] ?? '');

        $result['cert_apply_status'] = 0;
        $result['domain_verify_status'] = 0;
        $result['org_verify_status'] = 0;

        if ($result['status'] == 'processing') {
            $result['cert_apply_status'] = in_array($data['status'], ['pending', 'unpaid']) ? 0 : 2;
            $result['domain_verify_status'] = in_array($data['status'], ['pending', 'unpaid']) ? 0 : 1;
            $result['org_verify_status'] = in_array($data['status'], ['pending', 'unpaid']) ? 0 : 1;
        }

        if ($result['status'] == 'active') {
            $result['cert_apply_status'] = 2;
            $result['domain_verify_status'] = 2;
            $result['org_verify_status'] = 2;
        }

        // 完成申请的状态才返回validation信息
        if ($result['cert_apply_status'] === 2) {
            $result['dcv'] = $this->getDcv($data['approver_method']);
            $commonValidation = $this->getCommonValidation($result['common_name'], $result['dcv'], $data['dcv_status'] ?? 0);
            $sanValidation = $this->getSanValidation($data['san']);
            $result['validation'] = array_merge($commonValidation, $sanValidation);

            $alternativeNames = [];
            foreach ($result['validation'] as $v) {
                $alternativeNames[] = $v['domain'];
            }
            $result['alternative_names'] = implode(',', $alternativeNames);
        }

        if ($data['admin_firstname'] !== 'default' || $data['admin_lastname'] !== 'default') {
            $result['contact']['first_name'] = $data['admin_firstname'];
            $result['contact']['last_name'] = $data['admin_lastname'];
            $result['contact']['job'] = $data['admin_title'];
            $result['contact']['phone'] = $data['admin_phone'];
            $result['contact']['email'] = $data['admin_email'];
        }

        if (! empty($data['org_name']) || ! empty($data['admin_organization'])) {
            $result['organization']['name'] = $data['org_name'] ?? $data['admin_organization'];
            $result['organization']['address'] = $data['org_addressline1'];
            $result['organization']['city'] = $data['org_city'];
            $result['organization']['state'] = $data['org_region'];
            $result['organization']['country'] = $data['org_country'];
            $result['organization']['phone'] = $data['org_phone'];
            $result['organization']['postcode'] = $data['org_postalcode'];
        }

        $result['csr'] = $data['csr_code'];
        $result['cert'] = str_replace("\r\n", "\n", trim($data['crt_code']));
        $result['intermediate_cert'] = str_replace("\r\n", "\n", trim($data['ca_code']));

        return $result;
    }

    /**
     * 获取 dcv 验证信息
     */
    protected function getDcv(array $approver_method): ?array
    {
        $method = array_keys($approver_method)[0] ?? '';

        if ($method) {
            if ($method == 'dns') {
                $dcv['dns'] = $this->getDnsRecord($approver_method[$method]['record']);
                $dcv['method'] = $dcv['dns']['type'];
            }

            if ($method == 'http' || $method == 'https' || $method == 'file') {
                $dcv['method'] = $method;
                $dcv['file']['name'] = $approver_method[$method]['filename'];
                $dcv['file']['path'] = '/.well-known/pki-validation/'.$dcv['file']['name'];
                $dcv['file']['content'] = str_replace("\r\n", "\n", $approver_method[$method]['content']);
            }

            if ($method == 'email') {
                $dcv['method'] = explode('@', $approver_method['email'])[0];
            }
        }

        return $dcv ?? null;
    }

    /**
     * 获取DNS记录
     */
    protected function getDnsRecord(string $record): ?array
    {
        if (str_contains($record, ' CNAME ')) {
            $record = explode(' CNAME ', $record);
            $record[0] = trim($record[0]);
            $record[1] = trim($record[1]);

            if (count($record) == 2) {
                return [
                    'host' => str_starts_with($record[0], '_')
                        ? substr($record[0], 0, strpos($record[0], '.'))
                        : '@',
                    'type' => 'cname',
                    'value' => strtolower($record[1]),
                ];
            }
        }

        if (str_contains($record, '   IN   TXT   ')) {
            $record = explode('   IN   TXT   ', $record);
            $record[0] = trim($record[0]);
            $record[1] = trim($record[1]);

            if (count($record) == 2) {
                return [
                    'host' => str_starts_with($record[0], '_')
                        ? substr($record[0], 0, strpos($record[0], '.'))
                        : '@',
                    'type' => 'txt',
                    'value' => str_replace('"', '', strtolower($record[1])),
                ];
            }
        }

        return null;
    }

    protected function getCommonValidation(string $commonName, array $dcv, int $dcv_status = 0): array
    {
        $validation = [];
        $validation[0]['domain'] = $commonName;
        $validation[0]['method'] = $dcv['method'];

        if ($dcv['method'] == 'cname' || $dcv['method'] == 'txt') {
            $validation[0]['host'] = $dcv['dns']['host'];
            $validation[0]['value'] = $dcv['dns']['value'];
        } elseif ($dcv['method'] == 'http' || $dcv['method'] == 'https' || $dcv['method'] == 'file') {
            $scheme = $dcv['method'] == 'file' ? '//' : $dcv['method'].'://';
            $validation[0]['link'] = $scheme.$commonName.'/.well-known/pki-validation/'.$dcv['file']['name'];
            $validation[0]['name'] = $dcv['file']['name'];
            $validation[0]['content'] = $dcv['file']['content'];
        } else {
            $validation[0]['email'] = $dcv['method'].'@'.DomainUtil::getRootDomain($commonName);
        }

        $validation[0]['verified'] = $dcv_status == 2 ? 1 : 0;

        return $validation;
    }

    /**
     * 获取全部域名验证信息
     */
    protected function getSanValidation(array $san): array
    {
        if ($san) {
            $validation = [];
            foreach ($san as $k => $v) {
                $validation[$k]['domain'] = $v['san_name'];

                if ($v['validation_method'] == 'dns') {
                    $record = $this->getDnsRecord($v['validation']['dns']['record']);
                    $validation[$k]['method'] = $record['type'];
                    $validation[$k]['host'] = $record['host'];
                    $validation[$k]['value'] = $record['value'];
                } elseif ($v['validation_method'] == 'http' || $v['validation_method'] == 'https' || $v['validation_method'] == 'file') {
                    $scheme = $v['validation_method'] == 'file' ? '//' : $v['validation_method'].'://';
                    $validation[$k]['method'] = $v['validation_method'];
                    $validation[$k]['link'] = $scheme.$v['san_name'].'/.well-known/pki-validation/'.$v['validation'][$v['validation_method']]['filename'];
                    $validation[$k]['name'] = trim($v['validation'][$v['validation_method']]['filename']);
                    $validation[$k]['content'] = str_replace("\r\n", "\n", trim($v['validation'][$v['validation_method']]['content']));
                } else {
                    $validation[$k]['method'] = explode('@', $v['email'])[0];
                    $validation[$k]['email'] = $v['email'];
                }

                $validation[$k]['verified'] = $v['status'] == 2 ? 1 : 0;
            }

            return $validation;
        }

        return [];
    }

    /**
     * 取消订单
     */
    public function cancel(string $api_id): array
    {
        return $this->sdk->cancel($api_id);
    }

    /**
     * 重新验证
     */
    public function revalidate(string $api_id, array $cert): array
    {
        $api_method = $this->convertDcvToApi($cert['dcv']['method'] ?? '');

        $new_domains = [];
        $new_methods = [];

        foreach ($cert['validation'] as $v) {
            if (($v['verified'] ?? 0) == 0) {
                $new_domains[] = $v['domain'];
                $new_methods[] = $api_method === 'email' ? $cert['dcv']['method'].'@'.DomainUtil::getRootDomain($v['domain']) : $api_method;
            }
        }

        if (empty($new_domains)) {
            return ['code' => 0, 'msg' => '没有未验证的域名'];
        }

        return $this->sdk->batchUpdateDCV($api_id, implode(',', $new_domains), implode(',', $new_methods));
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string $api_id, string $method, array $cert): array
    {
        $api_method = $this->convertDcvToApi($method);

        $new_domains = [];
        $new_methods = [];
        foreach ($cert['validation'] as $v) {
            if (($v['verified'] ?? 0) == 0) {
                $new_domains[] = $v['domain'];
                $new_methods[] = $api_method === 'email' ? $method.'@'.DomainUtil::getRootDomain($v['domain']) : $api_method;
            }
        }

        if (empty($new_domains)) {
            return ['code' => 0, 'msg' => '没有未验证的域名'];
        }

        $result = $this->sdk->batchUpdateDCV($api_id, implode(',', $new_domains), implode(',', $new_methods));

        if ($result['code'] !== 1) {
            return $result;
        }

        $result = $this->sdk->getStatus($api_id);

        $dcv = $this->getDcv($result['data']['approver_method'] ?? []);
        $commonValidation = $this->getCommonValidation($result['data']['domain'] ?? '', $dcv, $result['data']['dcv_status'] ?? 0);
        $sanValidation = $this->getSanValidation($result['data']['san'] ?? []);

        return [
            'data' => [
                'dcv' => $dcv,
                'validation' => array_merge($commonValidation, $sanValidation),
            ],
            'code' => 1,
        ];
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
            'incomplete' => 'processing',
            'new_order' => 'processing',
            'unpaid' => 'processing',
            'reissued' => 'processing',
            'rejected' => 'revoked',
        ];
        isset($convert[$status]) && $status = $convert[$status];

        $all_status = ['processing', 'active', 'cancelled', 'reissued', 'renewed', 'revoked', 'failed', 'expired', 'approving'];
        in_array($status, $all_status) || ($status = 'failed');

        return strtolower($status);
    }

    /**
     * 转换验证方法为Sectigo
     */
    protected function convertDcvToApi(string $dcv): string
    {
        $convert = [
            'cname' => 'dns',
            'txt' => 'dns',
            'admin' => 'email',
            'administrator' => 'email',
            'hostmaster' => 'email',
            'webmaster' => 'email',
            'postmaster' => 'email',
        ];

        return $convert[$dcv] ?? $dcv;
    }
}
