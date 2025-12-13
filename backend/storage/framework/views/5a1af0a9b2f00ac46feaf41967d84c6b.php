<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>队列任务执行失败</title>
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
            /* 手机端表格变为块级显示，标签和值换行 */
            .data-row td { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; border: none !important; }
            .data-label { padding-bottom: 4px !important; font-size: 12px !important; color: #999 !important; }
            .data-value { padding-bottom: 16px !important; border-bottom: 1px solid #eee !important; }
        }

        /* 暗黑模式适配 */
        @media (prefers-color-scheme: dark) {
            body, .outer-wrapper { background-color: #2d2d2d !important; }
            .white-card { background-color: #1f1f1f !important; border: 1px solid #333333 !important; }
            h1, h2, h3, p, span, div { color: #e1e1e1 !important; }
            .data-label { color: #888888 !important; }
            .data-value { color: #e1e1e1 !important; border-bottom-color: #333 !important; }
            .code-block { background-color: #111 !important; border: 1px solid #333 !important; color: #a5b4fc !important; }
            .error-text { color: #f87171 !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;">

    <div style="display: none; font-size: 1px; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
        任务执行失败：<?php echo e($task_action); ?> (订单ID: <?php echo e($order_id); ?>) - <?php echo e($error_message); ?>

    </div>

    <center style="width: 100%; background-color: #f4f6f8;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="outer-wrapper" style="background-color: #f4f6f8;">
            <tr>
                <td align="center" class="wrapper-padding" style="padding-top: 50px; padding-bottom: 50px; padding-left: 10px; padding-right: 10px;">

                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="white-card" style="max-width: 680px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: left;">

                        <tr>
                            <td style="background-color: #dc2626; height: 4px; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>

                        <tr>
                            <td class="mobile-padding" style="padding: 40px 40px 30px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

                                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td width="40" style="padding-right: 15px; vertical-align: middle;">
                                            <img src="https://img.icons8.com/fluency/48/cancel.png" width="32" height="32" alt="Error" style="display: block; border: 0;">
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <h1 style="margin: 0; font-size: 20px; line-height: 30px; color: #dc2626; font-weight: 700;">
                                                队列任务执行失败
                                            </h1>
                                        </td>
                                    </tr>
                                </table>

                                <div style="margin-top: 20px; margin-bottom: 25px; height: 1px; background-color: #eeeeee; font-size: 0; line-height: 0;">&nbsp;</div>

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse: collapse;">

                                    <tr class="data-row">
                                        <td class="data-label" width="30%" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">订单 ID</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; font-family: monospace; font-weight: 600; border-bottom: 1px solid #f0f0f0;">
                                            <?php echo e($order_id); ?>

                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">任务记录 ID</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; font-family: monospace; border-bottom: 1px solid #f0f0f0;">
                                            <?php echo e($task_id); ?>

                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">动作 (Action)</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; border-bottom: 1px solid #f0f0f0;">
                                            <?php echo e($task_action); ?>

                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">执行状态</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; border-bottom: 1px solid #f0f0f0;">
                                            <span style="background-color: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                                <?php echo e($task_status); ?>

                                            </span>
                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">执行次数</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 14px; color: #333333; border-bottom: 1px solid #f0f0f0;">
                                            <?php echo e($attempts); ?>

                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">错误信息</td>
                                        <td class="data-value error-text" style="padding: 10px 0; font-size: 14px; color: #dc2626; font-weight: 600; border-bottom: 1px solid #f0f0f0;">
                                            <?php echo e($error_message); ?>

                                        </td>
                                    </tr>

                                    <tr class="data-row">
                                        <td class="data-label" style="padding: 10px 0; font-size: 13px; color: #888888; vertical-align: top; border-bottom: 1px solid #f0f0f0;">时间</td>
                                        <td class="data-value" style="padding: 10px 0; font-size: 13px; color: #555555; border-bottom: 1px solid #f0f0f0;">
                                            创建于: <?php echo e($created_at); ?><br>
                                            执行于: <?php echo e($executed_at); ?>

                                        </td>
                                    </tr>
                                </table>

                                <div style="margin-top: 30px; margin-bottom: 10px;">
                                    <span style="font-size: 14px; font-weight: bold; color: #333333; text-transform: uppercase; letter-spacing: 0.5px;">运行结果详情</span>
                                </div>

                                <div class="code-block" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; font-size: 13px; line-height: 1.6; color: #333;">
                                    <div style="font-weight: bold; margin-bottom: 5px; color: #555;">Params:</div>
                                    <pre style="margin: 0; white-space: pre-wrap; word-break: break-all; font-family: 'Menlo', 'Consolas', monospace; font-size: 12px; color: #4b5563;"><?php echo e($params); ?></pre>
                                </div>

                                <div class="code-block" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; font-size: 13px; line-height: 1.6; color: #333;">
                                    <div style="font-weight: bold; margin-bottom: 5px; color: #555;">Result:</div>
                                    <pre style="margin: 0; white-space: pre-wrap; word-break: break-all; font-family: 'Menlo', 'Consolas', monospace; font-size: 12px; color: #4b5563;"><?php echo e($result); ?></pre>
                                </div>

                                <p style="margin-top: 30px; font-size: 12px; color: #999999; text-align: center;">
                                    此邮件为系统自动发送，请勿回复。<br>
                                    Message ID: <?php echo e($task_id); ?>

                                </p>

                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>
    </center>
</body>
</html><?php /**PATH /Users/zhuxbo/work/code/cnssl/apps/backend/storage/framework/views/64677285dd08dcd268ca6e7abb821c04.blade.php ENDPATH**/ ?>