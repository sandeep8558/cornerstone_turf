@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active bg-success text-white px-3 py-2 rounded-2 fw-medium'
            : 'nav-link text-dark px-3 py-2 rounded-2 fw-medium hover-bg-light';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
