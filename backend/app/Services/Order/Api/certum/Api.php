<?php

declare(strict_types=1);

namespace App\Services\Order\Api\certum;

use App\Models\Cert;
use App\Models\Chain;
use App\Services\Order\Utils\DomainUtil;
use App\Utils\Random;
use DateMalformedStringException;
use DateTime;
use Exception;

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
        return ['code' => 1, 'data' => null];
    }

    /**
     * 购买SSL
     *
     * @throws Exception
     */
    public function new(array $data): array
    {
        $params = $this->getNewSslParams($data);
        $result = $this->sdk->new($params);

        return $this->getResult($result, $data['dcv']['method'], $data['alternative_names']);
    }

    /**
     * 续费SSL
     *
     * @throws Exception
     */
    public function renew(array $data): array
    {
        $params = $this->getRenewSslParams($data);
        $result = $this->sdk->renew($params);

        return $this->getResult($result, $data['dcv']['method'], $data['alternative_names']);
    }

    /**
     * 重签SSL
     */
    public function reissue(array $data): array
    {
        $params = $this->getReissueSslParams($data);
        $result = $this->sdk->reissue($params);

        // 重签证书后，如果状态是 processing，需要修改验证方法获取新的验证信息
        return $this->getResult($result, $data['dcv']['method'], $data['alternative_names']);
    }

    /**
     * 封装返回结果
     */
    protected function getResult(array $result, string $method, string $domains): array
    {
        $apiId = $result['data']['orderID'] ?? '';

        if ($result['code'] === 1 && $apiId) {
            $code = $result['data']['SANVerification']['code'] ?? '';

            return [
                'data' => [
                    'api_id' => $apiId,
                    'cert_apply_status' => 2,
                    'dcv' => $this->getDcv($method, $code),
                    'validation' => $this->getValidation($method, $code, $domains),
                ],
                'code' => 1,
            ];
        }

        return $result;
    }

    /**
     * 获取证书哈希算法
     */
    protected function getHashAlgorithm($csr): string
    {
        $csrResource = openssl_csr_get_public_key($csr);
        if ($csrResource !== false) {
            $pkeyDetails = openssl_pkey_get_details($csrResource);

            $type = $pkeyDetails['type'] ?? '-1';
            $type === 0 && $hashAlgorithm = 'RSA-SHA256';
            $type === 3 && $hashAlgorithm = 'ECC-SHA256';
        }

        return $hashAlgorithm ?? 'RSA-SHA25';
    }

    /**
     * 整理购买SSL参数
     *
     * @throws Exception
     */
    protected function getNewSslParams(array $data): array
    {
        $orderParameters = [
            'orderID' => $data['refer_id'] ?? '',  // 自定义订单ID
            'customer' => $data['contact']['email'] ?? Random::build('alpha', 10).'@custom.cnssl.com',  // 客户ID或SimplySign服务的客户登录
            'productCode' => $data['product_api_id'],  // 产品代码，例如SSL或S/MIME产品
            'CSR' => $data['csr'],  // PKCS#10 格式的证书请求
            'hashAlgorithm' => $this->getHashAlgorithm($data['csr']),  // 可选，哈希算法
            'email' => $data['contact']['email'] ?? 'admin@cnssl.cn',  // 订阅者的电子邮件
            'revocationContactEmail' => $data['contact']['email'] ?? 'admin@cnssl.cn',  // 可选，用于吊销通知的电子邮件地址
        ];

        ! $data['plus'] && $orderParameters['shortenedValidityPeriod'] = $this->getShortenedValidityPeriod();  // 可选，证书有效期缩短至此日期

        $SANEntries = $this->getSANEntries($data['alternative_names']);

        $SANApprover = $this->getSANApprover($data['dcv']['method']);

        $params = [
            'orderParameters' => $orderParameters,
            'SANEntries' => $SANEntries,
            'SANApprover' => $SANApprover,
        ];

        if (isset($data['organization'])) {
            $params['orderParameters'] = [
                ...$orderParameters,
                'givenName' => $data['contact']['first_name'] ?? '',  // 名，适用于某些产品
                'surname' => $data['contact']['last_name'] ?? '',  // 姓，适用于某些产品
                'organization' => $data['organization']['name'] ?? '',  // 组织名称
                'locality' => $data['organization']['city'] ?? '',  // 可选，所在城市
                'state' => $data['organization']['state'] ?? '',  // 可选，州或省
                'country' => $data['organization']['country'] ?? '',  // 国家代码
                'businessCategory' => $data['organization']['category'] ?? '',  // 可选，适用于EV证书
                'streetAddress' => $data['organization']['address'] ?? '',  // 可选，适用于EV证书
                'postalCode' => $data['organization']['postcode'] ?? '',  // 可选，邮政编码
                'joILN' => $data['organization']['city'] ?? '',  // 可选，注册地所在城市，适用于EV证书
                'joISoPN' => $data['organization']['state'] ?? '',  // 可选，注册地所在州，适用于EV证书
                'joISoCN' => $data['organization']['country'] ?? '',  // 可选，注册地所在国家，适用于EV证书
            ];

            $params['requestorInfo'] = [
                'email' => $data['contact']['email'] ?? '',  // 申请者的电子邮件地址
                'firstName' => $data['contact']['first_name'] ?? '',  // 申请者的名字
                'lastName' => $data['contact']['last_name'] ?? '',  // 申请者的姓氏
                'phone' => $data['contact']['mobile'] ?? '',  // 可选，申请者的电话
            ];

            $params['organizationInfo'] = [
                'taxIdentificationNumber' => $data['organization']['registration_number'] ?? '',  // 税号或组织注册号，适用于OV和EV证书
            ];
        }

        return $params;
    }

    /**
     * 整理购买SSL参数
     *
     *
     * @throws Exception
     */
    protected function getRenewSslParams(array $data): array
    {
        $orderParameters = [
            'customer' => $data['customer'] ?? Random::build('alpha', 10).'@custom.cnssl.com',  // 客户ID或SimplySign服务的客户登录
            'productCode' => $data['product_api_id'],  // 产品代码，例如SSL或S/MIME产品
            'CSR' => $data['csr'],  // PKCS#10 格式的证书请求
            'hashAlgorithm' => $this->getHashAlgorithm($data['csr']),  // 可选，哈希算法
            'X509Cert' => $data['last_cert'],  // 旧证书
            'revocationContactEmail' => $data['contact']['email'] ?? 'admin@cnssl.cn',  // 可选，用于吊销通知的电子邮件地址
            // 'serialNumber'           => $data['serial_number'],  // 可选，适用于EV证书
        ];

        ! $data['plus'] && $orderParameters['shortenedValidityPeriod'] = $this->getShortenedValidityPeriod();  // 可选，证书有效期缩短至此日期

        $SANApprover = $this->getSANApprover($data['dcv']['method']);

        return [
            ...$orderParameters,
            'SANApprover' => $SANApprover,
        ];
    }

    /**
     * 整理购买SSL参数
     */
    protected function getReissueSslParams(array $data): array
    {
        $orderParameters = [
            'CSR' => $data['csr'],  // PKCS#10 格式的证书请求
            'hashAlgorithm' => $this->getHashAlgorithm($data['csr']),  // 可选，哈希算法
            'X509Cert' => $data['last_cert'],  // 旧证书
            // 'serialNumber' => $data['serial_number'],  // 可选，适用于EV证书
        ];

        $SANEntries = $this->getSANEntries($data['alternative_names']);

        $SANApprover = $this->getSANApprover($data['dcv']['method']);

        return [
            ...$orderParameters,
            'SANEntries' => $SANEntries,
            'SANApprover' => $SANApprover,
        ];
    }

    /**
     * 获取验证参数
     */
    protected function getSANApprover(string $method): array
    {
        return [
            'approverMethod' => $this->convertDcvToApi($method),
            'approverEmailPrefix' => in_array($method, ['admin', 'administrator', 'postmaster', 'hostmaster', 'webmaster']) ? strtoupper($method) : '',
            'verificationNotificationEnabled' => false,  // 可选，是否发送验证电子邮件
        ];
    }

    /**
     * 获取签发有效期
     *
     *
     * @throws DateMalformedStringException
     */
    protected function getShortenedValidityPeriod(int $days = 365): string
    {
        $currentDate = new DateTime;
        $currentDate->modify('+'.$days.' days');

        return $currentDate->format('Y-m-d');
    }

    /**
     * 根据refer_id获取证书ID
     */
    public function getApiIdByReferId(string $referId): array
    {
        $result = $this->sdk->getOrderState(['orderID' => $referId]);

        if ($result['code'] === 1) {
            return ['data' => ['api_id' => $referId], 'code' => 1];
        }

        return $result;
    }

    /**
     * 获取订单信息
     */
    public function get(string $apiId): array
    {
        // 基础请求参数
        $baseParams = [
            'orderID' => $apiId,
            'orderOption' => [
                'orderStatus' => true,
                'orderDetails' => true,
                'certificateDetails' => true,
            ],
        ];

        // 获取订单信息
        $orderResult = $this->sdk->getOrderByOrderID($baseParams);

        // 处理返回结果
        $order = [];

        // 处理主订单数据
        if ($orderResult['code'] === 1) {
            $data = $orderResult['data'];
            if (isset($data['orders']['Order']['orderStatus'])) {
                $order = $data['orders']['Order'] ?? [];
            } else {
                // 重签证书获取最后一次签发的
                $order_count = count($data['orders']['Order'] ?? []);
                if ($order_count == 0) {
                    return ['msg' => '获取证书信息错误，请稍后再试', 'code' => 0];
                }
                $order = $data['orders']['Order'][$order_count - 1];
            }
        }

        // 如果没有获取到主订单信息
        if (empty($order)) {
            return ['msg' => '获取证书信息错误，请稍后再试', 'code' => 0];
        }

        $order['api_id'] = $apiId;

        return $this->getSsl($order);
    }

    /**
     * 整理SSL信息
     */
    protected function getSsl(array $order): array
    {
        // 处理状态
        $data['status'] = $this->convertStatusToStandard($order['orderStatus']['orderStatus'] ?? '');
        if (in_array($order['certificateDetails']['certificateStatus'] ?? '', ['REVOKING', 'REVOKED'])) {
            $data['status'] = 'revoked';
        }

        $data['vendor_id'] = $order['orderStatus']['orderID'] ?? '';

        if ($data['status'] == 'processing') {
            $data['cert_apply_status'] = 2;
            $data['domain_verify_status'] = 1;
            $data['org_verify_status'] = 1;
        }

        if ($data['status'] == 'approving' || $data['status'] == 'active') {
            $data['cert_apply_status'] = 2;
            $data['domain_verify_status'] = 2;
            $data['org_verify_status'] = 2;
        }

        if ($data['status'] === 'processing') {
            $result = $this->sdk->getSanVerificationState(['orderID' => $order['api_id']]);
            if ($result['code'] === 1) {
                if ($sans = ($result['data']['sanVerifications']['sanVerification'] ?? false)) {
                    // 只有一个域名的时候 转换为数组
                    if (isset($sans['FQDN'])) {
                        $sans = [$sans];
                    }

                    foreach ((array) $sans as $k => $v) {
                        $data['alternative_names'][] = $v['FQDN'] ?? '';
                        $data['validation'][$k]['domain'] = $v['FQDN'] ?? '';
                        $data['validation'][$k]['verified'] = (($v['manualVerification']['state'] ?? '') == 'VERIFIED') ? 1 : 0;

                        if (isset($v['manualVerification']['expireDate'])) {
                            $data['validation'][$k]['expires_date'] = (int) strtotime($v['manualVerification']['expireDate']);
                        }

                        // 系统验证 有信息就表示出错
                        if (isset($v['systemVerification']['method'])) {
                            $data['validation'][$k]['verified'] = 2;
                            $data['validation'][$k]['error']['system'] = $v['systemVerification']['method'];
                            if (isset($v['systemVerification']['info'])) {
                                $data['validation'][$k]['error']['info'] = $v['systemVerification']['info'];
                            }
                        }
                    }

                    if (isset($data['alternative_names'])) {
                        $data['alternative_names'] = implode(',', array_unique($data['alternative_names']));
                    }
                }
            }
        }

        if ($data['status'] == 'active') {
            $cert = Cert::where('api_id', $order['api_id'])->where('status', 'active')->first();

            if ($cert) {
                foreach ($cert->validation as $v) {
                    unset($v['error']);
                    $v['verified'] = 1;
                    $data['validation'][] = $v;
                }
            }

            $data['cert'] = $this->formatCertificate($order['certificateDetails']['X509Cert'] ?? '');

            $certInfo = openssl_x509_parse($data['cert']);

            // 如果证书解析失败
            if (! $certInfo) {
                return ['msg' => '获取证书链错误，请稍后再试', 'code' => 0];
            }

            // 查询中间证书
            $chain = Chain::where('common_name', $certInfo['issuer']['CN'] ?? '')->first();

            if (! $chain) {
                $result = $this->sdk->getCertificate(['orderID' => $order['api_id']]);

                if ($ca = $result['data']['caBundle']['X509Cert'] ?? false) {
                    $intermediateCert = '';
                    foreach ($ca as $v) {
                        $intermediateCert .= str_replace("\r\n", "\n", trim($v))."\n";
                    }
                    $data['intermediate_cert'] = rtrim($intermediateCert);
                }
            }
        }

        return ['data' => $data, 'code' => 1];
    }

    /**
     * 获取 dcv 验证信息
     *
     * @param  string  $method  $api_result_data['SANVerification']['approverMethod']
     * @param  string  $code  $api_result_data['SANVerification']['code']
     */
    protected function getDcv(string $method, string $code): ?array
    {
        if ($method) {
            $dcv['method'] = $method;

            if ($method == 'txt') {
                $dcv['dns']['host'] = '_certum';
                $dcv['dns']['type'] = mb_strtoupper($method);
                $dcv['dns']['value'] = $code;
            }

            if ($method == 'cname') {
                $dcv['dns']['host'] = '_certum';
                $dcv['dns']['type'] = mb_strtoupper($method);
                $dcv['dns']['value'] = $code.'.certum.pl';
            }

            if ($method == 'file') {
                $dcv['file']['name'] = 'certum.txt';
                $dcv['file']['path'] = '/.well-known/pki-validation/certum.txt';
                $dcv['file']['content'] = $code.'-certum.pl';
            }

            return $dcv;
        }

        return null;
    }

    /**
     * 获取全部域名验证信息
     */
    protected function getValidation(string $method, string $code, string $domains): ?array
    {
        if ($method) {
            $validation = [];
            foreach (explode(',', $domains) as $k => $v) {
                $validation[$k]['domain'] = $v;
                $validation[$k]['method'] = $method;

                if ($method == 'txt') {
                    $validation[$k]['host'] = '_certum';
                    $validation[$k]['value'] = $code;
                } elseif ($method == 'cname') {
                    $validation[$k]['host'] = '_certum';
                    $validation[$k]['value'] = $code.'.certum.pl';
                } elseif ($method == 'file') {
                    $validation[$k]['link'] = '//'.$v.'/.well-known/pki-validation/certum.txt';
                    $validation[$k]['name'] = 'certum.txt';
                    $validation[$k]['content'] = $code.'-certum.pl';
                } else {
                    $validation[$k]['email'] = $method.'@'.DomainUtil::getRootDomain($v);
                }
            }

            return $validation;
        }

        return null;
    }

    /**
     * 取消订单
     */
    public function cancel(string $apiId, array $cert = []): array
    {
        $serialNumber = $cert['serial_number'] ?? '';

        if ($serialNumber) {
            $result = $this->sdk->revoke([
                'revokeCertificateParameters' => [
                    'serialNumber' => $serialNumber,
                ],
            ]);
        } else {
            $result = $this->sdk->cancel(['cancelParameters' => ['orderID' => $apiId]]);
        }

        if ($result['code'] !== 1) {
            $orderResult = $this->sdk->getOrderByOrderID([
                'orderID' => $apiId,
                'orderOption' => [
                    'orderStatus' => true,
                ],
            ]);

            if ($orderResult['code'] === 1) {
                $data = $orderResult['data'];

                if (isset($data['orders']['Order']['orderStatus'])) {
                    $order = $data['orders']['Order'] ?? [];
                } else {
                    // 重签证书获取最后一次签发的
                    $orderCount = count($data['orders']['Order'] ?? []);
                    if ($orderCount > 0) {
                        $order = $data['orders']['Order'][$orderCount - 1] ?? [];
                    }
                }

                $status = $this->convertStatusToStandard($order['orderStatus']['orderStatus'] ?? '');
                if ($status === 'active') {
                    $result = $this->sdk->revoke([
                        'revokeCertificateParameters' => [
                            'serialNumber' => $order['orderStatus']['serialNumber'] ?? '',
                        ],
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * 重新验证
     */
    public function revalidate(string $apiId, array $cert = []): array
    {
        $dcv = $cert['dcv'] ?? [];

        if (isset($dcv['method'])) {
            if ($dcv['method'] == 'txt') {
                $code = $dcv['dns']['value'] ?? '';
            }

            if ($dcv['method'] == 'cname') {
                $code = str_replace('.certum.pl', '', ($dcv['dns']['value'] ?? ''));
            }

            if ($dcv['method'] == 'file') {
                $code = str_replace('-certum.pl', '', ($dcv['file']['content'] ?? ''));
            }
        } else {
            return ['msg' => '没有验证方法', 'code' => 0];
        }

        return $this->sdk->performSanVerification(['code' => $code ?? '']);
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string $apiId, string $method, array $cert = []): array
    {
        $params['orderID'] = $apiId;

        $params['SANApprover'] = $this->getSANApprover($method);

        $result = $this->sdk->addSanVerification($params);

        if ($result['code'] !== 1) {
            return $result;
        }

        $code = $result['data']['SANVerification']['code'] ?? '';

        return [
            'data' => [
                'dcv' => $this->getDcv($method, $code),
                'validation' => $this->getValidation($method, $code, $cert['alternative_names'] ?? ''),
            ],
            'code' => 1,
        ];
    }

    /**
     * 重新发送验证邮件
     * 适用于 S/MIME and 文档签名
     */
    public function addEmailVerification(string $apiId): array
    {
        return $this->sdk->addEmailVerification(['orderID' => $apiId]);
    }

    /**
     * 获取邮件验证状态
     * 适用于 S/MIME and 文档签名
     */
    public function getEmailVerification(string $apiId): array
    {
        return $this->sdk->getEmailVerification(['orderID' => $apiId]);
    }

    /**
     * 上传验证文件 base64编码
     *
     * @param  array  $document  ['type', 'fileName', 'content', 'description']
     */
    public function verifyOrder(string $apiId, array $document, string $note = 'report'): array
    {
        return $this->sdk->verifyOrder([
            'verifyOrderParameters' => [
                'orderID' => $apiId,
                'documents' => [
                    'document' => [
                        'type' => $document['type'],
                        'files' => [
                            'file' => [
                                'fileName' => $document['fileName'],
                                'content' => $document['content'],
                            ],
                        ],
                        'description' => $document['description'],
                    ],
                ],
                'note' => $note,
            ],
        ]);
    }

    /**
     * 获取验证文件列表
     */
    public function getDocumentsList(string $apiId): array
    {
        return $this->sdk->getDocumentsList(['orderID' => $apiId]);
    }

    /**
     * 获取订单列表
     */
    public function getOrdersByDateRange(string $fromDate, string $toDate, int $pageNumber): array
    {
        return $this->sdk->getOrdersByDateRange([
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'pageNumber' => $pageNumber,
            'orderOption' => [
                'orderStatus' => true,
                'orderDetails' => true,
                'certificateDetails' => true,
            ],
        ]);
    }

    /**
     * 获取被修改的订单列表
     */
    public function getModifiedOrders(string $fromDate, string $toDate, int $pageNumber): array
    {
        return $this->sdk->getModifiedOrders([
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'pageNumber' => $pageNumber,
            'orderOption' => [
                'orderStatus' => true,
                'orderDetails' => true,
                'certificateDetails' => true,
            ],
        ]);
    }

    /**
     * 获取即将到期的证书
     *
     * @param  string  $validityDaysLeft  有效期剩余天数 1-30
     */
    public function getExpiringCertificates(string $validityDaysLeft, int $pageNumber): array
    {
        return $this->sdk->getExpiringCertificates([
            'validityDaysLeft' => $validityDaysLeft,
            'pageNumber' => $pageNumber,
        ]);
    }

    /**
     * 转换状态为标准
     */
    protected function convertStatusToStandard(string $status): string
    {
        $status = strtolower($status);
        $convert = [
            'awaiting' => 'processing',
            'verification' => 'processing',
            'accepted' => 'approving',
            'enrolled' => 'active',
            'rejected' => 'cancelled',
        ];
        isset($convert[$status]) && $status = $convert[$status];

        $allStatus = ['processing', 'active', 'cancelled', 'reissued', 'renewed', 'revoked', 'failed', 'expired', 'approving'];

        in_array($status, $allStatus) || ($status = 'failed');

        return strtolower($status);
    }

    /**
     * 转换验证方法为certum
     */
    protected function convertDcvToApi(string $dcv): string
    {
        $convert = [
            'txt' => 'DNS_TXT_PREFIX',
            'cname' => 'DNS_CNAME_PREFIX',
            'file' => 'FILE',
            'admin' => 'ADMIN',
            'administrator' => 'ADMIN',
            'postmaster' => 'ADMIN',
            'hostmaster' => 'ADMIN',
            'webmaster' => 'ADMIN',
        ];

        return $convert[$dcv] ?? $dcv;
    }

    /**
     * 获取SANEntries
     */
    protected function getSANEntries(string $alternativeNames): array
    {
        $domains = explode(',', $alternativeNames);

        $SANEntries = [];
        foreach ($domains as $domain) {
            $SANEntries['SANEntry'][] = ['DNSName' => $domain];
        }

        return $SANEntries;
    }

    protected function formatCertificate(string $cert): string
    {
        if (empty($cert)) {
            return '';
        }

        // 去除所有空白字符和换行
        $cert = preg_replace('/\s+/', '', trim($cert));

        // 移除标记以便格式化
        $cert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $cert);

        // 将证书内容格式化为每行64个字符
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $formattedCert = chunk_split($cert, 64, "\n");

        // 重新添加标记
        return "-----BEGIN CERTIFICATE-----\n".$formattedCert.'-----END CERTIFICATE-----';
    }
}
