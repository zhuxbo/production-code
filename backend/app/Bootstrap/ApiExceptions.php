<?php

namespace App\Bootstrap;

use App\Exceptions\ApiResponseException;
use App\Models\ErrorLog;
use App\Traits\LogSanitizer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptions
{
    use LogSanitizer;

    /**
     * 不需要记录日志的异常类型
     */
    protected array $dontLogExceptions = [
        AuthenticationException::class,
        ThrottleRequestsException::class,
        NotFoundHttpException::class,
        ValidationException::class,
        ApiResponseException::class,
        MethodNotAllowedHttpException::class,
    ];

    /**
     * 处理异常
     */
    public function handle(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e) {
            return $this->handleApiException($e);
        });

        $exceptions->reportable(function (Throwable $e) {
            $this->logException($e);
        });
    }

    /**
     * 将异常记录到日志
     */
    public function logException(Throwable $e): void
    {
        if (! $this->shouldNotLog($e)) {
            $request = Request::instance();

            // 尝试正常记录错误日志
            try {
                // 截断过长的错误信息，防止数据库字段溢出
                $message = $e->getMessage();
                if (strlen($message) > 1000) {
                    $message = substr($message, 0, 997).'...';
                }

                ErrorLog::create([
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'exception' => class_basename($e),
                    'message' => $message,
                    'trace' => $this->sanitizeResponse($e->getTrace()),
                    'status_code' => $this->getExceptionStatusCode($e),
                    'ip' => $request->ip(),
                ]);
            } catch (Throwable $logException) {
                // 如果记录日志失败，创建一个简化的错误记录
                // 将完整的异常信息存储到 trace 字段中
                try {
                    $trace = [
                        'original_exception' => [
                            'class' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTrace(),
                        ],
                        'log_exception' => [
                            'class' => get_class($logException),
                            'message' => $logException->getMessage(),
                            'file' => $logException->getFile(),
                            'line' => $logException->getLine(),
                        ],
                    ];

                    ErrorLog::create([
                        'method' => $request->method(),
                        'url' => substr($request->fullUrl(), 0, 500), // 确保不超长
                        'exception' => 'LogException',
                        'message' => '日志记录失败，请查看详情',
                        'trace' => $trace,
                        'status_code' => $this->getExceptionStatusCode($e),
                        'ip' => $request->ip(),
                    ]);
                } catch (Throwable $finalException) {
                    // 如果连这个也失败了，至少记录到 Laravel 日志文件
                    Log::emergency('ErrorLog 记录完全失败', [
                        'original_exception' => $e->getMessage(),
                        'log_exception' => $logException->getMessage(),
                        'final_exception' => $finalException->getMessage(),
                        'url' => $request->fullUrl(),
                    ]);
                }
            }
        }
    }

    /**
     * 判断是否不记录日志
     */
    protected function shouldNotLog(Throwable $e): bool
    {
        foreach ($this->dontLogExceptions as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取异常状态码
     */
    protected function getExceptionStatusCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof AuthenticationException => 401,
            $e instanceof NotFoundHttpException => 404,
            $e instanceof MethodNotAllowedHttpException => 405,
            $e instanceof ThrottleRequestsException => 429,
            // 验证异常 状态码 200
            $e instanceof ValidationException => 200,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 400,
        };
    }

    /**
     * 处理 API 异常
     * 美化错误响应格式
     * 统一错误信息展示规则
     * 判断是否调试模式
     */
    protected function handleApiException(Throwable $e): JsonResponse
    {
        if ($e instanceof ApiResponseException) {
            return new JsonResponse($e->getApiResponse());
        }

        $status = $this->getExceptionStatusCode($e);
        $debug = config('app.debug', false);

        if ($e instanceof ValidationException) {
            $response = [
                'code' => 0,
                'msg' => '提交数据验证失败',
                'errors' => $e->errors(),
            ];

            return new JsonResponse($response, $status);
        }

        $message = match (true) {
            $e instanceof AuthenticationException => $e->getMessage() ?: '未登录或登录已过期',
            $e instanceof NotFoundHttpException => $e->getMessage() ?: '请求的资源不存在',
            $e instanceof MethodNotAllowedHttpException => $e->getMessage() ?: '请求方法不允许',
            $e instanceof ThrottleRequestsException => $e->getMessage() ?: '请求过于频繁，请稍后再试',
            $e instanceof HttpException => $e->getMessage() ?: '服务器错误',
            default => $debug ? $e->getMessage() : '服务器错误',
        };

        $response = [
            'code' => 0,
            'msg' => $message,
        ];

        if ($debug && ! ($e instanceof HttpException)) {
            $response['errors']['exception_type'] = get_class($e);
            $response['errors']['exception_trace'] = $e->getTrace();
        }

        return new JsonResponse($response, $status);
    }
}
