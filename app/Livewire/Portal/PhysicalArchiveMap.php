<?php

namespace App\Livewire\Portal;

use App\Models\Document;
use App\Models\PhysicalLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PhysicalArchiveMap extends Component
{
    use WithPagination;

    public string $selectedShelf = '01';

    public ?int $selectedLocationId = null;

    public string $searchQuery = '';

    // Reset pagination when searching or changing shelf/location
    public function updatingSearchQuery(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedShelf(): void
    {
        $this->selectedLocationId = null;
        $this->searchQuery = '';
        $this->resetPage();
    }

    public function selectLocation(?int $locationId): void
    {
        $this->selectedLocationId = $locationId;
        $this->searchQuery = '';
        $this->resetPage();
    }

    public function previousShelf(): void
    {
        $current = (int) $this->selectedShelf;
        if ($current > 1) {
            $this->selectedShelf = sprintf('%02d', $current - 1);
            $this->selectedLocationId = null;
            $this->searchQuery = '';
            $this->resetPage();
        }
    }

    public function nextShelf(): void
    {
        $current = (int) $this->selectedShelf;
        if ($current < 40) {
            $this->selectedShelf = sprintf('%02d', $current + 1);
            $this->selectedLocationId = null;
            $this->searchQuery = '';
            $this->resetPage();
        }
    }

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole([
            \App\Enums\Role::ArchiveManager->value,
            \App\Enums\Role::ArchiveOperator->value,
            \App\Enums\Role::Admin->value,
            \App\Enums\Role::SuperAdmin->value,
            \App\Enums\Role::BranchAdmin->value,
        ])) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        // 1. Generate Shelves List (01 to 40)
        $shelvesList = array_map(fn ($i) => sprintf('%02d', $i), range(1, 40));

        // 2. Fetch all locations for the selected shelf
        $locations = PhysicalLocation::where('company_id', $user->company_id)
            ->where('structured_data->estante', $this->selectedShelf)
            ->withCount('documents')
            ->get();

        // Count documents per shelf to show in the selector dropdown
        $shelfCounts = PhysicalLocation::where('company_id', $user->company_id)
            ->withCount('documents')
            ->get()
            ->groupBy(fn ($loc) => $loc->structured_data['estante'] ?? null)
            ->map(fn ($group) => $group->sum('documents_count'));

        // 3. Build 2D Grid: Entrepaños (rows) and Cajas (columns)
        $entrepanos = ['01', '02', '03', '04', '05', '06'];
        $cajas = ['001', '002', '003', '004', '005', '006', '007', '008'];

        $grid = [];
        foreach ($entrepanos as $entrepano) {
            $grid[$entrepano] = [];
            foreach ($cajas as $caja) {
                $location = $locations->first(function ($loc) use ($entrepano, $caja) {
                    return ($loc->structured_data['entrepaño'] ?? null) === $entrepano
                        && ($loc->structured_data['caja'] ?? null) === $caja;
                });
                $grid[$entrepano][$caja] = $location;
            }
        }

        // 4. Fetch details of the selected box and its documents (paginated)
        $selectedLocation = null;
        $documents = null;

        if ($this->selectedLocationId) {
            $selectedLocation = PhysicalLocation::where('company_id', $user->company_id)
                ->withCount('documents')
                ->find($this->selectedLocationId);

            if ($selectedLocation) {
                $documentsQuery = Document::where('physical_location_id', $this->selectedLocationId)
                    ->where('company_id', $user->company_id)
                    ->visibleToPortalUser($user);

                if (! empty(trim($this->searchQuery))) {
                    $search = trim($this->searchQuery);
                    $documentsQuery->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }

                $documents = $documentsQuery->latest()->paginate(10);
            }
        }

        return view('livewire.portal.physical-archive-map', [
            'shelvesList' => $shelvesList,
            'grid' => $grid,
            'entrepanos' => $entrepanos,
            'cajas' => $cajas,
            'selectedLocation' => $selectedLocation,
            'documents' => $documents,
            'shelfCounts' => $shelfCounts,
        ])->layout('layouts.app');
    }
}
