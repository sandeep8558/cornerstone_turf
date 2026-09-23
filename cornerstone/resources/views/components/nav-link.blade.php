@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active fw-bold border-bottom border-success border-2 px-1 pb-1'
            : 'nav-link text-secondary px-1 pb-1 border-bottom border-transparent';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
