<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL 证书已签发</title>
    <style>
        /* 基础重置 */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4f6f8; }

        /* 移动端与暗黑模式适配 */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: auto !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            /* 移动端上下间距稍微减小一点，避免太空 */
            .wrapper-padding { padding-top: 30px !important; padding-bottom: 30px !important; }
        }
        @media (prefers-color-scheme: dark) {
            body, .outer-wrapper { background-color: #2d2d2d !important; }
            .content-cell { background-color: #1a1a1a !important; color: #e1e1e1 !important; }
            .card-info { background-color: #252525 !important; border: 1px solid #333333 !important; }
            h1, h2, h3, span, div { color: #e1e1e1 !important; }
            .footer-text { color: #888888 !important; }
            .highlight-text { color: #ffffff !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">

    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        您申请的 <?php echo e($domain); ?> 证书已成功签发，请查收附件。
    </div>

    <center style="width: 100%; background-color: #f4f6f8;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="outer-wrapper" style="background-color: #f4f6f8;">
            <tr>
                <td align="center" class="wrapper-padding" style="padding-top: 50px; padding-bottom: 50px; padding-left: 10px; padding-right: 10px;">

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">

                        <tr>
                            <td style="background-color: #10b981; height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>

                        <tr>
                            <td class="content-cell mobile-padding" style="padding: 40px 40px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                                <h1 style="margin: 0 0 20px 0; font-size: 22px; line-height: 30px; color: #333333; font-weight: 700;">
                                    ✅ SSL 证书已成功签发
                                </h1>

                                <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 26px; color: #555555;">
                                    尊敬的 <span class="highlight-text" style="color: #10b981; font-weight: 600;"><?php echo e($username); ?></span>，您好：
                                </p>

                                <p style="margin: 0 0 24px 0; font-size: 16px; line-height: 26px; color: #555555;">
                                    您在 <a href="<?php echo e($site_url); ?>" style="color: #10b981; text-decoration: none; font-weight: 600;"><?php echo e($site_name); ?></a> 申请的 SSL 证书审核通过，现已正式签发。
                                </p>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 24px;">
                                    <tr>
                                        <td class="card-info" style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="padding-bottom: 8px; font-size: 14px; color: #888888; font-family: sans-serif;">证书域名</td>
                                                </tr>
                                                <tr>
                                                    <td class="highlight-text" style="padding-bottom: 16px; font-size: 18px; font-weight: 600; color: #333333; font-family: monospace;"><?php echo e($domain); ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="padding-bottom: 8px; font-size: 14px; color: #888888; font-family: sans-serif;">产品名称</td>
                                                </tr>
                                                <tr>
                                                    <td class="highlight-text" style="font-size: 16px; color: #333333; font-family: sans-serif;"><?php echo e($product); ?></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <div style="background-color: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; border-radius: 0 4px 4px 0; margin-bottom: 24px;">
                                    <p style="margin: 0; font-size: 15px; line-height: 24px; color: #065f46;">
                                        <strong>📎 附件提醒：</strong><br>
                                        证书文件已打包为 ZIP 附件，请下载后解压并安装。
                                    </p>
                                </div>

                                <p style="margin: 0; font-size: 15px; line-height: 24px; color: #666666;">
                                    如果您在安装过程中遇到任何问题，或附件无法下载，请随时登录控制台或联系我们的技术支持。
                                </p>

                            </td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="background-color: #fafafa; padding: 20px 40px; text-align: center; border-top: 1px solid #eeeeee;">
                                <p class="footer-text" style="margin: 0; font-size: 13px; line-height: 20px; color: #999999; font-family: sans-serif;">
                                    感谢您选择 <a href="<?php echo e($site_url); ?>" style="color: #999999; text-decoration: underline;"><?php echo e($site_name); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    </td>
            </tr>
        </table>
    </center>
</body>
</html><?php /**PATH /Users/zhuxbo/work/code/cnssl/apps/backend/storage/framework/views/bcb3cc61166f92965e7c64213708f7cf.blade.php ENDPATH**/ ?>