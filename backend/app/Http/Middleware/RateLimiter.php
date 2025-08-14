<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Traits\ApiResponse;
use App\Traits\ExtractsToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    use ApiResponse;
    use ExtractsToken;

    /**
     * 限流中间件 - 在认证之前执行
     */
    public function handle(Request $request, Closure $next, string $limiter = 'v2')
    {
        // 1. 优先检查 token 级别的限流
        $hasValidToken = $this->checkTokenRateLimit($request, $limiter);

        // 2. 如果没有有效的 token，则使用 IP 限流
        if (! $hasValidToken) {
            $this->checkIpRateLimit($request, $limiter);
        }

        return $next($request);
    }

    /**
     * 基于 IP 的基础限流
     */
    private function checkIpRateLimit(Request $request, string $limiter): void
    {
        $ip = $request->ip();
        $key = sprintf('rate_limit_ip:%s:%s', $limiter, $ip);

        // IP 限流相对宽松，主要防止暴力攻击
        $limit = match ($limiter) {
            'v1' => 200,
            'v2' => 200,
            default => 100,
        };

        $this->checkLimit($key, $limit, 'IP rate limit exceeded');
    }

    /**
     * 基于 Token 的精确限流
     *
     * @return bool 是否找到有效的 token
     */
    private function checkTokenRateLimit(Request $request, string $limiter): bool
    {
        // 尝试从请求属性中获取预解析的 token 信息
        /** @var ApiToken|null $apiToken */
        $apiToken = $request->attributes->get('api_token_info');

        if (! $apiToken) {
            // 如果没有预解析的信息，尝试直接提取和查找
            $token = $this->extractToken($request);
            if ($token) {
                $apiToken = ApiToken::where('token', hash('sha256', $token))->first();
            }
        }

        if (! $apiToken) {
            // 没有有效的 token，返回 false
            return false;
        }

        // 使用 token 配置的限流值
        $limit = $apiToken->getEffectiveRateLimit($this->getDefaultTokenLimit($limiter));
        $identifier = 'token_'.$apiToken->id;

        $key = sprintf('rate_limit_token:%s:%s', $limiter, $identifier);
        $this->checkLimit($key, $limit, 'Token rate limit exceeded');

        // 返回 true 表示找到了有效的 token
        return true;
    }

    /**
     * 执行限流检查
     */
    private function checkLimit(string $key, int $limit, string $errorMessage): void
    {
        // @phpstan-ignore arguments.count
        $current = Redis::client()->incr($key);
        if ($current === 1) {
            // @phpstan-ignore arguments.count
            Redis::client()->expire($key, 60);
        }

        if ($current > $limit) {
            $this->error($errorMessage);
        }
    }

    /**
     * 获取默认 Token 限流数量
     */
    private function getDefaultTokenLimit(string $limiter): int
    {
        return match ($limiter) {
            'v1' => 60,
            'v2' => 60,
            default => 30,
        };
    }
}
