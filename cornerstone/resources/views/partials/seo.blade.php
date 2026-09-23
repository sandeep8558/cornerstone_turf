@php
    $currentUrl = request()->url();
    $isAuthOrAdmin = request()->is('login') || request()->is('register') || request()->is('forgot-password') || request()->is('reset-password') || request()->is('verify-email') || request()->is('confirm-password') || request()->is('administrator*') || request()->is('manager*') || request()->is('profile*') || request()->is('dashboard*');

    // Default configuration values
    $defaultTitle = 'Cornerstone Turf Ambernath | Best Football & Box Cricket Turf';
    $defaultDescription = 'Book the best football and box cricket turf in Ambernath West. Experience FIFA-approved grass, professional floodlights, and 24/7 booking at Cornerstone Turf. Secure your slot now!';
    $defaultKeywords = 'Football Turf Ambernath, Box Cricket Ambernath, Turf Booking Ambernath West, Sports Ground Ambernath, Cornerstone Turf, Near Sarvodaya Vidyalaya Turf, 5-a-side Football Ambernath';
    $defaultOgImage = asset('assets/images/turf_1.png');

    // Smart Title Resolution
    $resolvedTitle = $defaultTitle;
    $shouldAppendSuffix = false;

    if (isset($title)) {
        $resolvedTitle = $title;
        $shouldAppendSuffix = true;
    } elseif (isset($meta_title)) {
        $resolvedTitle = $meta_title;
    } elseif ($isAuthOrAdmin) {
        if (request()->is('administrator*')) {
            $resolvedTitle = 'Administrator';
        } elseif (request()->is('manager*')) {
            $resolvedTitle = 'Manager Dashboard';
        } elseif (request()->is('login')) {
            $resolvedTitle = 'Login';
        } elseif (request()->is('register')) {
            $resolvedTitle = 'Create Account';
        } elseif (request()->is('forgot-password')) {
            $resolvedTitle = 'Forgot Password';
        } elseif (request()->is('reset-password')) {
            $resolvedTitle = 'Reset Password';
        } elseif (request()->is('verify-email')) {
            $resolvedTitle = 'Verify Email';
        } elseif (request()->is('confirm-password')) {
            $resolvedTitle = 'Confirm Password';
        } else {
            $resolvedTitle = 'Dashboard';
        }
        $shouldAppendSuffix = true;
    }

    // Resolve Title String
    $metaTitleString = $resolvedTitle;
    if ($shouldAppendSuffix) {
        $metaTitleString = $resolvedTitle . ' — ' . config('app.name', 'Cornerstone Turf');
    }
@endphp

<!-- Primary SEO Meta Tags -->
<title>@hasSection('title')@yield('title') — {{ config('app.name', 'Cornerstone Turf') }}@else{{ $metaTitleString }}@endif</title>
<meta name="title" content="@hasSection('title')@yield('title') — {{ config('app.name', 'Cornerstone Turf') }}@else{{ $metaTitleString }}@endif">
<meta name="description" content="@hasSection('meta_description')@yield('meta_description')@else{{ $description ?? ($meta_description ?? $defaultDescription) }}@endif">
<meta name="keywords" content="@hasSection('meta_keywords')@yield('meta_keywords')@else{{ $keywords ?? ($meta_keywords ?? $defaultKeywords) }}@endif">
<meta name="robots" content="@hasSection('robots')@yield('robots')@else{{ $robots ?? ($meta_robots ?? ($isAuthOrAdmin ? 'noindex, nofollow' : 'index, follow')) }}@endif">
<link rel="canonical" href="@hasSection('canonical')@yield('canonical')@else{{ $canonical ?? ($canonical_url ?? $currentUrl) }}@endif">
<meta name="author" content="Cornerstone Turf">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="@hasSection('canonical')@yield('canonical')@else{{ $canonical ?? ($canonical_url ?? $currentUrl) }}@endif">
<meta property="og:title" content="@hasSection('title')@yield('title') — {{ config('app.name', 'Cornerstone Turf') }}@else{{ $metaTitleString }}@endif">
<meta property="og:description" content="@hasSection('meta_description')@yield('meta_description')@else{{ $description ?? ($meta_description ?? $defaultDescription) }}@endif">
<meta property="og:image" content="@hasSection('og_image')@yield('og_image')@else{{ $og_image ?? $defaultOgImage }}@endif">
<meta property="og:site_name" content="{{ config('app.name', 'Cornerstone Turf') }}">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="@hasSection('canonical')@yield('canonical')@else{{ $canonical ?? ($canonical_url ?? $currentUrl) }}@endif">
<meta property="twitter:title" content="@hasSection('title')@yield('title') — {{ config('app.name', 'Cornerstone Turf') }}@else{{ $metaTitleString }}@endif">
<meta property="twitter:description" content="@hasSection('meta_description')@yield('meta_description')@else{{ $description ?? ($meta_description ?? $defaultDescription) }}@endif">
<meta property="twitter:image" content="@hasSection('og_image')@yield('og_image')@else{{ $og_image ?? $defaultOgImage }}@endif">

<!-- Global Favicon / App Icon Integration -->
<link rel="icon" type="image/png" href="/assets/images/cornerstoneturf_logo.png">
<link rel="apple-touch-icon" href="/assets/images/cornerstoneturf_logo.png">

<!-- Dynamic Schema / Extra Head Items Stack -->
@stack('seo')
