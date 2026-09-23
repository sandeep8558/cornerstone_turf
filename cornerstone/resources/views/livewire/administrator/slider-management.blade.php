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
            ->paginate(12);
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

    <!-- Card Grid -->
    <div class="p-4 bg-light bg-opacity-50">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-3 g-4">
            @forelse($sliders as $slider)
                <div class="col" wire:key="slider-{{ $slider->id }}">
                    <div class="card h-100 border border-light-subtle shadow-sm rounded-4 overflow-hidden bg-white slider-card">
                        <!-- Image Container with 5:3 Aspect Ratio and Badges -->
                        <div class="position-relative" style="aspect-ratio: 5/3; background-color: #f1f5f9; overflow: hidden;">
                            <img src="{{ asset($slider->image) }}" 
                                 class="w-100 h-100 object-fit-cover" 
                                 alt="{{ $slider->title ?: 'Slider image' }}">
                            
                            <!-- Badges Top Overlay -->
                            <div class="position-absolute top-0 start-0 end-0 p-3 d-flex justify-content-between align-items-center">
                                <span class="badge bg-dark bg-opacity-75 text-white rounded-pill px-2.5 py-1 extra-small fw-semibold shadow-sm">
                                    Order: #{{ $slider->sort_order }}
                                </span>
                                @if($slider->is_active)
                                    <span class="badge bg-success text-white rounded-pill px-2.5 py-1 extra-small fw-semibold shadow-sm">
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1 extra-small fw-semibold shadow-sm">
                                        Inactive
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h6 fw-bold text-dark mb-1 text-truncate" title="{{ $slider->title }}">
                                    {{ $slider->title ?: 'No Title' }}
                                </h3>
                                <div class="text-muted extra-small d-flex align-items-center gap-1">
                                    <svg style="width: 0.8rem; height: 0.8rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                    </svg>
                                    <span>Added {{ $slider->created_at ? $slider->created_at->diffForHumans() : 'Recently' }}</span>
                                </div>
                            </div>

                            <hr class="my-3 opacity-25">

                            <!-- Action Buttons -->
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <button wire:click="editSlider({{ $slider->id }})" 
                                        class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1" 
                                        style="font-size: 0.8rem;">
                                    <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                                    </svg>
                                    <span>Edit</span>
                                </button>
                                <button onclick="confirm('Are you sure you want to delete this slider?') || event.stopImmediatePropagation()" 
                                        wire:click="deleteSlider({{ $slider->id }})" 
                                        class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1 flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1" 
                                        style="font-size: 0.8rem;">
                                    <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                    <span>Remove</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 py-5 text-center text-muted w-100">
                    <div class="py-4">
                        <svg style="width: 3rem; height: 3rem;" class="text-muted opacity-50 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                        </svg>
                        <p class="small mb-2 fw-semibold">No sliders found</p>
                        <p class="extra-small text-muted mb-3">Add image sliders to showcase promotions and banners on the mobile app home screen.</p>
                        <button wire:click="addSlider" class="btn btn-primary btn-sm rounded-pill px-4">Add First Slider</button>
                    </div>
                </div>
            @endforelse
        </div>
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

    <style>
        .slider-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .slider-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.08) !important;
        }
    </style>
</div>
