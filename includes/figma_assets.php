<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * Figma assets helper:
 * - Mandatory contract: every slot has both SVG and PNG.
 * - No fallback for the asset itself; caller decides how to display errors.
 */

function figma_manifest(): array
{
    static $manifest = null;
    if ($manifest !== null) {
        return $manifest;
    }

    $manifestFile = dirname(__DIR__) . '/config/figma_assets.php';
    if (!is_file($manifestFile)) {
        throw new RuntimeException('ملف إعداد أصول Figma غير موجود.');
    }

    $loaded = require $manifestFile;
    if (!is_array($loaded)) {
        throw new RuntimeException('صيغة ملف إعداد أصول Figma غير صحيحة.');
    }

    $manifest = $loaded;
    return $manifest;
}

function figma_page_slot_regex(string $value): bool
{
    return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
}

function figma_asset_meta(string $page, string $slot): array
{
    $manifest = figma_manifest();
    if (!isset($manifest[$page]) || !is_array($manifest[$page])) {
        throw new RuntimeException("تعريف صفحة Figma غير موجود: {$page}");
    }
    if (!isset($manifest[$page][$slot]) || !is_array($manifest[$page][$slot])) {
        throw new RuntimeException("تعريف slot غير موجود: {$page}/{$slot}");
    }

    return $manifest[$page][$slot];
}

function figma_relpath(string $page, string $slot, string $format = 'svg'): string
{
    $fmt = strtolower(trim($format));
    if ($fmt !== 'svg' && $fmt !== 'png') {
        throw new RuntimeException("صيغة Figma غير مدعومة: {$format}");
    }

    return "assets/figma/{$page}/{$slot}.{$fmt}";
}

function figma_abspath(string $page, string $slot, string $format = 'svg'): string
{
    return dirname(__DIR__) . '/' . figma_relpath($page, $slot, $format);
}

function figma_detect_base_url(): string
{
    return app_base_path();
}

function figma_asset_url(string $page, string $slot, string $format = 'svg'): string
{
    // Ensure slot exists in config before generating URL.
    figma_asset_meta($page, $slot);

    $base = rtrim(figma_detect_base_url(), '/');
    return ($base !== '' ? $base : '') . '/' . figma_relpath($page, $slot, $format);
}

function figma_asset_assert(string $page, string $slot): void
{
    if (!figma_page_slot_regex($page)) {
        throw new RuntimeException("اسم الصفحة غير مطابق للعقد: {$page}");
    }
    if (!figma_page_slot_regex($slot)) {
        throw new RuntimeException("اسم slot غير مطابق للعقد: {$slot}");
    }

    $meta = figma_asset_meta($page, $slot);
    $svg = figma_abspath($page, $slot, 'svg');
    $png = figma_abspath($page, $slot, 'png');

    if (!is_file($svg)) {
        throw new RuntimeException("أصل Figma مفقود: {$page}/{$slot}.svg");
    }
    if (!is_file($png)) {
        throw new RuntimeException("أصل Figma مفقود: {$page}/{$slot}.png");
    }

    if (filesize($svg) === 0) {
        throw new RuntimeException("ملف Figma فارغ: {$page}/{$slot}.svg");
    }
    if (filesize($png) === 0) {
        throw new RuntimeException("ملف Figma فارغ: {$page}/{$slot}.png");
    }

    $displayW = isset($meta['width']) ? (int)$meta['width'] : 0;
    $displayH = isset($meta['height']) ? (int)$meta['height'] : 0;
    if ($displayW > 0 && $displayH > 0) {
        $pngInfo = @getimagesize($png);
        if (!is_array($pngInfo) || count($pngInfo) < 2) {
            throw new RuntimeException("تعذر قراءة أبعاد PNG: {$page}/{$slot}.png");
        }
        $requiredW = $displayW * 2;
        $requiredH = $displayH * 2;
        if ((int)$pngInfo[0] < $requiredW || (int)$pngInfo[1] < $requiredH) {
            throw new RuntimeException(
                "أبعاد PNG أقل من المطلوب (2x) للـslot {$page}/{$slot}. مطلوب {$requiredW}x{$requiredH}."
            );
        }
    }
}

function figma_escape_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function figma_asset_img(string $page, string $slot, array $attrs = [], string $format = 'svg'): string
{
    figma_asset_assert($page, $slot);
    $meta = figma_asset_meta($page, $slot);
    $url = figma_asset_url($page, $slot, $format);

    $defaultAttrs = [
        'src' => $url,
        'alt' => (string)($meta['alt'] ?? "{$page}-{$slot}"),
        'loading' => 'lazy',
        'decoding' => 'async',
        'data-figma-slot' => $slot,
    ];

    if (!isset($attrs['width']) && isset($meta['width'])) {
        $defaultAttrs['width'] = (string)$meta['width'];
    }
    if (!isset($attrs['height']) && isset($meta['height'])) {
        $defaultAttrs['height'] = (string)$meta['height'];
    }

    $all = array_merge($defaultAttrs, $attrs);
    $parts = [];
    foreach ($all as $k => $v) {
        if ($v === null) {
            continue;
        }
        $parts[] = figma_escape_attr((string)$k) . '="' . figma_escape_attr((string)$v) . '"';
    }

    return '<img ' . implode(' ', $parts) . '>';
}

function figma_slot_or_error(string $page, string $slot, string $format = 'svg', array $attrs = []): string
{
    try {
        return figma_asset_img($page, $slot, $attrs, $format);
    } catch (Throwable $e) {
        $msg = figma_escape_attr($e->getMessage());
        return '<div class="figma-asset-error" data-figma-slot="' . figma_escape_attr($slot) . '">' .
            '⚠️ ' . $msg . '</div>';
    }
}

function figma_render_page_slots(string $page, ?array $slots = null, string $format = 'svg', array $containerAttrs = []): string
{
    $manifest = figma_manifest();
    if (!isset($manifest[$page]) || !is_array($manifest[$page])) {
        return '<div class="figma-asset-error">⚠️ صفحة Figma غير معرفة: ' . figma_escape_attr($page) . '</div>';
    }

    $targetSlots = $slots;
    if ($targetSlots === null) {
        $targetSlots = array_keys($manifest[$page]);
    }

    $class = 'figma-slots figma-slots--' . $page;
    if (isset($containerAttrs['class']) && trim((string)$containerAttrs['class']) !== '') {
        $class = trim((string)$containerAttrs['class']) . ' ' . $class;
    }
    $containerAttrs['class'] = $class;

    $attrParts = [];
    foreach ($containerAttrs as $k => $v) {
        if ($v === null) {
            continue;
        }
        $attrParts[] = figma_escape_attr((string)$k) . '="' . figma_escape_attr((string)$v) . '"';
    }

    $html = '<div ' . implode(' ', $attrParts) . '>';
    foreach ($targetSlots as $slot) {
        $slotName = (string)$slot;
        $html .= '<div class="figma-slot figma-slot--' . figma_escape_attr($slotName) . '">';
        $html .= figma_slot_or_error($page, $slotName, $format, ['class' => 'figma-slot-img']);
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}
