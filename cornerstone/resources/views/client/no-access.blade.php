<x-guest-layout>
    <div class="text-center">
        <div class="mb-6">
            <svg class="mx-auto h-16 w-16 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Oops!</h1>
        <p class="text-gray-500 mb-2">You don't have access to this site.</p>
        <p class="text-sm text-gray-400 mb-6">Please contact your administrator for assistance.</p>

        <a href="{{ route('login') }}"
            class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
            Back to Login
        </a>
    </div>
</x-guest-layout>
