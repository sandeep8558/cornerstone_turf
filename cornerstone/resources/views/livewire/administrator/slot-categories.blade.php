<?php

use App\Models\SlotCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    protected $paginationTheme = 'bootstrap';
    
    public $editingCategory = null;
    
    public $category_name = '';
    public $is_active = 'Yes';

    public function updatingSearch() {
        $this->resetPage();
    }

    public function categories() {
        return SlotCategory::when($this->search, function($query) {
                $query->where('category_name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function addCategory() {
        $this->reset(['category_name', 'is_active', 'editingCategory']);
        $this->dispatch('open-modal', 'category-modal');
    }

    public function editCategory(SlotCategory $category) {
        $this->editingCategory = $category;
        $this->category_name = $category->category_name;
        $this->is_active = $category->is_active ? 'Yes' : 'No';
        $this->dispatch('open-modal', 'category-modal');
    }

    public function saveCategory() {
        $validated = $this->validate([
            'category_name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'in:Yes,No'],
        ]);

        $data = [
            'category_name' => $validated['category_name'],
            'is_active' => $validated['is_active'] === 'Yes',
        ];

        if ($this->editingCategory) {
            $this->editingCategory->update($data);
            session()->flash('message', 'Slot Category updated successfully.');
        } else {
            SlotCategory::create($data);
            session()->flash('message', 'Slot Category created successfully.');
        }

        $this->dispatch('close-modal', 'category-modal');
    }

    public function deleteCategory(SlotCategory $category) {
        $category->delete();
        session()->flash('message', 'Slot Category deleted successfully.');
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
        $categories = $this->categories();
    @endphp

    <!-- Table header -->
    <div class="card-header bg-white border-bottom border-light px-4 py-3">
        <div class="row align-items-center g-3">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold text-dark mb-0">Slot Categories ({{ $categories->total() }})</h2>
            </div>
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted px-3">
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                        </svg>
                    </span>
                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control bg-light border-start-0 ps-0" placeholder="Search by category name...">
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <button wire:click="addCategory" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2 rounded-2">
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span>Add Category</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small text-dark">
            <thead class="table-light text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                <tr>
                    <th class="px-4 py-3 text-start fw-semibold">Category Name</th>
                    <th class="px-4 py-3 text-start fw-semibold">Status</th>
                    <th class="px-4 py-3 text-start fw-semibold">Created At</th>
                    <th class="px-4 py-3 text-end fw-semibold text-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="px-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width: 2.25rem; height: 2.25rem; font-size: 0.85rem;">
                                    {{ strtoupper(substr($category->category_name, 0, 1)) }}
                                </div>
                                <span class="fw-bold text-dark">{{ $category->category_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if($category->is_active)
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fw-medium" style="font-size: 0.65rem;">Active</span>
                            @else
                                <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-medium" style="font-size: 0.65rem;">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $category->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button wire:click="editCategory({{ $category->id }})" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Edit</button>
                                <button onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" wire:click="deleteCategory({{ $category->id }})" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1" style="font-size: 0.75rem;">Remove</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-5 text-center text-muted">
                            <svg class="mx-auto mb-3 text-light" style="width: 2.5rem; height: 2.5rem;" fill="none" stroke="currentColor" stroke-width="1.25" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <p class="small mb-0">No categories found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top px-4 py-3">
        {{ $categories->links() }}
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <form wire:submit="saveCategory" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden text-dark">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h5 class="modal-title h6 fw-bold mb-0" id="categoryModalLabel">
                        {{ $editingCategory ? 'Edit Category: ' . $editingCategory->category_name : 'Add New Category' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Category Name</label>
                        <input wire:model="category_name" type="text" class="form-control rounded-3" placeholder="e.g. Morning, Evening, Special">
                        @error('category_name') <span class="text-danger extra-small">{{ $message }}</span> @enderror
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
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold">
                        {{ $editingCategory ? 'Update Category' : 'Create Category' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-modal', () => {
            const modalElement = document.getElementById('categoryModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        });

        $wire.on('close-modal', () => {
            const modalElement = document.getElementById('categoryModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        });
    </script>
    @endscript
</div>
