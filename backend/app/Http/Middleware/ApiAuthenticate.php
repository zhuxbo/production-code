<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Callback;
use App\Models\Contact;
use App\Models\Fund;
use App\Models\Invoice;
use App\Models\InvoiceLimit;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Scopes\UserScope;
use App\Models\Transaction;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Auth\TokenGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiAuthenticate
{
    use ApiResponse;

    /**
     * API 认证中间件
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var TokenGuard $guard */
        $guard = Auth::guard('api');

        if (! $guard->check()) {
            $this->error('Unauthorized');
        }

        /** @var ApiToken $apiToken */
        $apiToken = $guard->user();

        if (! $apiToken->status) {
            $this->error('Api token is disabled');
        }

        if (! $apiToken->isIpAllowed($request->ip())) {
            $this->error('IP is not allowed');
        }

        $apiToken->update([
            'last_used_ip' => $request->ip(),
            'last_used_at' => now(),
        ]);

        if ($apiToken->user_id) {
            UserScope::addScopeToModels($apiToken->user_id, [
                ApiToken::class,
                Callback::class,
                Order::class,
                Fund::class,
                Transaction::class,
                Invoice::class,
                InvoiceLimit::class,
                Organization::class,
                Contact::class,
            ]);
        }

        $request->attributes->set('authenticated_api_token', $apiToken);

        return $next($request);
    }
}
