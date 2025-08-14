<?php

namespace App\Http\Middleware;

use App\Models\AdminLog;
use App\Models\ApiLog;
use App\Models\CallbackLog;
use App\Models\EasyLog;
use App\Models\UserLog;
use App\Traits\LogSanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class LogOperation
{
    use LogSanitizer;

    /**
     * 不需要记录日志的路由
     */
    protected array $excludedPaths = [
        '*/index*',
        '*/list/*',
        '*/get/*',
        'api/admin/logs/*',
        'api/V1/*/health',
        'api/v2/*/health',
        '_debugger/*',
        '_ignition/*',
    ];

    /**
     * 不需要记录响应内容的路由
     */
    protected array $excludeResponsePaths = [
        '*download*',
        '*export*',
    ];

    /**
     * 处理请求
     *
     * @throws Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        // 检查是否需要记录日志
        if ($this->shouldSkipLogging($request)) {
            return $next($request);
        }

        // 记录开始时间
        $startTime = microtime(true);

        try {
            // 继续处理请求
            $response = $next($request);

            // 计算耗时
            $duration = microtime(true) - $startTime;

            // 获取响应内容
            if (! $this->shouldSkipResponse($request)) {
                $responseContent = $response->getContent();
                $sanitizedResponse = $this->sanitizeResponse($responseContent);
                $content = json_decode($responseContent, true);
            }

            $status = 0;
            // 主要用于支付宝回调 放前面 先检查
            if (isset($responseContent)) {
                $status = intval(strtolower($responseContent) === 'success');
            }
            // 主要用于微信支付回调
            if (isset($content['code'])) {
                if (is_string($content['code'])) {
                    $status = intval(strtolower($content['code']) === 'success');
                }
                if (is_int($content['code'])) {
                    $status = intval(boolval($content['code']));
                }
            }

            // 基础日志数据
            $logData = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'params' => $this->sanitizeParams($request->all()),
                'response' => $sanitizedResponse ?? null,
                'status_code' => $response->getStatusCode(),
                'status' => $status,
                'duration' => round($duration, 3),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            // 根据路由前缀记录不同类型的日志
            if ($request->is(['api/V1/*', 'api/v2/*'])) {
                $this->logApiRequest($request, $logData);
            } elseif ($request->is('api/admin/*')) {
                $this->logAdminRequest($request, $logData);
            } elseif ($request->is('api/easy/*')) {
                $this->logEasyRequest($logData);
            } elseif ($request->is('callback/*')) {
                $this->logCallbackRequest($logData);
            } else {
                $this->logUserRequest($request, $logData);
            }
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }

        return $response;
    }

    /**
     * 判断是否需要跳过记录日志
     */
    protected function shouldSkipLogging(Request $request): bool
    {
        foreach ($this->excludedPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断是否需要跳过记录响应内容
     */
    protected function shouldSkipResponse(Request $request): bool
    {
        foreach ($this->excludeResponsePaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 记录 API 请求日志
     */
    protected function logApiRequest(Request $request, array $logData): void
    {
        ApiLog::create(array_merge($logData, [
            'user_id' => Auth::guard('api')->user()?->user_id,
            'version' => $this->getApiVersion($request),
        ]));
    }

    /**
     * 记录管理员请求日志
     */
    protected function logAdminRequest(Request $request, array $logData): void
    {
        AdminLog::create(array_merge($logData, [
            'admin_id' => Auth::guard('admin')->id(),
            'module' => $this->getModule($request),
            'action' => $this->getAction($request),
        ]));
    }

    /**
     * 记录用户请求日志
     */
    protected function logUserRequest(Request $request, array $logData): void
    {
        UserLog::create(array_merge($logData, [
            'user_id' => Auth::guard('user')->id(),
            'module' => $this->getModule($request),
            'action' => $this->getAction($request),
        ]));
    }

    /**
     * 记录回调日志
     */
    protected function logCallbackRequest(array $logData): void
    {
        CallbackLog::create($logData);
    }

    /**
     * 记录Easy日志
     */
    protected function logEasyRequest(array $logData): void
    {
        EasyLog::create($logData);
    }

    /**
     * 获取 API 版本
     */
    protected function getApiVersion(Request $request): string
    {
        // 根据路由判断API版本
        if ($request->is('api/V1/*')) {
            return 'v1';
        } elseif ($request->is('api/v2/*')) {
            return 'v2';
        }

        // 如果路由不匹配，则从请求头获取版本（兼容旧的实现）
        return $request->header('Accept-Version', 'v2');
    }

    /**
     * 获取模块名称
     */
    protected function getModule(Request $request): string
    {
        $routeAction = $request->route()?->getAction();
        if (isset($routeAction['controller'])) {
            $controller = class_basename($routeAction['controller']);
            $controller = str_replace('Controller', '', $controller);

            $parts = explode('@', $controller);

            return $parts[0] ?? 'Unknown';
        }

        return 'Unknown';
    }

    /**
     * 获取操作名称
     */
    protected function getAction(Request $request): string
    {
        $routeAction = $request->route()?->getAction();
        if (isset($routeAction['controller'])) {
            $parts = explode('@', $routeAction['controller']);

            return $parts[1] ?? 'Unknown';
        }

        return 'Unknown';
    }
}
