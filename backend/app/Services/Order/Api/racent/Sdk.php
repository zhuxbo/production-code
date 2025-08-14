<?php

declare(strict_types=1);

namespace App\Services\Order\Api\racent;

use App\Bootstrap\ApiExceptions;
use App\Models\CaLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Sdk
{
    /**
     * 获取产品
     */
    public function getProducts(string $vendor = ''): array
    {
        return $this->call('productList', ['vendor' => $vendor]);
    }

    /**
     * 申请证书
     */
    public function new(string $productCode, int $years, array $params): array
    {
        $data['productCode'] = $productCode;
        $data['years'] = $years;
        $data['refId'] = $params['refId'] ?? '';
        unset($params['refId']);
        $data['params'] = json_encode($params, JSON_UNESCAPED_UNICODE);

        return $this->call('place', $data);
    }

    /**
     * 续费证书
     */
    public function renew(string $renewId, int $years, array $params): array
    {
        $data['renewId'] = $renewId;
        $data['years'] = $years;
        $data['refId'] = $params['refId'] ?? '';
        unset($params['refId']);
        $data['params'] = json_encode($params, JSON_UNESCAPED_UNICODE);

        return $this->call('renew', $data);
    }

    /**
     * 替换证书信息
     */
    public function replace(string $certId, array $params): array
    {
        $data['certId'] = $certId;
        $data['refId'] = $params['refId'] ?? '';
        unset($params['refId']);
        $data['params'] = json_encode($params, JSON_UNESCAPED_UNICODE);

        return $this->call('replace', $data);
    }

    /**
     * 修改验证方法
     */
    public function updateDCV(string $certId, string $dcvMethod, string $domainName, string $dcvEmail = ''): array
    {
        $data['certId'] = $certId;
        $data['dcvMethod'] = $dcvMethod;
        $data['domainName'] = $domainName;
        if ($dcvMethod == 'EMAIL') {
            $data['dcvEmail'] = $dcvEmail;
        }

        return $this->call('updateDCV', $data);
    }

    /**
     * 多域名修改验证方法
     */
    public function batchUpdateDCV(string $certId, array $domainInfo): array
    {
        $data['certId'] = $certId;
        $data['domainInfo'] = json_encode($domainInfo, JSON_UNESCAPED_UNICODE);

        return $this->call('batchUpdateDCV', $data);
    }

    /**
     * 删除DCV验证未通过域名
     */
    public function removeMdcDomain(string $certId, string $domain): array
    {
        $data['certId'] = $certId;
        $data['domainName'] = $domain;

        return $this->call('removeMdcDomain', $data);
    }

    /**
     * 删除全部DCV验证未通过域名
     */
    public function batchRemoveMdcDomain(string $certId): array
    {
        $data['certId'] = $certId;
        // 接口bug，必须传入domainName，待接口修复后移除此行
        $data['domainName'] = '*';

        return $this->call('batchRemoveMdcDomain', $data);
    }

    /**
     * 根据 refId 获取订单 ID
     */
    public function getCertIdByReferId(string $refId): array
    {
        $data['refId'] = $refId;

        return $this->call('certIdByrefId', $data);
    }

    /**
     * 获取订单信息
     */
    public function getStatus(string $certId): array
    {
        $data['certId'] = $certId;

        return $this->call('collect', $data);
    }

    /**
     * 取消证书
     */
    public function cancel(string $certId): array
    {
        $data['certId'] = $certId;
        $data['reason'] = 'Other';

        return $this->call('cancel', $data);
    }

    /**
     * 提交接口请求
     */
    protected function call(string $uri, array $data = []): array
    {
        $apiUrl = get_system_setting('ca', 'racentUrl');
        $apiToken = get_system_setting('ca', 'racentToken');

        if (! $apiUrl || ! $apiToken) {
            return ['code' => 0, 'msg' => 'CA 接口配置错误'];
        }

        // 提前记录请求参数避免日志存储token
        $params = $data;
        if (isset($params['params']) && is_string($params['params'])) {
            $params['params'] = json_decode($params['params'], true);
        }

        $url = $apiUrl.$uri;
        $data['api_token'] = $apiToken;

        $client = new Client;
        try {
            $response = $client->request('post', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'form_params' => $data,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            app(ApiExceptions::class)->logException($e);
            return ['code' => 0, 'msg' => 'CA 接口连接失败'];
        }

        $result = json_decode($response->getBody()->getContents(), true);
        isset($result['status']) && ($result['data']['status'] = $result['status']); // 证书状态

        // 错误信息
        $errorCode = [
            '400' => 'Request failed: 权限验证失败',
            '-1' => '参数验证失败，请联系管理员。',
            '-2' => '意外错误，请联系管理员。',
            '-3' => 'Request failed: 产品或产品定价不正确',
            '-4' => '余额不足',
            '-5' => '订单状态错误，请联系管理员。',
            '-6' => '订单取消失败，请联系管理员。',
            '-7' => '证书状态错误，请联系管理员。',
            '-8' => '订单已被取消',
            '2' => '证书正在签发中，请稍后再试',
        ];

        // 修正 -6 错误码
        if (in_array($uri, ['updateDCV', 'batchUpdateDCV', 'removeMdcDomain', 'batchRemoveMdcDomain']) && ($result['code'] ?? 1) == '-6') {
            $result['code'] = '2';
        }

        $result['error'] = $errorCode[$result['code'] ?? '1'] ?? '';

        $httpStatusCode = $response->getStatusCode();

        CaLog::create([
            'url' => $apiUrl,
            'api' => $uri,
            'params' => $params,
            'response' => $result,
            'status_code ' => $httpStatusCode,
            'status' => intval($result['code'] ?? 0) === 1 ? 1 : 0,
        ]);

        if ($httpStatusCode == 200) {
            if (! isset($result['code'])) {
                return ['code' => 0, 'msg' => 'No return code'];
            }

            if ($result['code'] !== 1) {
                // 取消订单时，如果订单已被取消，返回成功
                if ($result['code'] == '-8' && $uri == 'cancel') {
                    return ['code' => 1, 'data' => null];
                }

                if (in_array($result['code'], ['-1', '-2', '-5', '-6', '-7', '-8', '2'])) {
                    $msg = $result['error'];
                }

                return ['code' => 0, 'msg' => $msg ?? 'Unknown error.', 'errors' => $result['errors'] ?? null];
            }
        } else {
            return ['code' => 0, 'msg' => 'Http status code '.$httpStatusCode];
        }

        return ['code' => 1, 'data' => $result['data'] ?? null];
    }
}
