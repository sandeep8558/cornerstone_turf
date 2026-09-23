<nav class="d-flex justify-content-end gap-2">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="btn btn-success rounded-pill px-4"
        >
            Dashboard
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="btn btn-outline-success rounded-pill px-4"
        >
            Log in
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="btn btn-success rounded-pill px-4"
            >
                Register
            </a>
        @endif
    @endauth
</nav>
