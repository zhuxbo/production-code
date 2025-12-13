<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL 证书到期提醒</title>
    <style>
        /* 基础重置 */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4f6f8; }

        /* 移动端适配 */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: auto !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .wrapper-padding { padding-top: 30px !important; padding-bottom: 30px !important; }
            /* 强制表格在手机端滚动或调整字号 */
            .data-table th, .data-table td { font-size: 12px !important; padding: 10px 5px !important; }
        }

        /* 暗黑模式适配 */
        @media (prefers-color-scheme: dark) {
            body, .outer-wrapper { background-color: #2d2d2d !important; }
            .white-card { background-color: #1f1f1f !important; border: 1px solid #333333 !important; }
            h1, h2, h3, p, span, div { color: #e1e1e1 !important; }
            .footer-text { color: #888888 !important; }
            .highlight-text { color: #f59e0b !important; }
            /* 表格暗黑模式 */
            .data-table th { background-color: #333333 !important; color: #cccccc !important; border-bottom: 1px solid #444 !important; }
            .data-table td { border-bottom: 1px solid #333 !important; color: #e1e1e1 !important; }
            .warning-box { background-color: #332b00 !important; border-left-color: #f59e0b !important; }
            .warning-text { color: #fbbf24 !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">

    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        您的 SSL 证书即将过期，请尽快处理续费。
    </div>

    <center style="width: 100%; background-color: #f4f6f8;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="outer-wrapper" style="background-color: #f4f6f8;">
            <tr>
                <td align="center" class="wrapper-padding" style="padding-top: 50px; padding-bottom: 50px; padding-left: 10px; padding-right: 10px;">

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="white-card" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">

                        <tr>
                            <td style="background-color: #f59e0b; height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="padding: 40px 40px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                                <h1 style="margin: 0 0 20px 0; font-size: 22px; line-height: 30px; color: #333333; font-weight: 700;">
                                    ⚠️ SSL 证书到期提醒
                                </h1>

                                <p style="margin: 0 0 15px 0; font-size: 16px; line-height: 26px; color: #555555;">
                                    尊敬的 <span class="highlight-text" style="color: #f59e0b; font-weight: 600;">{{ $username }}</span>，您好：
                                </p>

                                <p style="margin: 0 0 25px 0; font-size: 15px; line-height: 26px; color: #555555;">
                                    为了不影响网站的正常访问和数据安全，请注意下列证书即将到期，建议您尽快完成续费。
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="data-table" style="margin-bottom: 24px; border-collapse: collapse; width: 100%;">
                                    <thead>
                                        <tr style="background-color: #fffbeb;">
                                            <th align="left" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">序号</th>
                                            <th align="left" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">域名</th>
                                            <th align="left" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">到期时间</th>
                                            <th align="center" style="padding: 12px 10px; border-bottom: 2px solid #fcd34d; font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">剩余</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- 数据库模板中的 Blade 循环 --}}
                                        @foreach($certificates as $index => $cert)
                                        <tr>
                                            <td align="left" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #666666;">
                                                {{ $index + 1 }}
                                            </td>
                                            <td align="left" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px; font-weight: 600; color: #333333; font-family: monospace;">
                                                {{ $cert['domain'] }}
                                            </td>
                                            <td align="left" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px; color: #666666;">
                                                {{ $cert['expire_at'] }}
                                            </td>
                                            <td align="center" style="padding: 12px 10px; border-bottom: 1px solid #eeeeee; font-size: 14px;">
                                                @if($cert['days_left'] <= 7)
                                                    <span style="background-color: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 12px;">{{ $cert['days_left'] }}天</span>
                                                @else
                                                    <span style="background-color: #fffbeb; color: #d97706; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 12px;">{{ $cert['days_left'] }}天</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                        {{-- 循环结束 --}}
                                    </tbody>
                                </table>

                                <div class="warning-box" style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 0 4px 4px 0; margin-bottom: 30px;">
                                    <p class="warning-text" style="margin: 0; font-size: 14px; line-height: 22px; color: #92400e;">
                                        <strong>重要提示：</strong><br>
                                        证书过期后，浏览器将拦截访问并显示“不安全”警告，严重影响用户信任。
                                    </p>
                                </div>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $site_url }}" style="background-color:#f59e0b; border-radius:4px; color:#ffffff; display:inline-block; font-family:sans-serif; font-size:16px; font-weight:bold; line-height:44px; text-align:center; text-decoration:none; width:200px; -webkit-text-size-adjust:none;">
                                                立即续费
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="background-color: #fafafa; padding: 20px 40px; text-align: center; border-top: 1px solid #eeeeee;">
                                <p class="footer-text" style="margin: 0; font-size: 13px; line-height: 20px; color: #999999; font-family: sans-serif;">
                                    感谢您选择 <a href="{{ $site_url }}" style="color: #999999; text-decoration: underline;">{{ $site_name }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    </td>
            </tr>
        </table>
    </center>
</body>
</html>