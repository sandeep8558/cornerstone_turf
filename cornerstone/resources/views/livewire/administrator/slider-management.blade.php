<?php

use App\Models\Slider;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    protected $paginationTheme = 'bootstrap';

    public $editingSlider = null;
    public $title = '';
    public $image; // Uploaded file
    public $currentImage = ''; // For editing display
    public $sort_order = 0;
    public $is_active = true;

    public function updatingSearch() {
        $this->resetPage();
    }

    public function sliders() {
        return Slider::query()
            ->when($this->search, function($query) {
                $query->where('title', 'like', '%' . $this->search . '%');
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function addSlider() {
        $this->reset(['title', 'image', 'currentImage', 'editingSlider', 'sort_order', 'is_active']);
        $this->is_active = true;
        $this->sort_order = 0;
        $this->dispatch('open-modal', 'slider-modal');
    }

    public function editSlider(Slider $slider) {
        $this->editingSlider = $slider;
        $this->title = $slider->title;
        $this->currentImage = $slider->image;
        $this->image = null;
        $this->sort_order = $slider->sort_order;
        $this->is_active = $slider->is_active;
        $this->dispatch('open-modal', 'slider-modal');
    }

    public function saveSlider() {
        $rules = [
            'title' => ['nullable', 'string', 'max:255'],
            'image' => [$this->editingSlider ? 'nullable' : 'required', 'image', 'max:2048'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ];

        $validated = $this->validate($rules);

        $path = $this->currentImage;
        if ($this->image) {
            $filename = time() . '_' . $this->image->getClientOriginalName();
            $this->image->storeAs('sliders', $filename, 'public_folder');
            $path = 'sliders/' . $filename;
        }

        if ($this->editingSlider) {
            $this->editingSlider->update([
                'title' => $this->title,
                'image' => $path,
                'sort_order' => $this->sort_order ?: 0,
                'is_active' => $this->is_active,
            ]);
            session()->flash('message', 'Slider updated successfully.');
        } else {
            Slider::create([
                'title' => $this->title,
                'image' => $path,
                'sort_order' => $this->sort_order ?: 0,
                'is_active' => $this->is_active,
            ]);
            session()->flash('message', 'Slider created successfully.');
        }

        $this->dispatch('close-modal', 'slider-modal');
    }

    public function deleteSlider(Slider $slider) {
        $slider->delete();
        session()->flash('message', 'Slider deleted successfully.');
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
        $sliders = $this->sliders();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Image Sliders ({{ $sliders->total() }})</h2>
                <div class="text-muted extra-small mt-1">Recommended: <strong>1000 x 600 px</strong> (Aspect Ratio <strong>5:3</strong>)</div>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by title...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addSlider" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add Slider</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Slide Image</th>
                    <th class="px-4 py-3 text-start fw-semibold">Title</th>
                    <th class="px-4 py-3 text-start fw-semibold">Sort Order</th>
                    <th class="px-4 py-3 text-start fw-semibold">Status</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($sliders as $slider)
                    <tr wire:key="slider-{{ $slider->id }}">
                        <td class="px-4 py-3">
                            <img src="{{ asset($slider->image) }}" class="rounded shadow-sm" style="width: 100px; aspect-ratio: 5/3; object-fit: cover;" alt="Slider image">
                        </td>
                        <td class="px-4 py-3">
                            <span class="fw-bold text-dark">{{ $slider->title ?: 'No Title' }}</span>
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $slider->sort_order }}
                        </td>
                        <td class="px-4 py-3">
                            @if($slider->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">Active</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editSlider({{ $slider->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteSlider({{ $slider->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-5 text-center text-muted">
                            <p class="small mb-0">No sliders found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $sliders->links() }}
    </div>

    <!-- Slider Modal -->
    <div class="modal fade" id="sliderModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="saveSlider" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0 text-dark">{{ $editingSlider ? 'Edit Slider' : 'Add New Slider' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-dark">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Title (Optional)</label>
                        <input wire:model="title" type="text" class="form-control rounded-3" placeholder="e.g. Summer Special Sale">
                        @error('title') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Slide Image</label>
                        <input type="file" wire:model="image" class="form-control rounded-3">
                        <div class="text-muted extra-small mt-1">For best results, upload images with an aspect ratio of <strong>5:3</strong> (Recommended size: <strong>1000 x 600 px</strong>).</div>
                        @error('image') <span class="text-danger extra-small">{{ $message }}</span> @enderror

                        <div wire:loading wire:target="image" class="text-primary extra-small mt-1">Uploading preview...</div>

                        @if ($image)
                            <div class="mt-2 text-center">
                                <img src="{{ $image->temporaryUrl() }}" class="rounded shadow-sm w-100" style="max-width: 300px; aspect-ratio: 5/3; object-fit: cover;">
                            </div>
                        @elseif ($currentImage)
                            <div class="mt-2 text-center">
                                <img src="{{ asset($currentImage) }}" class="rounded shadow-sm w-100" style="max-width: 300px; aspect-ratio: 5/3; object-fit: cover;">
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold text-muted">Sort Order</label>
                            <input wire:model="sort_order" type="number" class="form-control rounded-3" placeholder="0">
                            @error('sort_order') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input wire:model="is_active" class="form-check-input" type="checkbox" id="isActiveSwitch">
                                <label class="form-check-label small fw-semibold text-muted" for="isActiveSwitch">Active status</label>
                            </div>
                            @error('is_active') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold" wire:loading.attr="disabled">
                        {{ $editingSlider ? 'Update Slider' : 'Create Slider' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('sliderModal'));
            modal.show();
        });
        $wire.on('close-modal', () => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('sliderModal'));
            if (modal) modal.hide();
        });
    </script>
    @endscript
</div>
