<?php
// ================================================== //
// SEO & Social Meta Tags Helper
// Include this in <head> of each page
// ================================================== //

/**
 * Usage:
 *   render_meta([
 *     'title'       => 'Page Title',
 *     'description' => 'Page description',
 *     'url'         => 'https://moghadam.pro/mobbin/',
 *     'image'       => 'https://moghadam.pro/mobbin/asset/og-default.png',
 *     'type'        => 'website', // or 'article'
 *     'noindex'     => false,     // true for admin pages
 *   ]);
 */
function render_meta(array $meta): void
{
    $siteName    = 'Private Mobbin Mirror';
    $title       = isset($meta['title']) ? e($meta['title']) . ' — ' . $siteName : $siteName;
    $description = e($meta['description'] ?? 'A curated personal library of UI screenshots from apps and sites.');
    $url         = e($meta['url']         ?? BASE_URL);
    $image       = e($meta['image']       ?? BASE_URL . 'asset/og-default.png');
    $type        = e($meta['type']        ?? 'website');
    $noindex     = !empty($meta['noindex']);

    echo <<<HTML
    <!-- Primary SEO -->
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <link rel="canonical" href="{$url}">

    <!-- Robots -->
    <meta name="robots" content="
HTML;

    echo $noindex ? 'noindex, nofollow">' : 'index, follow">';

    echo <<<HTML

    <!-- Open Graph (Facebook, LinkedIn, WhatsApp) -->
    <meta property="og:type"        content="{$type}">
    <meta property="og:site_name"   content="{$siteName}">
    <meta property="og:title"       content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:url"         content="{$url}">
    <meta property="og:image"       content="{$image}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height"content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{$title}">
    <meta name="twitter:description" content="{$description}">
    <meta name="twitter:image"       content="{$image}">
HTML;

}
