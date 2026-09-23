<?php

use App\Models\Location;
use App\Models\Turf;
use App\Models\Slot;
use App\Models\BlockBooking;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Form state
    public $showForm = false;
    public $block_id = null;
    public $location_id = '';
    public $turf_id = '';
    public $date = '';
    public $reason = '';
    public $selected_slots = [];

    protected $listeners = ['refresh' => '$refresh'];

    public function mount() {
        $this->date = Carbon::today()->toDateString();
    }

    public function resetForm() {
        $this->reset(['block_id', 'location_id', 'turf_id', 'reason', 'selected_slots', 'showForm']);
        $this->date = Carbon::today()->toDateString();
    }

    public function updatedLocationId() {
        $this->turf_id = '';
        $this->selected_slots = [];
    }

    public function updatedTurfId() {
        $this->selected_slots = [];
    }

    public function toggleSlot($slotId) {
        if (in_array($slotId, $this->selected_slots)) {
            $this->selected_slots = array_diff($this->selected_slots, [$slotId]);
        } else {
            $this->selected_slots[] = $slotId;
        }
    }

    public function edit($id) {
        $block = BlockBooking::with('slots')->findOrFail($id);
        $this->block_id = $block->id;
        $this->location_id = $block->location_id;
        $this->turf_id = $block->turf_id;
        $this->date = $block->date;
        $this->reason = $block->reason;
        $this->selected_slots = $block->slots->pluck('id')->toArray();
        $this->showForm = true;
    }

    public function save() {
        $this->validate([
            'location_id' => 'required',
            'turf_id' => 'required',
            'date' => 'required|date',
            'selected_slots' => 'required|array|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () {
            $block = BlockBooking::updateOrCreate(
                ['id' => $this->block_id],
                [
                    'location_id' => $this->location_id,
                    'turf_id' => $this->turf_id,
                    'date' => $this->date,
                    'reason' => $this->reason,
                ]
            );

            $block->slots()->sync($this->selected_slots);
        });

        session()->flash('message', 'Blocked slots saved successfully.');
        $this->resetForm();
    }

    public function delete($id) {
        BlockBooking::findOrFail($id)->delete();
        session()->flash('message', 'Block booking removed.');
    }

    public function locations() {
        return Location::orderBy('name')->get();
    }

    public function turfs() {
        return Turf::when($this->location_id, fn($q) => $q->where('location_id', $this->location_id))
            ->orderBy('name')
            ->get();
    }

    public function availableSlots() {
        if (!$this->turf_id) return collect();

        return Slot::where('turf_id', $this->turf_id)
            ->where('is_active', true)
            ->with('slotCategory')
            ->get()
            ->groupBy(function($slot) {
                return $slot->slotCategory->category_name ?? 'Other';
            })
            ->map(function($categorySlots) {
                return $categorySlots->map(function($slot) {
                    return [
                        'id' => $slot->id,
                        'time' => Carbon::parse($slot->from)->format('g:i A') . ' - ' . Carbon::parse($slot->to)->format('g:i A'),
                    ];
                });
            });
    }

    public function with() {
        return [
            'blocks' => BlockBooking::with(['location', 'turf', 'slots'])
                ->orderBy('date', 'desc')
                ->paginate(10),
        ];
    }
}; ?>

