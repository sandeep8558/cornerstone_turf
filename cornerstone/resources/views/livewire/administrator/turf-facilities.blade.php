<?php

use App\Models\Turf;
use App\Models\TurfFacility;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    protected $paginationTheme = 'bootstrap';
    
    public $editingFacility = null;
    public $turf_id = '';
    public $name = '';
    public $is_active = 'Yes';

    public function updatingSearch() {
        $this->resetPage();
    }

    public function facilities() {
        return TurfFacility::with('turf')
            ->when($this->search, function($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhereHas('turf', function($q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function turfs() {
        return Turf::orderBy('name')->get();
    }

    public function addFacility() {
        $this->reset(['turf_id', 'name', 'is_active', 'editingFacility']);
        $this->dispatch('open-modal', 'facility-modal');
    }

    public function editFacility(TurfFacility $facility) {
        $this->editingFacility = $facility;
        $this->turf_id = $facility->turf_id;
        $this->name = $facility->name;
        $this->is_active = $facility->is_active;
        $this->dispatch('open-modal', 'facility-modal');
    }

    public function saveFacility() {
        $validated = $this->validate([
            'turf_id' => ['required', 'exists:turfs,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'in:Yes,No'],
        ]);

        if ($this->editingFacility) {
            $this->editingFacility->update($validated);
            session()->flash('message', 'Facility updated successfully.');
        } else {
            TurfFacility::create($validated);
            session()->flash('message', 'Facility added successfully.');
        }

        $this->dispatch('close-modal', 'facility-modal');
    }

    public function deleteFacility(TurfFacility $facility) {
        $facility->delete();
        session()->flash('message', 'Facility deleted successfully.');
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
        $facilities = $this->facilities();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Turf Facilities ({{ $facilities->total() }})</h2>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by name or turf...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addFacility" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add Facility</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Facility Name</th>
                    <th class="px-4 py-3 text-start fw-semibold">Turf</th>
                    <th class="px-4 py-3 text-start fw-semibold">Status</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($facilities as $f)
                    <tr wire:key="facility-{{ $f->id }}">
                        <td class="px-4 py-3 fw-bold">{{ $f->name }}</td>
                        <td class="px-4 py-3">{{ $f->turf->name }}</td>
                        <td class="px-4 py-3">
                            <span class="badge rounded-pill bg-{{ $f->is_active === 'Yes' ? 'success' : 'danger' }}-subtle text-{{ $f->is_active === 'Yes' ? 'success' : 'danger' }} border border-{{ $f->is_active === 'Yes' ? 'success' : 'danger' }}-subtle fw-medium">
                                {{ $f->is_active }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editFacility({{ $f->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteFacility({{ $f->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-5 text-center text-muted">
                            <p class="small mb-0">No facilities found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $facilities->links() }}
    </div>

    <!-- Facility Modal -->
    <div class="modal fade" id="facilityModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="saveFacility" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0">{{ $editingFacility ? 'Edit Facility' : 'Add Turf Facility' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-dark">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Select Turf</label>
                        <select wire:model="turf_id" class="form-select rounded-3">
                            <option value="">Choose a turf...</option>
                            @foreach($this->turfs() as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @error('turf_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Facility Name</label>
                        <input wire:model="name" type="text" class="form-control rounded-3" placeholder="e.g. Free Wi-Fi, Changing Rooms...">
                        @error('name') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Is Active?</label>
                        <select wire:model="is_active" class="form-select rounded-3">
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                        @error('is_active') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold">
                        {{ $editingFacility ? 'Update Facility' : 'Add Facility' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('facilityModal'));
            modal.show();
        });
        $wire.on('close-modal', () => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('facilityModal'));
            if (modal) modal.hide();
        });
    </script>
    @endscript
</div>
