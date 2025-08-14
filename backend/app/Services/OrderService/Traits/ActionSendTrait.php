<?php

declare(strict_types=1);

namespace App\Services\OrderService\Traits;

use App\Bootstrap\ApiExceptions;
use App\Models\Order;
use App\Models\User;
use App\Utils\Email;
use DateMalformedStringException;
use DateTime;
use Throwable;
use ZipArchive;

trait ActionSendTrait
{
    /**
     * 发送证书邮件
     */
    public function sendActive(int $orderId, string $email = ''): void
    {
        $order = Order::with(['user', 'product', 'latestCert'])
            ->whereHas('user')
            ->whereHas('product')
            ->whereHas('latestCert', fn ($query) => $query->where('status', 'active'))
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            $this->error('订单不存在或未签发');
        }

        $email = $email ?: $order->user->email;
        if (! $email) {
            $this->error('邮箱为空');
        }

        $tempDir = storage_path('app/'.sprintf('%04x%04x', mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)));
        mkdir($tempDir, 0755, true);
        chdir($tempDir);

        $zip = new ZipArchive;
        $filename = str_replace('*', 'STAR', $order->latestCert->common_name).'.zip';
        $zip->open($filename, ZipArchive::CREATE);
        $this->addCertToZip($order, $zip, $tempDir);
        $zip->close();

        $template = get_system_setting('mail', 'issueNotice', 'SSL证书已签发通知');
        $siteUrl = get_system_setting('site', 'url', '/');
        $siteName = get_system_setting('site', 'name', 'SSL证书管理系统');

        $subject = $order->latestCert->common_name.' 域名SSL证书已签发 ['.$siteName.']';
        $pem = ($order->latestCert->cert ?? '')."\n".($order->latestCert->intermediate_cert ?? '');

        $body = str_replace('{#site_url}', $siteUrl, $template);
        $body = str_replace('{#site_name}', $siteName, $body);
        $body = str_replace('{#product}', $order->product->name, $body);
        $body = str_replace('{#pem}', $pem, $body);
        $body = str_replace('{#cert}', $order->latestCert->cert ?? '', $body);
        $body = str_replace('{#key}', $order->latestCert->private_key ?? '', $body);
        $body = str_replace('{#ca}', $order->latestCert->intermediate_cert ?? '', $body);
        $body = str_replace('{#domain}', $order->latestCert->common_name, $body);

        try {
            $mail = new Email;
            $mail->isSMTP();
            $mail->isHTML();
            $mail->addAddress($email, $order->user->username);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->addAttachment($filename);
            $result = $mail->send();
            @exec('rm -rf '.$tempDir);
        } catch (Throwable $e) {
            @exec('rm -rf '.$tempDir);
            app(ApiExceptions::class)->logException($e);
            $this->error('发送证书邮件失败');
        }

        if (! ($result ?? false)) {
            $this->error('发送证书邮件失败', ['result' => $result, 'error' => $mail->ErrorInfo ?? '']);
        }

        $this->success();
    }

    /**
     * 发送证书到期提醒
     */
    public function sendExpire(int $userId, string $email = ''): void
    {
        $user = User::where('id', $userId)->first();
        if (! $user) {
            $this->error('用户不存在');
        }

        $email = $email ?: $user->email;
        if (! $email) {
            $this->error('邮箱为空');
        }

        $template = get_system_setting('mail', 'expireNotice', 'SSL证书到期提醒');
        $siteUrl = get_system_setting('site', 'url', '/');
        $siteName = get_system_setting('site', 'name', 'SSL证书管理系统');

        $body = str_replace('{#username}', $user->username, $template);

        $orders = Order::with(['product', 'latestCert'])
            ->whereHas('product')
            ->whereHas('latestCert',
                fn ($query) => $query->where('status', 'active')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->orderBy('expires_at')
            )
            ->where('user_id', $user->id)
            ->get();

        $list = '';
        foreach ($orders as $key => $order) {
            try {
                $diffExpire = (new DateTime)->diff(new DateTime((string) $order->latestCert->expires_at))->format('%a');
            } catch (DateMalformedStringException $e) {
                app(ApiExceptions::class)->logException($e);
                $diffExpire = '未知';
            }

            $list .= '<tr style="text-align: center">'
                .'<td style="border: 1px solid #ccc; word-break: break-all; padding: 10px; width: 60px">'
                .($key + 1)
                .'</td>'
                .'<td style="border: 1px solid #ccc; word-break: break-all; padding: 10px">'
                .$order->latestCert->common_name
                .'</td>'
                .'<td style="border: 1px solid #ccc; word-break: break-all; padding: 10px; width: 200px">'
                .$order->latestCert->expires_at
                .'</td>'
                .'<td style="border: 1px solid #ccc; word-break: break-all; padding: 10px; width: 80px">'
                .$diffExpire
                .'</td></tr>';
        }
        if (! $list) {
            $this->error('30天内没有到期的证书');
        }

        $body = str_replace('{#list}', $list, $body);
        $body = str_replace('{#site_name}', $siteName, $body);
        $body = str_replace('{#site_url}', $siteUrl, $body);

        $subject = 'SSL证书到期提醒 ['.$siteName.']';

        try {
            $mail = new Email;
            $mail->isSMTP();
            $mail->isHTML();
            $mail->addAddress($email, $user->username);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Throwable $e) {
            app(ApiExceptions::class)->logException($e);
            $this->error('发送证书到期提醒邮件失败');
        }

        $this->success();
    }
}
