@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white'])

@php
$alignmentStyle = match ($align) {
    'left' => 'left: 0;',
    'top' => 'bottom: 100%;',
    default => 'right: 0;',
};

$widthStyle = match ($width) {
    '48' => 'width: 12rem;',
    default => "width: $width;",
};
@endphp

<div class="position-relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            class="position-absolute z-index-100 mt-2 shadow border bg-white rounded-2 {{ $contentClasses }}"
            style="display: none; {{ $widthStyle }} {{ $alignmentStyle }} z-index: 1000;"
            @click="open = false">
        <div class="rounded-2">
            {{ $content }}
        </div>
    </div>
</div>
