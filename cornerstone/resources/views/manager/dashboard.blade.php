<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manager Workspace') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Welcome, Manager</h3>
                    <p class="text-gray-600">You have access to manage operations in the Cornerstone Turf system.</p>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-green-subtle border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-0">Client Management</h4>
                            <p class="text-sm text-green-600 mt-1 mb-0">Manage client accounts</p>
                        </div>
                        <div class="bg-green-subtle border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-0">Bookings</h4>
                            <p class="text-sm text-green-600 mt-1 mb-0">View and manage bookings</p>
                        </div>
                        <div class="bg-green-subtle border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-0">Reports</h4>
                            <p class="text-sm text-green-600 mt-1 mb-0">View operational reports</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
