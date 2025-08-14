<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\Cors\CorsService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class DynamicCors extends HandleCors
{
    public function __construct(Container $container, CorsService $cors)
    {
        parent::__construct($container, $cors);
    }

    /**
     * 处理传入的请求。
     *
     * @param  Request  $request
     */
    public function handle($request, Closure $next)
    {
        // 验证是否需要执行 CORS
        if (! $this->hasMatchingPath($request)) {
            return $next($request);
        }

        // 获取允许的域名列表并处理动态 CORS 头
        $allowedOrigins = Config::get('cors.allowed_origins', '');
        $origin = $request->header('Origin');

        if ($origin && $this->isAllowedOrigin($origin, $allowedOrigins)) {
            // 处理预检请求
            if ($request->getMethod() === 'OPTIONS') {
                $response = new JsonResponse(null, 204);

                return $this->applyCorsHeaders($response, $origin);
            }

            return $this->applyCorsHeaders($next($request), $origin);
        }

        return $next($request);
    }

    /**
     * 检查请求的 Origin 是否在允许的域名列表中
     */
    private function isAllowedOrigin(string $origin, string $allowedOrigins): bool
    {
        $allowedOrigins = array_map('trim', explode(',', $allowedOrigins));

        // 提取请求的域名部分
        $requestHost = parse_url($origin, PHP_URL_HOST);
        if (! $requestHost) {
            return false;
        }

        foreach ($allowedOrigins as $allowedOrigin) {
            // 转义所有点号
            $pattern = str_replace('.', '\.', $allowedOrigin);

            // 处理通配符，只对以 *. 开头的进行特殊处理
            if (str_starts_with($allowedOrigin, '*.')) {
                $baseDomain = substr($allowedOrigin, 2); // 去掉 *.
                $basePattern = str_replace('.', '\.', $baseDomain); // 转义点号
                $pattern = '/^([a-zA-Z0-9-]+\.)*'.$basePattern.'$/i'; // 支持任意级子域名
            } else {
                // 直接匹配
                $pattern = '/^'.$pattern.'$/i';
            }

            // 域名匹配
            if (preg_match($pattern, $requestHost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 应用 CORS 头到响应
     */
    private function applyCorsHeaders($response, string $origin)
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', Config::get('cors.allowed_methods')));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', Config::get('cors.allowed_headers')));
        $response->headers->set('Access-Control-Allow-Credentials', Config::get('cors.supports_credentials'));

        // 添加暴露给前端的头部
        $exposedHeaders = Config::get('cors.exposed_headers');
        if ($exposedHeaders) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
        }

        // 添加缓存控制头
        if ($response->headers->has('Access-Control-Allow-Methods')) {
            $response->headers->set('Access-Control-Max-Age', Config::get('cors.max_age', 7200));
        }

        return $response;
    }
}
