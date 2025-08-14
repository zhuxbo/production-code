<?php

declare(strict_types=1);

namespace App\Services\Order\Utils;

use App\Traits\ApiResponseStatic;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 重构的验证服务类
 *
 * 将验证规则和特殊验证逻辑统一整合在此类中。
 * 使用方式：
 *
 * ValidatorService::validate($params);
 *
 * $params 为待验证的数据数组，其中的键值可包含：
 *   - action / channel / plus / auto_verify / refer_id / unique_value
 *   - contact / organization / domains / period / validation_method / product 等
 *
 * 基本规则验证使用 Laravel 的 Validator，
 * 额外特殊验证逻辑通过本类的专有方法进行处理。
 *
 * 需根据传入的 $params 中是否存在对应键来决定是否进行对应的验证：
 *   - 存在 contact 则调用 validateContact
 *   - 存在 organization 则调用 validateOrganization
 *   - 存在 domains 则调用 validateDomains
 *   - 存在 period 则调用 validatePeriod
 *   - 存在 validation_method 则调用 validateValidationMethod
 *   - 存在 encryption 则调用 validateEncryption
 *   - 其他字段例如 action、channel、plus、auto_verify、refer_id、unique_value 使用全局 rules 校验
 *
 * 完整性说明：
 *  - 包含特殊验证方法：validateDomains、validateSansMaxCount、validateDomain、validatePeriod、validateValidationMethod、validateEncryption。
 *  - 使用 $rules 数组直接定义所有字段对应的验证规则与属性名。
 */
class ValidatorUtil
{
    use ApiResponseStatic;

    /**
     * 验证规则配置（整合所有字段规则）
     */
    protected static array $rules = [];

    // auto_verify 仅在API提交时验证
    protected static function init(): void
    {
        self::$rules = [
            'basic' => [
                'rules' => [
                    'action' => ['required', 'in:new,renew,reissue'],
                    'channel' => ['required', 'in:web,admin,api,acme'],
                    'plus' => ['in:0,1'],
                    'auto_verify' => ['in:0,1'],
                    'refer_id' => ['alpha_num', 'size:32', 'unique:certs,refer_id'],
                    'unique_value' => ['alpha_num', 'between:16,24', 'unique:certs,unique_value,null,id,order_id,null'],
                    'order_id' => ['required_if:action,renew,reissue', 'numeric'],
                ],
                'attributes' => [
                    'action' => '操作',
                    'channel' => '来源',
                    'plus' => '是否赠送时间',
                    'auto_verify' => '是否自动验证',
                    'refer_id' => '参考ID',
                    'unique_value' => '参考ID',
                    'order_id' => '订单ID',
                ],
            ],
            'contact' => [
                'rules' => [
                    'first_name' => ['required', 'between:1,16'],
                    'last_name' => ['required', 'between:1,40'],
                    'title' => ['required', 'between:2,16'],
                    'email' => ['required', 'email', 'between:6,64'],
                    'phone' => ['required', 'numeric', 'digits_between:5,15'],
                ],
                'attributes' => [
                    'first_name' => '管理员-名',
                    'last_name' => '管理员-姓',
                    'title' => '管理员-职位',
                    'email' => '管理员-邮箱',
                    'phone' => '管理员-电话',
                ],
            ],
            'organization' => [
                'rules' => [
                    'name' => ['required', 'between:2,64'],
                    'registration_number' => ['required', 'between:6,32'],
                    'phone' => ['required', 'numeric', 'digits_between:5,15'],
                    'address' => ['required', 'between:2,64'],
                    'city' => ['required', 'between:2,64'],
                    'state' => ['required', 'between:2,64'],
                    'country' => ['required', 'size:2'],
                    'postcode' => ['required', 'between:4,16'],
                ],
                'attributes' => [
                    'name' => '组织名称',
                    'registration_number' => '组织注册号',
                    'phone' => '组织电话',
                    'address' => '地址',
                    'city' => '城市',
                    'state' => '省份',
                    'country' => '国家',
                    'postcode' => '邮编',
                ],
            ],
            'domain' => [
                'rules' => [
                    'domain' => ['regex:/^([a-zA-Z0-9]([a-zA-Z0-9-_]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,11}$/'],
                ],
                'attributes' => [
                    'domain' => '域名',
                ],
            ],
        ];
    }

