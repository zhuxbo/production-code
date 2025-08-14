<?php

declare(strict_types=1);

namespace App\Services\Order\Api\racentdomestic;

use App\Services\Order\Api\racent\Api as RacentApi;
use App\Services\Order\Utils\DomainUtil;

class Api extends RacentApi
{
    public function __construct()
    {
        return parent::__construct();
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

                if ($method == 'txt') {
                    $result['validation'][$k]['host'] = $data['DCVdnsHost'] ?? '';
                    $result['validation'][$k]['value'] = $data['DCVdnsValue'] ?? '';
                } elseif ($method == 'file') {
                    $result['validation'][$k]['link'] = '//'.$v['domainName'].'/.well-known/pki-validation/'.($data['DCVfileName'] ?? '');
                    $result['validation'][$k]['name'] = $data['DCVfileName'] ?? '';
                    $result['validation'][$k]['content'] = $data['DCVfileContent'] ?? '';
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
                        if ($v['method'] == 'txt') {
                            $result['dcv']['dns']['host'] = $v['host'];
                            $result['dcv']['dns']['type'] = $v['method'];
                            $result['dcv']['dns']['value'] = $v['value'];
                        } elseif ($v['method'] == 'file') {
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

            if ($method == 'txt') {
                $dcv['dns']['host'] = $recode;
                $dcv['dns']['type'] = $method;
                $dcv['dns']['value'] = $value;
            }

            if ($method == 'file') {
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

                if ($method == 'txt') {
                    $validation[$k]['host'] = $recode;
                    $validation[$k]['value'] = $value;
                } elseif ($method == 'file') {
                    $validation[$k]['link'] = '//'.$domain.'/.well-known/pki-validation/'.$recode;
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

        if ($method == 'txt') {
            $recode = $result['data']['DCVdnsHost'] ?? $result['data']['data']['DCVdnsHost'] ?? '';
            $value = $result['data']['DCVdnsValue'] ?? $result['data']['data']['DCVdnsValue'] ?? '';
        }

        if ($method == 'file') {
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
     * 转换验证方法为Sectigo
     */
    protected function convertDcvToApi(string $dcv): string
    {
        $convert = [
            'txt' => 'CNAME_CSR_HASH',
            'file' => 'HTTP_CSR_HASH',
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
            'CNAME_CSR_HASH' => 'txt',
            'HTTP_CSR_HASH' => 'file',
            'EMAIL' => 'email',
        ];

        return $convert[$dcv] ?? $dcv;
    }
}