<div class="text-dark">
    @if (session()->has('message'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center justify-content-between">
            <span>{{ session('message') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="h4 fw-bold mb-1">Block Bookings</h3>
            <p class="text-muted small mb-0">Manage restricted slots for maintenance or events.</p>
        </div>
        @if(!$showForm)
            <button wire:click="$set('showForm', true)" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                <svg style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Block
            </button>
        @else
            <button wire:click="resetForm" class="btn btn-light rounded-3 px-4 fw-bold shadow-sm border">
                Cancel
            </button>
        @endif
    </div>

    @if($showForm)
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 transition-all">
                    <div class="card-header bg-white border-bottom p-4">
                        <h4 class="h6 fw-bold mb-0">Block Details</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Date</label>
                            <input wire:model.live="date" type="date" class="form-control rounded-3">
                            @error('date') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Location</label>
                            <select wire:model.live="location_id" class="form-select rounded-3">
                                <option value="">Select Location...</option>
                                @foreach($this->locations() as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                            @error('location_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Turf</label>
                            <select wire:model.live="turf_id" class="form-select rounded-3" @disabled(!$location_id)>
                                <option value="">Select Turf...</option>
                                @foreach($this->turfs() as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('turf_id') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Reason</label>
                            <textarea wire:model="reason" class="form-control rounded-3" rows="3" placeholder="Maintenance, Rain, Private Event..."></textarea>
                            @error('reason') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        @if(count($selected_slots) > 0)
                            <button wire:click="save" wire:loading.attr="disabled" class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm mt-3 d-flex align-items-center justify-content-center gap-2">
                                <span wire:loading.remove>Save Block Booking</span>
                                <span wire:loading class="spinner-border spinner-border-sm"></span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <h4 class="h6 fw-bold mb-0">Select Slots to Block</h4>
                            @if(count($selected_slots) > 0)
                                <span class="badge rounded-pill bg-danger px-3">{{ count($selected_slots) }} Selected</span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @if(!$turf_id)
                            <div class="text-center py-5">
                                <p class="text-muted small">Please select location and turf first.</p>
                            </div>
                        @else
                            @foreach($this->availableSlots() as $categoryName => $slots)
                                <div class="mb-4">
                                    <h5 class="extra-small fw-bold text-uppercase text-muted mb-3 d-flex align-items-center gap-2">
                                        {{ $categoryName }}
                                        <span class="flex-grow-1 border-bottom border-light"></span>
                                    </h5>
                                    <div class="row row-cols-2 row-cols-md-3 g-3">
                                        @foreach($slots as $slot)
                                            <div class="col">
                                                <div wire:click="toggleSlot({{ $slot['id'] }})" 
                                                    class="border rounded-4 p-3 d-flex flex-column gap-1 cursor-pointer transition-all position-relative overflow-hidden"
                                                    style="{{ in_array($slot['id'], $this->selected_slots) ? 'border-color: #ef4444 !important; background: #fef2f2;' : 'background: #fefefe;' }}">
                                                    
                                                    @if(in_array($slot['id'], $this->selected_slots))
                                                        <div class="position-absolute top-0 end-0 p-2">
                                                            <svg style="color: #ef4444; width: 1rem; height: 1rem;" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                            </svg>
                                                        </div>
                                                    @endif
                                                    <span class="small fw-bold text-dark">{{ $slot['time'] }}</span>
                                                    <span class="extra-small text-muted">Select to Block</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            @error('selected_slots') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 border-0 small fw-bold text-muted text-uppercase">Date</th>
                            <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Location / Turf</th>
                            <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Slots</th>
                            <th class="py-3 border-0 small fw-bold text-muted text-uppercase">Reason</th>
                            <th class="pe-4 py-3 border-0 small fw-bold text-muted text-uppercase text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        @forelse($blocks as $block)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold">{{ Carbon::parse($block->date)->format('M d, Y') }}</div>
                                    <div class="extra-small text-muted">{{ Carbon::parse($block->date)->format('l') }}</div>
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $block->location->name }}</div>
                                    <div class="extra-small text-success">{{ $block->turf->name }}</div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                                        {{ $block->slots->count() }} Slots Blocked
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $block->reason ?: 'No reason provided' }}</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <button wire:click="edit({{ $block->id }})" class="btn btn-white btn-sm px-3 py-2 border">
                                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </button>
                                        <button wire:click="delete({{ $block->id }})" wire:confirm="Are you sure you want to remove this block?" class="btn btn-white btn-sm px-3 py-2 border text-danger">
                                            <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center">
                                    <div class="text-muted">No block bookings found.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($blocks->hasPages())
                <div class="card-footer bg-white p-4">
                    {{ $blocks->links() }}
                </div>
            @endif
        </div>
    @endif

    <style>
        .extra-small { font-size: 0.7rem; }
        .cursor-pointer { cursor: pointer; }
        .transition-all { transition: all 0.2s ease-in-out; }
        .btn-white { background: #fff; color: #374151; }
        .btn-white:hover { background: #f9fafb; }
    </style>
</div>
