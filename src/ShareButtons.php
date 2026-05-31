<?php declare(strict_types=1);
namespace Gratis\Social;

final class ShareButtons
{
    public static function appendToContent(string $content): string
    {
        if (!is_singular('post') || is_admin()) return $content;
        return $content . self::render();
    }

    public static function render(): string
    {
        $url = urlencode(get_permalink());
        $title = urlencode(get_the_title());

        $buttons = [
            ['label' => '𝕏', 'url' => "https://twitter.com/intent/tweet?url={$url}&text={$title}", 'class' => 'x'],
            ['label' => 'in', 'url' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}", 'class' => 'linkedin'],
            ['label' => 'f', 'url' => "https://www.facebook.com/sharer/sharer.php?u={$url}", 'class' => 'facebook'],
            ['label' => '✉', 'url' => "mailto:?subject={$title}&body={$url}", 'class' => 'email'],
        ];

        $html = '<div class="gratis-share">';
        $html .= '<span class="gratis-share-label">Share:</span>';
        foreach ($buttons as $btn) {
            $html .= '<a href="' . $btn['url'] . '" class="gratis-share-btn gratis-share-' . $btn['class'] . '" target="_blank" rel="noopener">' . $btn['label'] . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function inlineCSS(): void
    {
        if (!is_singular('post')) return;
        echo '<style>.gratis-share{display:flex;align-items:center;gap:8px;margin:2rem 0;padding:1rem 0;border-top:1px solid rgba(255,255,255,0.08)}.gratis-share-label{font-size:0.85rem;color:rgba(255,255,255,0.5);font-weight:600}.gratis-share-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);color:rgba(255,255,255,0.7);text-decoration:none;font-size:14px;font-weight:700;transition:all 150ms}.gratis-share-btn:hover{background:rgba(255,255,255,0.1);color:#fff;border-color:rgba(255,255,255,0.3)}</style>' . "\n";
    }
}
