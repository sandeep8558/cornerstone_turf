<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.seo')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-light">

    @livewireScripts
    <div class="min-vh-100 d-flex" x-data="{
                drawerOpen: false,
                isMobile: window.innerWidth < 1024
            }" @resize.window="isMobile = window.innerWidth < 1024; if (!isMobile) drawerOpen = false">
        <div x-show="isMobile && drawerOpen" x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="drawerOpen = false"
            style="position:fixed;inset:0;z-index:1999;background:rgba(0,0,0,0.5);" x-cloak></div>

        <!-- ===== SIDEBAR ===== -->
        <aside :class="{ 'drawer-open': isMobile && drawerOpen }"
            class="admin-sidebar d-flex flex-column bg-green-subtle shadow"
            style="border-right: 1px solid var(--cs-green-200);">
            <!-- Brand -->
            <div class="d-flex align-items-center px-3 border-bottom border-green"
                style="height:4rem; flex-shrink:0; overflow:hidden;">
                <a href="{{ route('administrator.dashboard') }}" class="text-decoration-none">
                    <span class="fw-semibold text-dark text-uppercase">Cornerstone Turf</span>
                </a>
            </div>

            <!-- Nav Links -->
            <nav class="flex-grow-1 py-3 px-2 overflow-y-auto" style="overflow-x:hidden;">

                <a href="{{ route('administrator.dashboard') }}"
                    class="sidebar-nav-link mb-1 {{ request()->routeIs('administrator.dashboard') ? 'active' : '' }}">
                    <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 13.5h7.5V3H3v10.5ZM3 21h7.5v-4.5H3V21ZM13.5 21H21V10.5h-7.5V21ZM13.5 3v4.5H21V3h-7.5Z" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('administrator.slider') }}"
                    class="sidebar-nav-link mb-1 {{ request()->routeIs('administrator.slider') ? 'active' : '' }}">
                    <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    <span>Image Slider</span>
                </a>

                <a href="{{ route('administrator.users') }}"
                    class="sidebar-nav-link mb-1 {{ request()->routeIs('administrator.users') ? 'active' : '' }}">
                    <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span>User Manager</span>
                </a>

                <a href="{{ route('administrator.locations') }}"
                    class="sidebar-nav-link mb-1 {{ request()->routeIs('administrator.locations') ? 'active' : '' }}">
                    <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    <span>Location Manager</span>
                </a>

                <div x-data="{ open: {{ request()->routeIs('administrator.turfs*') || request()->routeIs('administrator.photos*') || request()->routeIs('administrator.facilities*') || request()->routeIs('administrator.sports*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" 
                        class="sidebar-nav-link w-100 border-0 text-start mb-1 d-flex align-items-center justify-content-between"
                        :class="{ 
                            'active': open || {{ request()->routeIs('administrator.turfs*') || request()->routeIs('administrator.photos*') || request()->routeIs('administrator.facilities*') || request()->routeIs('administrator.sports*') ? 'true' : 'false' }},
                            'bg-transparent': !(open || {{ request()->routeIs('administrator.turfs*') || request()->routeIs('administrator.photos*') || request()->routeIs('administrator.facilities*') || request()->routeIs('administrator.sports*') ? 'true' : 'false' }})
                        }">
                        <div class="d-flex align-items-center gap-2">
                            <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                                stroke-width="1.75" viewBox="0 0 24 24" :class="{ 'text-white': open || {{ request()->routeIs('administrator.turfs*') || request()->routeIs('administrator.photos*') || request()->routeIs('administrator.facilities*') || request()->routeIs('administrator.sports*') ? 'true' : 'false' }} }">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <span>Turfs Menu</span>
                        </div>
                        <svg class="transition-transform" :class="{ 'rotate-180': open, 'text-white': open || {{ request()->routeIs('administrator.turfs*') || request()->routeIs('administrator.photos*') || request()->routeIs('administrator.facilities*') || request()->routeIs('administrator.sports*') ? 'true' : 'false' }} }" style="width:1rem;height:1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse x-cloak class="ps-3 mb-2">
                        <a href="{{ route('administrator.turfs') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.turfs') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Turf Manager</span>
                        </a>
                        <a href="{{ route('administrator.photos') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.photos') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Photos</span>
                        </a>
                        <a href="{{ route('administrator.facilities') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.facilities') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Facilities</span>
                        </a>
                        <a href="{{ route('administrator.sports') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.sports') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Sports</span>
                        </a>
                    </div>
                </div>

                <div x-data="{ open: {{ request()->routeIs('administrator.slot-categories*') || request()->routeIs('administrator.slots*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" 
                        class="sidebar-nav-link w-100 border-0 text-start mb-1 d-flex align-items-center justify-content-between"
                        :class="{ 
                            'active': open || {{ request()->routeIs('administrator.slot-categories*') || request()->routeIs('administrator.slots*') ? 'true' : 'false' }},
                            'bg-transparent': !(open || {{ request()->routeIs('administrator.slot-categories*') || request()->routeIs('administrator.slots*') ? 'true' : 'false' }})
                        }">
                        <div class="d-flex align-items-center gap-2">
                            <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                                stroke-width="1.75" viewBox="0 0 24 24" :class="{ 'text-white': open || {{ request()->routeIs('administrator.slot-categories*') || request()->routeIs('administrator.slots*') ? 'true' : 'false' }} }">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span>Slot Manager</span>
                        </div>
                        <svg class="transition-transform" :class="{ 'rotate-180': open, 'text-white': open || {{ request()->routeIs('administrator.slot-categories*') || request()->routeIs('administrator.slots*') ? 'true' : 'false' }} }" style="width:1rem;height:1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse x-cloak class="ps-3 mb-2">
                        <a href="{{ route('administrator.slot-categories') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.slot-categories') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Slot Category</span>
                        </a>
                        <a href="{{ route('administrator.slots') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.slots') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Slots</span>
                        </a>
                    </div>
                </div>

                <div x-data="{ open: {{ request()->routeIs('administrator.book-slots*') || request()->routeIs('administrator.block-booking*') || request()->routeIs('administrator.bookings*') || request()->routeIs('administrator.payments*') ? 'true' : 'false' }} }">
                    <button @click="open = !open" 
                        class="sidebar-nav-link w-100 border-0 text-start mb-1 d-flex align-items-center justify-content-between"
                        :class="{ 
                            'active': open || {{ request()->routeIs('administrator.book-slots*') || request()->routeIs('administrator.block-booking*') || request()->routeIs('administrator.bookings*') || request()->routeIs('administrator.payments*') ? 'true' : 'false' }},
                            'bg-transparent': !(open || {{ request()->routeIs('administrator.book-slots*') || request()->routeIs('administrator.block-booking*') || request()->routeIs('administrator.bookings*') || request()->routeIs('administrator.payments*') ? 'true' : 'false' }})
                        }">
                        <div class="d-flex align-items-center gap-2">
                            <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                                stroke-width="1.75" viewBox="0 0 24 24" :class="{ 'text-white': open || {{ request()->routeIs('administrator.book-slots*') || request()->routeIs('administrator.block-booking*') || request()->routeIs('administrator.bookings*') || request()->routeIs('administrator.payments*') ? 'true' : 'false' }} }">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M21 12a8.25 8.25 0 0 1-16.5 0c0-2.333.972-4.44 2.531-5.939l-1.031-1.03M18 1.5l-3 3m2.859 6.75a3 3 0 1 1-5.718 0" />
                            </svg>
                            <span>Booking Manager</span>
                        </div>
                        <svg class="transition-transform" :class="{ 'rotate-180': open, 'text-white': open || {{ request()->routeIs('administrator.book-slots*') || request()->routeIs('administrator.block-booking*') || request()->routeIs('administrator.bookings*') || request()->routeIs('administrator.payments*') ? 'true' : 'false' }} }" style="width:1rem;height:1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse x-cloak class="ps-3 mb-2">
                        <a href="{{ route('administrator.book-slots') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.book-slots') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Book Slots</span>
                        </a>
                        <a href="{{ route('administrator.block-booking') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.block-booking') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Block Booking</span>
                        </a>
                        <a href="{{ route('administrator.bookings') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.bookings') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Bookings</span>
                        </a>
                        <a href="{{ route('administrator.payments') }}"
                            class="sidebar-nav-link mb-1 py-1 px-3 {{ request()->routeIs('administrator.payments') ? 'active' : '' }}" style="font-size: 0.85rem;">
                            <span>Payments</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('administrator.coupons') }}"
                    class="sidebar-nav-link mb-1 {{ request()->routeIs('administrator.coupons') ? 'active' : '' }}">
                    <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581a2.25 2.25 0 0 0 3.182 0l4.318-4.318a2.25 2.25 0 0 0 0-3.182L11.159 3.659A2.25 2.25 0 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    <span>Offers & Discounts</span>
                </a>

                <a href="{{ route('administrator.settings') }}"
                    class="sidebar-nav-link mb-1 {{ request()->routeIs('administrator.settings') ? 'active' : '' }}">
                    <svg class="flex-shrink-0 text-success" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1 1 15 0 7.5 7.5 0 0 1-15 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3l2 2" />
                    </svg>
                    <span>Settings</span>
                </a>

            </nav>

            <!-- Bottom Menu: Profile & Logout -->
            <div class="px-2 py-3 border-top border-green" style="flex-shrink:0;">
                <a href="{{ route('administrator.profile') }}"
                    class="sidebar-nav-link mb-2 {{ request()->routeIs('administrator.profile') ? 'active' : '' }}">
                    <svg class="flex-shrink-0" style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor"
                        stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0Z" />
                    </svg>
                    <span>Profile</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sidebar-nav-link w-100 border-0 bg-transparent text-start"
                        style="color:#374151;" onmouseover="this.style.background='#fee2e2';this.style.color='#b91c1c';"
                        onmouseout="this.style.background='';this.style.color='#374151';">
                        <svg class="flex-shrink-0" style="width:1.25rem;height:1.25rem;" fill="none"
                            stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                        </svg>
                        <span>Log Out</span>
                    </button>
                </form>
            </div>


        </aside>

        <!-- ===== MAIN AREA ===== -->
        <div class="d-flex flex-column flex-grow-1" style="min-width:0;">

            <!-- Top Navbar -->
            <header class="bg-white shadow-sm d-flex align-items-center justify-content-between px-3 px-lg-4"
                style="height:4rem; flex-shrink:0;">
                <div class="d-flex align-items-center gap-3">
                    <!-- Mobile hamburger -->
                    <button x-show="isMobile" @click="drawerOpen = !drawerOpen" class="btn btn-light btn-sm p-2">
                        <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    @isset($heading)
                        <h1 class="h6 mb-0 fw-semibold text-dark">{{ $heading }}</h1>
                    @endisset
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="d-none d-sm-block text-muted small">{{ auth()->user()->name }}</span>
                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white fw-semibold flex-shrink-0"
                        style="width:2rem;height:2rem;font-size:.875rem;">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                </div>
            </header>

            <!-- Work Area -->
            <main class="flex-grow-1 overflow-y-auto p-3 p-lg-4">
                {{ $slot }}
            </main>
        </div>

    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

</body>

</html>