<?php

/**
 * 模板处理助手类
 */
class TemplateHelper
{
    private static $templatePath = 'install-assets/';

    /**
     * 加载模板文件
     */
    public static function load($templateName)
    {
        $templateFile = __DIR__.'/'.$templateName.'.html';
        if (! file_exists($templateFile)) {
            throw new Exception("模板文件不存在: $templateName.html");
        }

        return file_get_contents($templateFile);
    }

    /**
     * 渲染模板
     */
    public static function render($templateName, $variables = [])
    {
        $template = self::load($templateName);

        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }

    /**
     * 生成错误或警告部分HTML
     */
    public static function generateMessageSection($messages, $type = 'error')
    {
        if (empty($messages)) {
            return '';
        }

        $title = $type === 'error' ? '错误' : '警告';
        $html = '<h2>'.$title.'</h2>';
        $html .= '<div class="log">';

        foreach ($messages as $message) {
            $html .= '<div class="'.$type.'">✘ '.htmlspecialchars($message).'</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 生成单个检查项HTML
     */
    public static function generateRequirementItem($name, $value, $status)
    {
        return '<div class="'.$status.'" style="padding: 5px;">'.
               htmlspecialchars($name).': '.htmlspecialchars($value).
               '</div>';
    }

    /**
     * 生成检查项列表HTML
     */
    public static function generateRequirementsList($items)
    {
        $html = '';
        foreach ($items as $item) {
            $html .= self::generateRequirementItem($item['name'], $item['value'], $item['status']);
        }

        return $html;
    }

    /**
     * 生成总结信息HTML
     */
    public static function generateSummary($allSuccess, $hasWarnings = false, $successText = '', $warningText = '', $errorText = '')
    {
        if ($allSuccess) {
            if ($hasWarnings) {
                return '<span class="success" style="padding: 5px;">✓ '.$successText.'</span>'.
                       '<br><span class="warning" style="padding: 5px;">⚠ '.$warningText.'</span>';
            } else {
                return '<span class="success" style="padding: 5px;">✓ '.$successText.'</span>';
            }
        } else {
            return '<span class="error" style="padding: 5px;">✘ '.$errorText.'</span>';
        }
    }
}
