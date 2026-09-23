<?php

use App\Models\Turf;
use App\Models\Location;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    protected $paginationTheme = 'bootstrap';
    
    public $editingTurf = null;
    
    public $location_id = '';
    public $name = '';
    public $description = '';
    public $turf_type = 'Synthetic';
    public $area = '';
    public $equipments = '';
    public $is_active = 'Yes';

    public function updatingSearch() {
        $this->resetPage();
    }

    public function turfs() {
        return Turf::with('location')
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%')
                      ->orWhere('area', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function locations() {
        return Location::orderBy('name')->get();
    }

    public function addTurf() {
        $this->reset(['location_id', 'name', 'description', 'turf_type', 'area', 'equipments', 'is_active', 'editingTurf']);
        $this->dispatch('open-modal', 'turf-modal');
    }

    public function editTurf(Turf $turf) {
        $this->editingTurf = $turf;
        $this->location_id = $turf->location_id;
        $this->name = $turf->name;
        $this->description = $turf->description;
        $this->turf_type = $turf->turf_type;
        $this->area = $turf->area;
        $this->equipments = $turf->equipments;
        $this->is_active = $turf->is_active;
        $this->dispatch('open-modal', 'turf-modal');
    }

    public function saveTurf() {
        $validated = $this->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'turf_type' => ['required', 'in:Synthetic,Hard,Other'],
            'area' => ['nullable', 'string', 'max:255'],
            'equipments' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'in:Yes,No'],
        ]);

        if ($this->editingTurf) {
            $this->editingTurf->update($validated);
            session()->flash('message', 'Turf updated successfully.');
        } else {
            Turf::create($validated);
            session()->flash('message', 'Turf created successfully.');
        }

        $this->dispatch('close-modal', 'turf-modal');
    }

    public function deleteTurf(Turf $turf) {
        $turf->delete();
        session()->flash('message', 'Turf deleted successfully.');
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
        $turfs = $this->turfs();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Recent Turfs ({{ $turfs->total() }})</h2>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by name or description...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addTurf" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add Turf</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Turf Name / Location</th>
                    <th class="px-4 py-3 text-start fw-semibold">Type / Area</th>
                    <th class="px-4 py-3 text-start fw-semibold">Status</th>
                    <th class="px-4 py-3 text-start fw-semibold">Created At</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($turfs as $turf)
                    <tr wire:key="turf-{{ $turf->id }}">
                        <td class="px-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-info d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width: 2.25rem; height: 2.25rem; font-size: 0.85rem;">
                                    {{ strtoupper(substr($turf->name, 0, 1)) }}
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-dark">{{ $turf->name }}</span>
                                    <span class="extra-small text-muted">{{ $turf->location->name }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="d-flex flex-column">
                                <span class="badge rounded-pill bg-light text-dark border fw-medium align-self-start mb-1" style="font-size: 0.65rem;">
                                    {{ $turf->turf_type }}
                                </span>
                                @if($turf->area)
                                    <span class="extra-small text-muted italic">Area: {{ $turf->area }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($turf->is_active === 'Yes')
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fw-medium" style="font-size: 0.65rem;">Active</span>
                            @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-medium" style="font-size: 0.65rem;">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $turf->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editTurf({{ $turf->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteTurf({{ $turf->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-5 text-center text-muted">
                            <svg class="mx-auto mb-3 text-light" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <p class="small mb-0">No turfs found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $turfs->links() }}
    </div>

    <!-- Turf Modal (Bootstrap 5) -->
    <div class="modal fade" id="turfModal" tabindex="-1" aria-labelledby="turfModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form wire:submit="saveTurf" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden text-dark">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0" id="turfModalLabel">
                        {{ $editingTurf ? 'Edit Turf: ' . $editingTurf->name : 'Add New Turf' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Location</label>
                            <select wire:model="location_id" class="form-select rounded-3">
                                <option value="">Choose a location...</option>
                                @foreach($this->locations() as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                            @error('location_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Turf Name</label>
                            <input wire:model="name" type="text" class="form-control rounded-3" placeholder="e.g. Main Synthetic Pitch">
                            @error('name') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Description</label>
                        <textarea wire:model="description" class="form-control rounded-3" rows="3" placeholder="Additional details about the turf..."></textarea>
                        @error('description') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Turf Type</label>
                            <select wire:model="turf_type" class="form-select rounded-3">
                                <option value="Synthetic">Synthetic</option>
                                <option value="Hard">Hard</option>
                                <option value="Other">Other</option>
                            </select>
                            @error('turf_type') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Area (sq ft/size)</label>
                            <input wire:model="area" type="text" class="form-control rounded-3" placeholder="e.g. 5000 sq ft">
                            @error('area') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Is Active?</label>
                            <select wire:model="is_active" class="form-select rounded-3">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            @error('is_active') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Equipments Provided</label>
                        <textarea wire:model="equipments" class="form-control rounded-3" rows="2" placeholder="List items like: Goal posts, Floodlights, Bibs..."></textarea>
                        @error('equipments') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold">
                        {{ $editingTurf ? 'Update Turf' : 'Create Turf' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modalElement = document.getElementById('turfModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        });

        $wire.on('close-modal', () => {
            const modalElement = document.getElementById('turfModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        });
    </script>
    @endscript
</div>
