@php
    $seoTitle = config('seo.title');
    $seoDescription = config('seo.description');
    $seoUrl = rtrim(config('seo.url'), '/').'/';
    $seoLocale = config('seo.locale', app()->getLocale());
    $seoOrganization = config('seo.organization');
    $canonicalUrl = request()->routeIs('home') ? $seoUrl : url()->current();
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Budget Tracker',
        'alternateName' => config('seo.site_name'),
        'applicationCategory' => 'FinanceApplication',
        'operatingSystem' => 'Web',
        'url' => $seoUrl,
        'description' => $seoDescription,
        'publisher' => [
            '@type' => 'Organization',
            'name' => $seoOrganization['name'],
            'url' => $seoOrganization['url'],
        ],
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'XOF',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $seoLocale) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="keywords" content="{{ config('seo.keywords') }}">
        <meta name="robots" content="index, follow">
        <meta name="author" content="{{ $seoOrganization['name'] }}">
        <meta name="application-name" content="{{ config('seo.site_name') }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('seo.site_name') }}">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:locale" content="{{ $seoLocale }}">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">

        <title inertia>{{ $seoTitle }}</title>

        <script type="application/ld+json">
            {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
