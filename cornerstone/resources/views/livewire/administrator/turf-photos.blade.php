<?php

use App\Models\Turf;
use App\Models\TurfPhoto;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\File;

new class extends Component {
    use WithPagination, WithFileUploads;

    public $search = '';
    protected $paginationTheme = 'bootstrap';
    
    public $editingPhoto = null;
    public $turf_id = '';
    public $photo; // Uploaded file
    public $currentPhoto = ''; // For editing display

    public function updatingSearch() {
        $this->resetPage();
    }

    public function photos() {
        return TurfPhoto::with('turf')
            ->when($this->search, function($query) {
                $query->whereHas('turf', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function turfs() {
        return Turf::orderBy('name')->get();
    }

    public function addPhoto() {
        $this->reset(['turf_id', 'photo', 'currentPhoto', 'editingPhoto']);
        $this->dispatch('open-modal', 'photo-modal');
    }

    public function editPhoto(TurfPhoto $photo) {
        $this->editingPhoto = $photo;
        $this->turf_id = $photo->turf_id;
        $this->currentPhoto = $photo->photo;
        $this->photo = null;
        $this->dispatch('open-modal', 'photo-modal');
    }

    public function savePhoto() {
        $rules = [
            'turf_id' => ['required', 'exists:turfs,id'],
            'photo' => [$this->editingPhoto ? 'nullable' : 'required', 'image', 'max:2048'],
        ];
        
        $validated = $this->validate($rules);

        $path = $this->currentPhoto;
        if ($this->photo) {
            $filename = time() . '_' . $this->photo->getClientOriginalName();
            $this->photo->storeAs('turfs', $filename, 'public_folder');
            $path = 'turfs/' . $filename;
            
            // Delete old photo if editing
            if ($this->editingPhoto && $this->editingPhoto->photo && File::exists(public_path($this->editingPhoto->photo))) {
                File::delete(public_path($this->editingPhoto->photo));
            }
        }

        if ($this->editingPhoto) {
            $this->editingPhoto->update([
                'turf_id' => $this->turf_id,
                'photo' => $path,
            ]);
            session()->flash('message', 'Photo updated successfully.');
        } else {
            TurfPhoto::create([
                'turf_id' => $this->turf_id,
                'photo' => $path,
            ]);
            session()->flash('message', 'Photo uploaded successfully.');
        }

        $this->dispatch('close-modal', 'photo-modal');
    }

    public function deletePhoto(TurfPhoto $photo) {
        if (File::exists(public_path($photo->photo))) {
            File::delete(public_path($photo->photo));
        }
        $photo->delete();
        session()->flash('message', 'Photo deleted successfully.');
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
        $photos = $this->photos();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Turf Photos ({{ $photos->total() }})</h2>
                <div class="text-muted extra-small mt-1">Recommended: <strong>800 x 600 px</strong> (Aspect Ratio <strong>4:3</strong>)</div>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by turf name...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addPhoto" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Upload Photo</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Gallery/Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Photo</th>
                    <th class="px-4 py-3 text-start fw-semibold">Turf</th>
                    <th class="px-4 py-3 text-start fw-semibold">Uploaded</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($photos as $p)
                    <tr wire:key="photo-{{ $p->id }}">
                        <td class="px-4 py-3">
                            <img src="{{ asset($p->photo) }}" class="rounded shadow-sm" style="width: 60px; height: 40px; object-fit: cover;" alt="Turf photo">
                        </td>
                        <td class="px-4 py-3">
                            <span class="fw-bold">{{ $p->turf->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $p->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editPhoto({{ $p->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deletePhoto({{ $p->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-5 text-center text-muted">
                            <p class="small mb-0">No photos found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $photos->links() }}
    </div>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="savePhoto" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0">{{ $editingPhoto ? 'Edit Photo' : 'Upload Turf Photo' }}</h5>
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
                        <label class="form-label small fw-semibold text-muted">Photo</label>
                        <input type="file" wire:model="photo" class="form-control rounded-3">
                        <div class="text-muted extra-small mt-1">For best results, upload images with an aspect ratio of <strong>4:3</strong> (Recommended size: <strong>800 x 600 px</strong> or <strong>1000 x 750 px</strong>).</div>
                        @error('photo') <span class="text-danger extra-small">{{ $message }}</span> @enderror
                        
                        <div wire:loading wire:target="photo" class="text-primary extra-small mt-1">Uploading preview...</div>
                        
                        @if ($photo)
                            <div class="mt-2 text-center">
                                <img src="{{ $photo->temporaryUrl() }}" class="rounded shadow-sm mw-100" style="max-height: 200px;">
                            </div>
                        @elseif ($currentPhoto)
                            <div class="mt-2 text-center">
                                <img src="{{ asset($currentPhoto) }}" class="rounded shadow-sm mw-100" style="max-height: 200px;">
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer bg-light border-top px-4 py-3">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary small fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold" wire:loading.attr="disabled">
                        {{ $editingPhoto ? 'Update Photo' : 'Upload Photo' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('photoModal'));
            modal.show();
        });
        $wire.on('close-modal', () => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('photoModal'));
            if (modal) modal.hide();
        });
    </script>
    @endscript
</div>