    /**
     * 验证基础字段
     *
     * @param  array  $params  输入参数
     * @return array 错误信息数组
     */
    protected static function validateBasicFields(array $params): array
    {
        if (empty(self::$rules)) {
            self::init();
        }

        $rules = self::$rules['basic']['rules'];
        $attributes = self::$rules['basic']['attributes'];

        // 特殊处理 unique_value 的 order_id 关联
        if (isset($params['order_id'])) {
            // 使用正则表达式只替换最后一个 null
            $rules['unique_value'][2] = preg_replace('/,null$/', ','.$params['order_id'], $rules['unique_value'][2]);
        }

        $validator = Validator::make($params, $rules, [], $attributes);

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    /**
     * 对外暴露的唯一验证方法
     * 根据传入的参数进行对应验证。
     *
     * @throws ValidationException 验证失败抛出异常
     */
    public static function validate(array $params): void
    {
        if (empty(self::$rules)) {
            self::init();
        }

        $errors = [];

        // 1. 基础规则验证
        if ($basicErrors = self::validateBasicFields($params)) {
            $errors['basic'] = $basicErrors;
        }

        // 2. 验证 contact (若存在)
        if (isset($params['contact']) && is_array($params['contact'])) {
            if ($contactErrors = self::validateContact($params['contact'])) {
                $errors['contact'] = $contactErrors;
            }
        }

        // 3. 验证 organization (若存在)
        if (isset($params['organization']) && is_array($params['organization'])) {
            if ($orgErrors = self::validateOrganization($params['organization'])) {
                $errors['organization'] = $orgErrors;
            }
        }

        // 4. 特殊逻辑验证
        //   - domains 验证：调用 validateDomains
        //   - period 验证：调用 validatePeriod
        //   - validation_method 验证：调用 validateValidationMethod
        //   这里的 product 是示例中提及的，根据需要从 $params 中获取
        $product = $params['product'] ?? [];

        if (isset($params['domains'])) {
            $domainsErrors = self::validateDomains($params['domains'], $product, $params);
            // validateDomains 可能返回字符串（单个错误）或数组（多个错误）
            if (is_string($domainsErrors) || (is_array($domainsErrors) && ! empty($domainsErrors))) {
                $errors['domains'] = $domainsErrors;
            }
        }

        if (isset($params['period'])) {
            $periodError = self::validatePeriod($params['period'], $product);
            if ($periodError) {
                $errors['period'] = $periodError;
            }
        }

        if (isset($params['validation_method'])) {
            $validationMethodError = self::validateValidationMethod($params['validation_method'], $product);
            if ($validationMethodError) {
                $errors['validation_method'] = $validationMethodError;
            }
        }

        if (isset($params['encryption']) && is_array($params['encryption'])) {
            $encryptionError = self::validateEncryption($params['encryption'], $product);
            if ($encryptionError) {
                $errors['encryption'] = $encryptionError;
            }
        }

        $errors = self::recursiveFilterEmpty($errors);
        if (! empty($errors)) {
            self::error('参数错误', $errors);
        }
    }

    /**
     * 递归过滤空值
     * 过滤掉空字符串、null、false、0以及空数组
     */
    private static function recursiveFilterEmpty($input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $result = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                // 递归处理数组
                $filteredArray = self::recursiveFilterEmpty($value);
                // 只有当过滤后的数组不为空时才保留
                if (! empty($filteredArray)) {
                    $result[$key] = $filteredArray;
                }
            } else {
                // 处理非数组值，过滤掉各种空值
                if (self::isNotEmpty($value)) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * 判断值是否不为空
     * 更严格的空值检查
     */
    private static function isNotEmpty($value): bool
    {
        // null 值
        if ($value === null) {
            return false;
        }

        // 布尔值 false
        if ($value === false) {
            return false;
        }

        // 数字 0
        if ($value === 0 || $value === '0') {
            return false;
        }

        // 字符串类型的空值检查
        if (is_string($value)) {
            // 去除首尾空格后检查是否为空
            $trimmed = trim($value);

            return $trimmed !== '';
        }

        // 数组在上面已经单独处理了，这里不应该到达
        if (is_array($value)) {
            return ! empty($value);
        }

        // 其他类型认为不为空
        return true;
    }

    /**
     * 验证 contact
     */
    public static function validateContact(array $contact): array
    {
        $config = self::$rules['contact'];
        $validator = Validator::make(
            $contact,
            $config['rules'] ?? [],
            [],
            $config['attributes'] ?? []
        );

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    /**
     * 验证 organization
     */
    public static function validateOrganization(array $organization): array
    {
        $config = self::$rules['organization'];
        $validator = Validator::make(
            $organization,
            $config['rules'] ?? [],
            [],
            $config['attributes'] ?? []
        );

        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    /**
     * 验证域名列表
     * 包括通配符域名、标准域名数量检查
     */
    public static function validateDomains($domains, array $product = [], array $params = []): array|string
    {
        if (! is_string($domains)) {
            return '域名必须是字符串类型';
        }

        if (empty($product)) {
            return '未找到产品';
        }

        // 处理单域名赠送
        if ($product['gift_root_domain'] && $product['total_max'] === 1) {
            $domains = DomainUtil::removeGiftDomain($domains);
        }

        $errors = [];
        $countErrors = self::validateSansMaxCount($product, $domains);

        // 只有当数量验证有错误时才添加
        if (! empty($countErrors)) {
            $errors['count'] = $countErrors;
        }

        $validationMethod = $params['validation_method'] ?? '';
        $commonNameTypes = $product['common_name_types'] ?? [];
        $alternativeNameTypes = $product['alternative_name_types'] ?? [];

        $domains = explode(',', $domains);
        foreach ($domains as $index => $domain) {
            $domain = strtolower($domain);

            $types = $index === 0 ? $commonNameTypes : $alternativeNameTypes;
            $domainError = self::validateDomain($domain, $types);

            // 只有当域名验证有错误时才添加
            if (! empty($domainError)) {
                $errors[$index][] = $domainError;
            }

            if (
                str_starts_with($domain, '*.')
                && in_array($validationMethod, ['http', 'https', 'file'])
            ) {
                $errors[$index][] = $domain.' 通配符域名不能使用 '.$validationMethod.' 方法';
            }

            if (
                filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)
                && ! in_array($validationMethod, ['http', 'https', 'file'])
            ) {
                $errors[$index][] = $domain.' IP 只能使用文件验证方法';
            }
        }

        $repeat = array_diff_assoc($domains, array_unique($domains));
        if (! empty($repeat)) {
            $errors['repeat'] = '域名重复: '.implode(',', $repeat);
        }

        // 过滤掉空的错误信息，避免返回空数组
        return self::recursiveFilterEmpty($errors);
    }

    /**
     * 验证域名数量是否超出产品限制
     */
    public static function validateSansMaxCount(array $product, $domains): array
    {
        if (! is_string($domains)) {
            return ['域名必须是字符串类型'];
        }

        // 验证最大数量时要加上赠送域名 按最大数量计算
        if ($product['gift_root_domain'] && $product['total_max'] > 1) {
            $domains = DomainUtil::addGiftDomain($domains);
        }

        // 单域名赠送处理
        if ($product['gift_root_domain'] && $product['total_max'] === 1) {
            $domains = DomainUtil::removeGiftDomain($domains);
        }

        $sans = OrderUtil::getSansFromDomains($domains);

        $errors = [];

        $standardCount = $sans['standard_count'];
        $wildcardCount = $sans['wildcard_count'];

        $standardError = self::checkDomainCount($standardCount, $product['standard_max'], '标准');
        $wildcardError = self::checkDomainCount($wildcardCount, $product['wildcard_max'], '通配');
        $totalError = self::checkDomainCount($standardCount + $wildcardCount, $product['total_max'], '总');

        // 只有当有错误时才添加到数组中
        if (! empty($standardError)) {
            $errors['standard'] = $standardError;
        }
        if (! empty($wildcardError)) {
            $errors['wildcard'] = $wildcardError;
        }
        if (! empty($totalError)) {
            $errors['total'] = $totalError;
        }

        return $errors;
    }

    /**
     * 辅助方法：检查域名数量上限
     */
    private static function checkDomainCount(int $count, int $max, string $type = ''): string
    {
        if ($count > $max) {
            return $type."域名数量不能超过 $max";
        }

        return '';
    }

    /**
     * 验证单个域名类型是否合法
     */
    public static function validateDomain(string $domain, array $types): string
    {
        $type = DomainUtil::getType($domain);

        if (! in_array($type, $types)) {
            return '域名 '.$domain.' 类型错误: '.$type.' 不允许';
        }

        return '';
    }

    /**
     * 验证有效期
     */
    public static function validatePeriod($period, array $product = []): string
    {
        if (! is_int($period) && ! is_string($period)) {
            return '有效期必须是整数或字符串';
        }

        if (empty($product)) {
            return '未找到产品';
        }

        $periods = $product['periods'] ?? [];
        $periods = is_array($periods) ? $periods : explode(',', (string) $periods);

        if ($period && ! in_array($period, $periods)) {
            return '有效期只能使用 '.implode(',', $periods);
        }

        return '';
    }

    /**
     * 验证验证方法
     */
    public static function validateValidationMethod($validationMethod, array $product = []): string
    {
        if (! is_string($validationMethod)) {
            return '验证方法必须是字符串';
        }

        if (empty($product)) {
            return '未找到产品';
        }

        $validationMethods = $product['validation_methods'] ?? [];
        $validationMethods = is_array($validationMethods)
            ? $validationMethods
            : explode(',', (string) $validationMethods);

        if ($validationMethod && ! in_array($validationMethod, $validationMethods)) {
            return '验证方法只能使用 '.implode(',', $validationMethods);
        }

        return '';
    }

    /**
     * 验证加密配置
     */
    public static function validateEncryption($encryption, array $product = []): string|array
    {
        if (empty($product)) {
            return '未找到产品';
        }

        $errors = [];

        // 验证加密算法
        if (isset($encryption['alg'])) {
            if (! in_array(strtolower($encryption['alg']), $product['encryption_alg'])) {
                $errors[] = '加密算法只能使用 '.strtoupper(implode(',', $product['encryption_alg']));
            }
        }

        // 验证摘要算法
        if (isset($encryption['digest_alg'])) {
            if (! in_array(strtolower($encryption['digest_alg']), $product['signature_digest_alg'])) {
                $errors[] = '摘要算法只能使用 '.strtoupper(implode(',', $product['signature_digest_alg']));
            }
        }

        return $errors;
    }
}
