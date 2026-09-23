<?php

use App\Models\Location;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    protected $paginationTheme = 'bootstrap';
    
    public $showingLocationModal = false;
    public $editingLocation = null;
    
    public $name = '';
    public $address = '';
    public $lat = '';
    public $lon = '';

    public function updatingSearch() {
        $this->resetPage();
    }

    public function locations() {
        return Location::query()
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('address', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function addLocation() {
        $this->reset(['name', 'address', 'lat', 'lon', 'editingLocation']);
        $this->dispatch('open-modal', 'location-modal');
    }

    public function editLocation(Location $location) {
        $this->editingLocation = $location;
        $this->name = $location->name;
        $this->address = $location->address;
        $this->lat = $location->lat;
        $this->lon = $location->lon;
        $this->dispatch('open-modal', 'location-modal');
    }

    public function saveLocation() {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric'],
            'lon' => ['nullable', 'numeric'],
        ]);

        if ($this->editingLocation) {
            $this->editingLocation->update($validated);
            session()->flash('message', 'Location updated successfully.');
        } else {
            Location::create($validated);
            session()->flash('message', 'Location created successfully.');
        }

        $this->dispatch('close-modal', 'location-modal');
    }

    public function deleteLocation(Location $location) {
        $location->delete();
        session()->flash('message', 'Location deleted successfully.');
    }
}; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden text-dark">
    <!-- Messages -->
    @if (session()->has('message'))
        <div class="alert alert-success border-0 rounded-0 mb-0 small py-2 px-4 d-flex align-items-center justify-content-between">
            <span>{{ session('message') }}</span>
            <button type="button" class="btn-close small" data-bs-dismiss="alert" style="font-size: 0.5rem;"></button>
        </div>
    @endif

    @php
        $locations = $this->locations();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Recent Locations ({{ $locations->total() }})</h2>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by name or address...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addLocation" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add Location</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Location Name</th>
                    <th class="px-4 py-3 text-start fw-semibold">Address</th>
                    <th class="px-4 py-3 text-start fw-semibold">Coordinates (Lat, Lon)</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($locations as $location)
                    <tr wire:key="location-{{ $location->id }}">
                        <td class="px-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width: 2.25rem; height: 2.25rem; font-size: 0.85rem;">
                                    {{ strtoupper(substr($location->name, 0, 1)) }}
                                </div>
                                <span class="fw-bold text-dark">{{ $location->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $location->address ?: 'N/A' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($location->lat && $location->lon)
                                <code class="extra-small text-primary">{{ $location->lat }}, {{ $location->lon }}</code>
                            @else
                                <span class="text-muted italic extra-small">Not set</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editLocation({{ $location->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteLocation({{ $location->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-5 text-center text-muted">
                            <svg class="mx-auto mb-3 text-light" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                            <p class="small mb-0">No locations found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $locations->links() }}
    </div>

    <!-- Location Modal (Bootstrap 5) -->
    <div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form wire:submit="saveLocation" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden text-dark">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0" id="locationModalLabel">
                        {{ $editingLocation ? 'Edit Location: ' . $editingLocation->name : 'Add New Location' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Location Name</label>
                        <input wire:model="name" type="text" class="form-control rounded-3" placeholder="e.g. West Coast Facility">
                        @error('name') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Address</label>
                        <textarea wire:model="address" class="form-control rounded-3" rows="2" placeholder="Street address, city, etc."></textarea>
                        @error('address') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Latitude</label>
                            <input wire:model="lat" type="text" class="form-control rounded-3" placeholder="e.g. 34.052235">
                            @error('lat') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Longitude</label>
                            <input wire:model="lon" type="text" class="form-control rounded-3" placeholder="e.g. -118.243683">
                            @error('lon') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold">
                        {{ $editingLocation ? 'Update Location' : 'Create Location' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modalElement = document.getElementById('locationModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        });

        $wire.on('close-modal', () => {
            const modalElement = document.getElementById('locationModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        });
    </script>
    @endscript
</div>
