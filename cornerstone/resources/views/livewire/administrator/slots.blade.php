<?php

use App\Models\Slot;
use App\Models\Turf;
use App\Models\Location;
use App\Models\SlotCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    protected $paginationTheme = 'bootstrap';
    
    public $editingSlot = null;
    
    public $location_id = '';
    public $turf_id = '';
    public $slot_category_id = '';
    public $from = '';
    public $to = '';
    public $minutes = '';
    public $mon_amount = 0;
    public $tue_amount = 0;
    public $wed_amount = 0;
    public $thu_amount = 0;
    public $fri_amount = 0;
    public $sat_amount = 0;
    public $sun_amount = 0;
    public $is_active = 'Yes';

    public function updatingSearch() {
        $this->resetPage();
    }

    public function updatedLocationId($value) {
        $this->turf_id = '';
    }

    public function slots() {
        return Slot::with(['location', 'turf', 'slotCategory'])
            ->when($this->search, function($query) {
                $query->whereHas('turf', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })->orWhereHas('slotCategory', function($q) {
                    $q->where('category_name', 'like', '%' . $this->search . '%');
                })->orWhereHas('location', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function locations() {
        return Location::orderBy('name')->get();
    }

    public function turfs() {
        return Turf::when($this->location_id, function($query) {
                $query->where('location_id', $this->location_id);
            })
            ->orderBy('name')
            ->get();
    }

    public function categories() {
        return SlotCategory::orderBy('category_name')->get();
    }

    public function addSlot() {
        $this->reset(['location_id', 'turf_id', 'slot_category_id', 'from', 'to', 'minutes', 'mon_amount', 'tue_amount', 'wed_amount', 'thu_amount', 'fri_amount', 'sat_amount', 'sun_amount', 'is_active', 'editingSlot']);
        $this->dispatch('open-modal', 'slot-modal');
    }

    public function editSlot(Slot $slot) {
        $this->editingSlot = $slot;
        $this->location_id = $slot->location_id;
        $this->turf_id = $slot->turf_id;
        $this->slot_category_id = $slot->slot_category_id;
        $this->from = \Carbon\Carbon::parse($slot->from)->format('H:i');
        $this->to = \Carbon\Carbon::parse($slot->to)->format('H:i');
        $this->minutes = $slot->minutes;
        $this->mon_amount = $slot->mon_amount;
        $this->tue_amount = $slot->tue_amount;
        $this->wed_amount = $slot->wed_amount;
        $this->thu_amount = $slot->thu_amount;
        $this->fri_amount = $slot->fri_amount;
        $this->sat_amount = $slot->sat_amount;
        $this->sun_amount = $slot->sun_amount;
        $this->is_active = $slot->is_active ? 'Yes' : 'No';
        $this->dispatch('open-modal', 'slot-modal');
    }

    public function saveSlot() {
        $validated = $this->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'turf_id' => ['required', 'exists:turfs,id'],
            'slot_category_id' => ['required', 'exists:slot_categories,id'],
            'from' => ['required'],
            'to' => ['required'],
            'minutes' => ['required', 'integer'],
            'mon_amount' => ['required', 'numeric'],
            'tue_amount' => ['required', 'numeric'],
            'wed_amount' => ['required', 'numeric'],
            'thu_amount' => ['required', 'numeric'],
            'fri_amount' => ['required', 'numeric'],
            'sat_amount' => ['required', 'numeric'],
            'sun_amount' => ['required', 'numeric'],
            'is_active' => ['required', 'in:Yes,No'],
        ]);

        $data = $validated;
        $data['is_active'] = $validated['is_active'] === 'Yes';

        if ($this->editingSlot) {
            $this->editingSlot->update($data);
            session()->flash('message', 'Slot updated successfully.');
        } else {
            Slot::create($data);
            session()->flash('message', 'Slot created successfully.');
        }

        $this->dispatch('close-modal', 'slot-modal');
    }

    public function deleteSlot(Slot $slot) {
        $slot->delete();
        session()->flash('message', 'Slot deleted successfully.');
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
        $slots = $this->slots();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Slots ({{ $slots->total() }})</h2>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by location, turf or category...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addSlot" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add Slot</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Location / Turf / Category</th>
                    <th class="px-4 py-3 text-start fw-semibold">Timing / Duration</th>
                    <th class="px-4 py-3 text-start fw-semibold">Amounts (Mon-Sun)</th>
                    <th class="px-4 py-3 text-start fw-semibold">Status</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($slots as $slot)
                    <tr wire:key="slot-{{ $slot->id }}">
                        <td class="px-4 py-3">
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark">{{ $slot->turf->name }}</span>
                                <span class="extra-small text-muted">{{ $slot->location->name }} — {{ $slot->slotCategory->category_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="d-flex flex-column">
                                <span class="fw-medium">{{ \Carbon\Carbon::parse($slot->from)->format('h:i A') }} - {{ \Carbon\Carbon::parse($slot->to)->format('h:i A') }}</span>
                                <span class="extra-small text-muted">{{ $slot->minutes }} Minutes</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="extra-small text-muted" style="max-width: 250px;">
                                <span title="Monday">M: {{ number_format($slot->mon_amount, 0) }}</span> |
                                <span title="Tuesday">T: {{ number_format($slot->tue_amount, 0) }}</span> |
                                <span title="Wednesday">W: {{ number_format($slot->wed_amount, 0) }}</span> |
                                <span title="Thursday">Th: {{ number_format($slot->thu_amount, 0) }}</span> |
                                <span title="Friday">F: {{ number_format($slot->fri_amount, 0) }}</span> |
                                <span title="Saturday">Sa: {{ number_format($slot->sat_amount, 0) }}</span> |
                                <span title="Sunday">Su: {{ number_format($slot->sun_amount, 0) }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($slot->is_active)
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fw-medium" style="font-size: 0.65rem;">Active</span>
                            @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-medium" style="font-size: 0.65rem;">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editSlot({{ $slot->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteSlot({{ $slot->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-5 text-center text-muted">
                            <svg class="mx-auto mb-3 text-light" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <p class="small mb-0">No slots found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $slots->links() }}
    </div>

    <!-- Slot Modal -->
    <div class="modal fade" id="slotModal" tabindex="-1" aria-labelledby="slotModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form wire:submit="saveSlot" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden text-dark">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0" id="slotModalLabel">
                        {{ $editingSlot ? 'Edit Slot' : 'Add New Slot' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Location</label>
                            <select wire:model.live="location_id" class="form-select rounded-3">
                                <option value="">Choose a location...</option>
                                @foreach($this->locations() as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                @endforeach
                            </select>
                            @error('location_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Turf</label>
                            <select wire:model="turf_id" class="form-select rounded-3" @disabled(!$location_id)>
                                <option value="">Choose a turf...</option>
                                @foreach($this->turfs() as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('turf_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Category</label>
                            <select wire:model="slot_category_id" class="form-select rounded-3">
                                <option value="">Choose a category...</option>
                                @foreach($this->categories() as $c)
                                    <option value="{{ $c->id }}">{{ $c->category_name }}</option>
                                @endforeach
                            </select>
                            @error('slot_category_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">From Time</label>
                            <input wire:model="from" type="time" class="form-control rounded-3">
                            @error('from') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">To Time</label>
                            <input wire:model="to" type="time" class="form-control rounded-3">
                            @error('to') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold text-muted">Duration (Minutes)</label>
                            <input wire:model="minutes" type="number" class="form-control rounded-3" placeholder="60">
                            @error('minutes') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="fw-bold small text-muted mb-3 mt-2 border-bottom pb-1">Daily Amounts</div>
                    
                    <div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Monday</label>
                            <input wire:model="mon_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('mon_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Tuesday</label>
                            <input wire:model="tue_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('tue_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Wednesday</label>
                            <input wire:model="wed_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('wed_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Thursday</label>
                            <input wire:model="thu_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('thu_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Friday</label>
                            <input wire:model="fri_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('fri_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Saturday</label>
                            <input wire:model="sat_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('sat_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Sunday</label>
                            <input wire:model="sun_amount" type="number" step="0.01" class="form-control form-control-sm rounded-2">
                            @error('sun_amount') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                        <div class="col">
                            <label class="form-label extra-small fw-semibold text-muted mb-1">Is Active?</label>
                            <select wire:model="is_active" class="form-select form-select-sm rounded-2">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                            @error('is_active') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold">
                        {{ $editingSlot ? 'Update Slot' : 'Create Slot' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modalElement = document.getElementById('slotModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        });

        $wire.on('close-modal', () => {
            const modalElement = document.getElementById('slotModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        });
    </script>
    @endscript
</div>
