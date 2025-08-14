<?php

declare(strict_types=1);

namespace App\Services\Order\Api\certum;

use App\Bootstrap\ApiExceptions;
use App\Models\CaLog;
use SoapClient;
use SoapFault;

class Sdk
{
    /**
     * 获取产品列表
     *
     * @param  bool  $hashAlgorithm  是否返回可用的哈希算法
     */
    public function getProductList(bool $hashAlgorithm = false): array
    {
        $params = [
            'hashAlgorithm' => $hashAlgorithm ? 'true' : 'false',
        ];

        return $this->call('getProductList', $params);
    }

    /**
     * 申请证书
     */
    public function validate(array $params): array
    {
        return $this->call('validateOrderParameters', $params);
    }

    /**
     * 申请证书
     */
    public function new(array $params): array
    {
        return $this->call('quickOrder', $params);
    }

    /**
     * 续费证书
     */
    public function renew(array $params): array
    {
        return $this->call('renewCertificate', $params);
    }

    /**
     * 重签证书
     */
    public function reissue(array $params): array
    {
        return $this->call('reissueCertificate', $params);
    }

    /**
     * 获取订单信息
     */
    public function getOrderState(array $params): array
    {
        return $this->call('getOrderState', $params);
    }

    /**
     * 获取证书信息
     */
    public function getCertificate(array $params): array
    {
        return $this->call('getCertificate', $params);
    }

    /**
     * 获取证书信息
     */
    public function getSanVerificationState(array $params): array
    {
        return $this->call('getSanVerificationState', $params);
    }

    /**
     * 通过备用id获取订单id
     */
    public function getOrderByOrderID(array $params): array
    {
        return $this->call('getOrderByOrderID', $params);
    }

    /**
     * 修改域名验证方式
     */
    public function addSanVerification(array $params): array
    {
        return $this->call('addSanVerification', $params);
    }

    /**
     * 重新验证域名
     */
    public function performSanVerification(array $params): array
    {
        return $this->call('performSanVerification', $params);
    }

    /**
     * 取消订单
     */
    public function cancel(array $params): array
    {
        return $this->call('cancelOrder', $params);
    }

    /**
     * 吊销证书
     */
    public function revoke(array $params): array
    {
        return $this->call('revokeCertificate', $params);
    }

    /**
     * 生成电子邮件验证
     */
    public function addEmailVerification(array $params): array
    {
        return $this->call('addEmailVerification', $params);
    }

    /**
     * 获取电子邮件验证状态
     */
    public function getEmailVerification(array $params): array
    {
        return $this->call('getEmailVerification', $params);
    }

    /**
     * 上传验证文件 base64编码
     */
    public function verifyOrder(array $params): array
    {
        return $this->call('verifyOrder', $params);
    }

    /**
     * 获取验证文件列表
     */
    public function getDocumentsList(array $params): array
    {
        return $this->call('getDocumentsList', $params);
    }

    /**
     * 获取订单列表
     */
    public function getOrdersByDateRange(array $params): array
    {
        return $this->call('getOrdersByDateRange', $params);
    }

    /**
     * 获取被修改的订单列表
     */
    public function getModifiedOrders(array $params): array
    {
        return $this->call('getModifiedOrders', $params);
    }

    /**
     * 获取即将到期的证书
     */
    public function getExpiringCertificates(array $params): array
    {
        return $this->call('getExpiringCertificates', $params);
    }

    /**
     * 提交接口请求
     *
     * @param  string  $action  要调用的操作名称
     * @param  array  $params  要传递给操作的参数
     * @return array 包含从服务返回的所有数据的对象
     */
    public function call(string $action, array $params = []): array
    {
        $apiUrl = get_system_setting('ca', 'certumUrl');
        $username = get_system_setting('ca', 'certumUsername');
        $password = get_system_setting('ca', 'certumPassword');

        if (! $apiUrl || ! $username || ! $password) {
            return ['code' => 0, 'msg' => 'CA 接口配置错误'];
        }

        try {
            $client = new SoapClient($apiUrl, [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => 30,
            ]);
        } catch (SoapFault $e) {
            app(ApiExceptions::class)->logException($e);

            return ['code' => 0, 'msg' => 'CA 接口连接失败'];
        }

        $params = [
            'requestHeader' => [
                'authToken' => [
                    'userName' => $username,
                    'password' => $password,
                ],
            ],
            ...$params,
        ];

        try {
            $result = $client->__soapCall($action, [$params]);

            if (isset($result->responseHeader->errors)) {
                $result->errors = (new Errors)->getErrorText($result->responseHeader->successCode, $result->responseHeader->errors);
            }

            unset($params['requestHeader']);

            CaLog::create([
                'url' => $apiUrl,
                'api' => $action,
                'params' => $params,
                'response' => $result,
                'status_code' => $client->__getLastResponseHeaders()['http_code'] ?? 200,
                'status' => $result?->responseHeader?->successCode === 0 ? 1 : 0,
            ]);

            if (isset($result->errors)) {
                $error = count($result->errors) === 1 ? $result->errors[0] : json_encode($result->errors, JSON_UNESCAPED_UNICODE);

                // 如果是取消订单 已取消 则返回成功
                if ($action === 'cancelOrder' && $error == '订单已取消') {
                    return ['code' => 1, 'msg' => '订单已取消'];
                }

                // 如果是吊销证书 已吊销 则返回成功
                if ($action === 'revokeCertificate' && $error == '无法吊销证书，因为证书已被吊销') {
                    return ['code' => 1, 'msg' => '证书已吊销'];
                }

                return ['code' => 0, 'msg' => (string) $error];
            }
        } catch (SoapFault $e) {
            $requestXml = $client->__getLastRequest();
            $requestXml = preg_replace('/<password>.*?<\/password>/', '<password>******</password>', $requestXml);

            $debugInfo = 'Timestamp: '.date('Y-m-d H:i:s')."\n\n".
                'Action: '.$action."\n\n".
                "Request Headers: \n".$client->__getLastRequestHeaders()."\n\n".
                "Request XML: \n".$requestXml."\n\n".
                "Response Headers: \n".$client->__getLastResponseHeaders()."\n\n".
                "Response XML: \n".$client->__getLastResponse();

            $logPath = storage_path('logs');

            if (! is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }

            $filename = $logPath.'/soap_'.date('Y-m-d').'_'.$action.'_'.uniqid().'.log';
            file_put_contents($filename, $debugInfo);

            $result = is_soap_fault($e) ? [$e->faultstring, $e->detail] : $e->getTrace();

            unset($params['requestHeader']);

            CaLog::create([
                'url' => $apiUrl,
                'api' => $action,
                'params' => $params,
                'response' => $result,
                'status_code' => $client->__getLastResponseHeaders()['http_code'] ?? 400,
                'status' => 0,
            ]);

            return ['code' => 0, 'msg' => 'CA 接口调用失败'];
        }

        return ['code' => 1, 'data' => json_decode(json_encode($result), true)];
    }
}
