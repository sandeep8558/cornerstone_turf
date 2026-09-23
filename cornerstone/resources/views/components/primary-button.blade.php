<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-success fw-semibold text-uppercase px-4 py-2']) }} style="font-size: 0.75rem; letter-spacing: 0.1em;">
    {{ $slot }}
</button>
