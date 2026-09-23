<x-administrator-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="heading">Dashboard</x-slot>

    @php
        // Users stats
        $totalUsers = \App\Models\User::count();
        $totalManagers = \App\Models\User::role('Manager')->count();

        // Bookings queries (exclude Failed)
        $bookingsQuery = \App\Models\Booking::where('status', '!=', 'Failed');
        $totalBookings = (clone $bookingsQuery)->count();
        
        $todayStr = \Carbon\Carbon::today()->toDateString();
        $upcomingBookings = (clone $bookingsQuery)->where('date', '>=', $todayStr)->count();
        $pastBookings = (clone $bookingsQuery)->where('date', '<', $todayStr)->count();

        // Financial queries (only Success/Pending bookings contribute to active billing/payments)
        $activeBookings = \App\Models\Booking::whereIn('status', ['Success', 'Pending'])->get();
        
        $totalBilling = $activeBookings->sum('amount');
        $totalReceived = \App\Models\BookingPayment::whereHas('booking', function($q) {
            $q->whereIn('status', ['Success', 'Pending']);
        })->sum('amount');
        
        $pendingPayments = $activeBookings->sum(function($b) {
            return max(0, $b->amount - $b->total_received);
        });
    @endphp

    <!-- Section: Business Financials -->
    <div class="mb-5">
        <h4 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing: 0.08em; font-size: 0.8rem;">Financial Summary</h4>
        <div class="row row-cols-1 row-cols-sm-3 g-4">
            <!-- Total Billing -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #6366f1 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Billing Amount</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.5rem; height: 2.5rem; background: #e0e7ff; color: #4f46e5;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($totalBilling, 2) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Total Received -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #10b981 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Received Amount</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.5rem; height: 2.5rem; background: #d1fae5; color: #059669;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($totalReceived, 2) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #f43f5e !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Pending Payments</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.5rem; height: 2.5rem; background: #ffe4e6; color: #e11d48;">
                            <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">₹{{ number_format($pendingPayments, 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Bookings Overview -->
    <div class="mb-5">
        <h4 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing: 0.08em; font-size: 0.8rem;">Bookings Overview</h4>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <!-- Total Bookings -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #0ea5e9 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Bookings</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.25rem; height: 2.25rem; background: #e0f2fe; color: #0284c7;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18m-3-12h15c.621 0 1.125.504 1.125 1.125v10.5c0 .621-.504 1.125-1.125 1.125H4.5c-.621 0-1.125-.504-1.125-1.125V7.125C3.375 6.504 3.879 6 4.5 6Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $totalBookings }}</h3>
                    </div>
                </div>
            </div>

            <!-- Upcoming Bookings -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #8b5cf6 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Upcoming Bookings</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.25rem; height: 2.25rem; background: #ede9fe; color: #7c3aed;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $upcomingBookings }}</h3>
                    </div>
                </div>
            </div>

            <!-- Past Bookings -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #64748b !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Past Bookings</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.25rem; height: 2.25rem; background: #f1f5f9; color: #475569;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $pastBookings }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Users Overview -->
    <div class="mb-5">
        <h4 class="h6 fw-bold text-uppercase text-muted mb-3" style="letter-spacing: 0.08em; font-size: 0.8rem;">Users Overview</h4>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <!-- Total Users -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #0d9488 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Users</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.25rem; height: 2.25rem; background: #ccfbf1; color: #0f766e;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0ZM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $totalUsers }}</h3>
                    </div>
                </div>
            </div>

            <!-- Managers -->
            <div class="col">
                <div class="card border-0 shadow-sm p-4 h-100 rounded-4 position-relative overflow-hidden" 
                     style="background: #ffffff; border-left: 4px solid #f59e0b !important; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: default;"
                     onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(0,0,0,0.05)';"
                     onmouseout="this.style.transform='none'; this.style.boxShadow='';"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="text-muted fw-bold small text-uppercase mb-0" style="letter-spacing: 0.05em; font-size: 0.72rem;">Managers</p>
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" 
                             style="width: 2.25rem; height: 2.25rem; background: #fef3c7; color: #d97706;">
                            <svg style="width: 1.15rem; height: 1.15rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0" style="font-size: 1.6rem; letter-spacing: -0.02em;">{{ $totalManagers }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Welcome card -->
    <div class="card border-0 shadow-sm p-4 rounded-4">
        <h3 class="h6 fw-semibold text-dark mb-2">Welcome back, {{ auth()->user()->name }}</h3>
        <p class="small text-secondary mb-4">You have full access to manage the Cornerstone Turf system.</p>

        <div class="row row-cols-1 row-cols-md-3 g-3">
            <div class="col">
                <a href="{{ route('administrator.users') }}"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 border border-green bg-green-subtle hover-bg-green-100 transition-all text-decoration-none">
                    <svg class="text-success flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-5.996-3.457M17 20H7m10 0v-1c0-.653-.1-1.283-.284-1.875M7 20H2v-1a4 4 0 015.996-3.457M7 20v-1c0-.653.1-1.283.284-1.875m9.432 0A5.97 5.97 0 0012 15a5.97 5.97 0 00-4.716 2.125M15 7a3 3 0 11-6 0 3 3 0 016 0Zm6 3a2 2 0 11-4 0 2 2 0 014 0ZM7 10a2 2 0 11-4 0 2 2 0 014 0Z"/>
                    </svg>
                    <div>
                        <p class="fw-bold text-success small mb-0">User Manager</p>
                        <p class="extra-small text-success opacity-75 mb-0">Manage all users and roles</p>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('administrator.profile') }}"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 border border-green bg-green-subtle hover-bg-green-100 transition-all text-decoration-none">
                    <svg class="text-success flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0Z"/>
                    </svg>
                    <div>
                        <p class="fw-bold text-success small mb-0">My Profile</p>
                        <p class="extra-small text-success opacity-75 mb-0">Update your account details</p>
                    </div>
                </a>
            </div>

            <div class="col">
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 border border-green bg-green-subtle opacity-75">
                    <svg class="text-success flex-shrink-0" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5Z"/>
                    </svg>
                    <div>
                        <p class="fw-bold text-success small mb-0">Reports</p>
                        <p class="extra-small text-success opacity-75 mb-0">Coming soon</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-administrator-layout>
