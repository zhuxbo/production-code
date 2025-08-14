<?php

declare(strict_types=1);

namespace App\Services\OrderService\Utils;

use App\Traits\ApiResponseStatic;

class CsrUtil
{
    use ApiResponseStatic;

    const string DEFAULT_ENCRYPTION_ALGORITHM = 'rsa';

    const int DEFAULT_BITS = 2048;

    const string DEFAULT_CURVE = 'prime256v1';

    const string DEFAULT_DIGEST_ALGORITHM = 'sha256';

    /**
     * 自动生成CSR
     */
    public static function auto($params): array
    {
        if ($params['csr_generate'] ?? 0) {
            $result = self::generate($params);
            $params['csr'] = $result['csr'];
            $params['private_key'] = $result['private_key'];
        } else {
            self::checkDomain($params['csr'] ?? '', explode(',', $params['domains'])[0]);

            if (isset($params['private_key'])) {
                self::matchKey($params['csr'], $params['private_key']) || self::error('CSR and private key do not match');
            }

            if (isset($params['organization']['organization'])) {
                self::checkOrganization($params['csr'] ?? '', $params['organization']['organization']);
            }
        }

        return $params;
    }

    /**
     * 生成CSR
     */
    public static function generate(array $params): array
    {
        $encryption = self::getEncryptionParams($params);
        $info = self::getInfoParams($params);

        if ($encryption['alg'] == 'rsa') {
            $pkeyEncryption = [
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'private_key_bits' => $encryption['bits'],
            ];
        } elseif ($encryption['alg'] == 'ecdsa') {
            $pkeyEncryption = [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => $encryption['curve'],
            ];
        }

        $pkey = openssl_pkey_new($pkeyEncryption ?? []);
        ($pkey === false) && self::error('Failed to generate private key');

        $csr = openssl_csr_new($info, $pkey, ['digest_alg' => $encryption['digest_alg']]);
        ($csr === false) && self::error('Failed to generate CSR');

        openssl_csr_export($csr, $csrOut);
        openssl_pkey_export($pkey, $keyOut);
        (! $csrOut || ! $keyOut) && self::error('Failed to export CSR or private key');

        $data['csr'] = str_replace("\r\n", "\n", trim($csrOut));
        $data['private_key'] = str_replace("\r\n", "\n", trim($keyOut));

        return $data;
    }

    /**
     * 获取加密参数
     */
    public static function getEncryptionParams(array $params = []): array
    {
        $alg = strtolower($params['encryption']['alg'] ?? '');
        $bits = intval($params['encryption']['bits'] ?? 0);
        $digestAlg = strtolower($params['encryption']['digest_alg'] ?? '');

        $encryption['alg'] = in_array($alg, ['rsa', 'ecdsa'])
            ? $params['encryption']['alg']
            : self::DEFAULT_ENCRYPTION_ALGORITHM;

        if ($encryption['alg'] == 'rsa') {
            $encryption['bits'] = in_array($bits, [2048, 4096]) ? $bits : self::DEFAULT_BITS;
        }

        if ($encryption['alg'] == 'ecdsa') {
            $allowedCurves = [256 => 'prime256v1', 384 => 'secp384r1', 521 => 'secp521r1'];
            $encryption['curve'] = $allowedCurves[$bits] ?? self::DEFAULT_CURVE;
        }

        $encryption['digest_alg'] = in_array($digestAlg, ['sha256', 'sha384', 'sha512'])
            ? $digestAlg
            : self::DEFAULT_DIGEST_ALGORITHM;

        return $encryption;
    }

    /**
     * 获取信息参数
     */
    public static function getInfoParams(array $params = []): array
    {
        $organization = $params['organization'] ?? [];

        $info['organizationName'] = $organization['name'] ?? '';

        $info['commonName'] = explode(',', $params['domains'])[0];

        // commonName 不能超过 64个字符
        strlen($info['commonName']) > 64 && self::error('The Common Name (CN) for the DV certificate CSR cannot exceed 64 characters');

        $info['countryName'] = $organization['country'] ?? 'CN';
        $info['stateOrProvinceName'] = $organization['state'] ?? 'Shanghai';
        $info['localityName'] = $organization['city'] ?? 'Shanghai';

        return array_filter($info);
    }

    /**
     * 检查域名
     */
    public static function checkDomain(string $csr, string $domain): void
    {
        $info = self::parseCsr($csr);

        ($info['commonName'] != $domain) && self::error('CSR Common Name does not match the Cert Common Name');
    }

    /**
     * 检查组织
     */
    public static function checkOrganization(string $csr, array $organizationName): void
    {
        $info = self::parseCsr($csr);

        (($info['organizationName'] ?? '') != $organizationName) && self::error('CSR organization name does not match the params organization name');
    }

    /**
     * 匹配私钥
     */
    public static function matchKey(string $csr, string $key): bool
    {
        $privateKey = openssl_pkey_get_private($key);
        $publicKey = openssl_csr_get_public_key($csr);

        if ($privateKey === false || $publicKey === false) {
            return false;
        }

        $privateKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyDetails = openssl_pkey_get_details($publicKey);

        return $privateKeyDetails['bits'] === $publicKeyDetails['bits']
            && $privateKeyDetails['key'] === $publicKeyDetails['key'];
    }

    /**
     * 解析CSR
     */
    protected static function parseCsr(string $csr): array
    {
        $csr || self::error('CSR is empty');

        $info = openssl_csr_get_subject($csr, false);

        $info || self::error('CSR parse error');

        return $info;
    }
}
