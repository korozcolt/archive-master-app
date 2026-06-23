<?php

namespace App\Services;

use App\Enums\ArchivePhase;
use App\Enums\DocumentAccessLevel;
use App\Enums\Priority;
use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentaryType;
use App\Models\PhysicalLocation;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HistoricalDocumentIntakeService
{
    public function __construct(
        private readonly DocumentFileService $documentFileService,
        private readonly SlaCalculatorService $slaCalculatorService,
        private readonly ArchiveClassificationService $archiveClassificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $legacyFiles
     * @return Collection<int, Document>
     */
    public function store(User $user, array $data, array $legacyFiles = []): Collection
    {
        return DB::transaction(function () use ($user, $data, $legacyFiles): Collection {
            $category = Category::query()
                ->where('company_id', $user->company_id)
                ->findOrFail((int) $data['category_id']);

            $producerDepartment = Department::query()
                ->where('company_id', $user->company_id)
                ->findOrFail((int) $data['original_department_id']);

            $location = $this->resolveBoxLocation($user, (int) $data['physical_location_id']);
            $centralArchiveDepartment = $this->resolveCentralArchiveDepartment($user);
            $archivedStatus = Status::query()
                ->where('company_id', $user->company_id)
                ->where('slug', 'archivado')
                ->first();

            $rows = $this->normalizeRows($data, $legacyFiles);
            $createdDocuments = collect();

            foreach ($rows as $row) {
                $documentaryType = $this->resolveDocumentaryType($user, $producerDepartment, (int) $row['documentary_type_id']);
                $this->ensureUniqueHistoricalRow($user, $producerDepartment, $location, $documentaryType, $row);

                $filePath = $this->documentFileService->storeUploadedFile($row['file']);
                $accessLevel = $row['access_level'] ?? $data['access_level'] ?? $documentaryType->access_level_default?->value ?? DocumentAccessLevel::Interno->value;

                $document = Document::create([
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                    'department_id' => $centralArchiveDepartment->id,
                    'created_by' => $user->id,
                    'assigned_to' => null,
                    'title' => $this->makeTitle($row),
                    'description' => $row['description'] ?? $data['description'] ?? null,
                    'category_id' => $category->id,
                    'status_id' => $archivedStatus?->id,
                    'is_confidential' => $accessLevel !== DocumentAccessLevel::Publico->value,
                    'priority' => Priority::Medium->value,
                    'file_path' => $filePath,
                    'digital_document_type' => $data['digital_document_type'] ?? 'copia',
                    'physical_document_type' => $data['physical_document_type'] ?? 'original',
                    'is_archived' => true,
                    'archived_at' => now(),
                    'archive_phase' => ArchivePhase::Central->value,
                    'access_level' => $accessLevel,
                    'trd_series_id' => $documentaryType->subseries?->series?->id,
                    'trd_subseries_id' => $documentaryType->documentary_subseries_id,
                    'documentary_type_id' => $documentaryType->id,
                    'metadata' => $this->metadata($user, $centralArchiveDepartment, $producerDepartment, $location, $row, $data),
                ]);

                $this->archiveClassificationService->applyToDocument($document);
                $document->save();

                if (! $document->moveToLocation($location, $row['archive_note'] ?? 'Carga historica por caja.', $user, 'stored')) {
                    throw ValidationException::withMessages([
                        'physical_location_id' => 'La caja seleccionada no tiene capacidad disponible.',
                    ]);
                }

                $this->slaCalculatorService->freeze($document, 'historical_upload');
                $document->saveQuietly();
                $createdDocuments->push($document->refresh());
            }

            $this->rememberHistoricalBox($user, $location);

            return $createdDocuments;
        });
    }

    private function resolveBoxLocation(User $user, int $locationId): PhysicalLocation
    {
        $location = PhysicalLocation::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->find($locationId);

        if (! $location) {
            throw ValidationException::withMessages([
                'physical_location_id' => 'La caja seleccionada no es valida para la empresa.',
            ]);
        }

        if (! filled(data_get($location->structured_data, 'caja'))) {
            throw ValidationException::withMessages([
                'physical_location_id' => 'Selecciona una caja como ubicacion final, no solo un estante o entrepano.',
            ]);
        }

        if ($location->isFull()) {
            throw ValidationException::withMessages([
                'physical_location_id' => 'La caja seleccionada esta llena.',
            ]);
        }

        return $location;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function ensureUniqueHistoricalRow(User $user, Department $producerDepartment, PhysicalLocation $location, DocumentaryType $documentaryType, array $row): void
    {
        $title = $this->makeTitle($row);
        $duplicateExists = Document::query()
            ->where('company_id', $user->company_id)
            ->where('created_by', $user->id)
            ->where('title', $title)
            ->where('documentary_type_id', $documentaryType->id)
            ->where('physical_location_id', $location->id)
            ->where('metadata->entry_mode', 'historical')
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'rows' => "Ya existe un documento histórico con la misma referencia en esta caja: {$title}.",
            ]);
        }
    }

    private function resolveDocumentaryType(User $user, Department $producerDepartment, int $documentaryTypeId): DocumentaryType
    {
        $documentaryType = DocumentaryType::query()
            ->with('subseries.series')
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where(function ($query) use ($producerDepartment): void {
                $query->whereNull('department_id')
                    ->orWhere('department_id', $producerDepartment->id);
            })
            ->find($documentaryTypeId);

        if (! $documentaryType || ! $documentaryType->subseries || ! $documentaryType->subseries->series) {
            throw ValidationException::withMessages([
                'documentary_type_id' => 'El tipo documental seleccionado no tiene serie y subserie validas.',
            ]);
        }

        return $documentaryType;
    }

    private function resolveCentralArchiveDepartment(User $user): Department
    {
        return Department::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query): void {
                $query->where('code', 'AC170')
                    ->orWhere('name->es', 'like', '%Archivo Central%')
                    ->orWhere('name', 'like', '%Archivo Central%');
            })
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $legacyFiles
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $data, array $legacyFiles): array
    {
        $rows = collect($data['rows'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && ($row['file'] ?? null) instanceof UploadedFile)
            ->map(function (array $row) use ($data): array {
                $row['documentary_type_id'] ??= $data['documentary_type_id'] ?? null;

                return $row;
            })
            ->values()
            ->all();

        if ($rows !== []) {
            return $rows;
        }

        return collect($legacyFiles)
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): array => [
                'file' => $file,
                'documentary_type_id' => $data['documentary_type_id'] ?? null,
                'folder' => $data['folder'] ?? null,
                'volume' => $data['volume'] ?? null,
                'reference_code' => $data['reference_code'] ?? null,
                'description' => $data['description'] ?? null,
                'date_start' => $data['date_start'] ?? null,
                'date_end' => $data['date_end'] ?? null,
                'year' => $data['year'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function makeTitle(array $row): string
    {
        $reference = trim((string) ($row['reference_code'] ?? ''));

        if ($reference !== '') {
            return $reference;
        }

        $file = $row['file'];

        return pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $file->getClientOriginalName();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metadata(User $user, Department $centralArchiveDepartment, Department $producerDepartment, PhysicalLocation $location, array $row, array $data): array
    {
        $file = $row['file'];
        $box = data_get($location->structured_data, 'caja');

        return [
            'entry_mode' => 'historical',
            'historical' => array_filter([
                'workflow' => 'historical_upload',
                'custody' => 'archivo_central',
                'visibility_scope' => 'company_wide_internal',
                'custody_department_id' => $centralArchiveDepartment->id,
                'custody_department_name' => $this->translatedDepartmentName($centralArchiveDepartment),
                'original_department_id' => $producerDepartment->id,
                'original_department_name' => $this->translatedDepartmentName($producerDepartment),
                'shelf' => data_get($location->structured_data, 'estante'),
                'bay' => data_get($location->structured_data, 'entrepaño') ?? data_get($location->structured_data, 'entrepano'),
                'box' => $box ? 'Caja '.$box : ($row['box'] ?? null),
                'box_location_id' => $location->id,
                'box_location_code' => $location->code,
                'box_location_path' => $location->full_path,
                'folder' => $row['folder'] ?? null,
                'volume' => $row['volume'] ?? null,
                'reference_code' => $row['reference_code'] ?? null,
                'year' => $row['year'] ?? null,
                'date_start' => $row['date_start'] ?? $data['date_start'] ?? null,
                'date_end' => $row['date_end'] ?? $data['date_end'] ?? null,
                'keywords_text' => $row['keywords'] ?? $data['keywords'] ?? null,
                'uploaded_to_central_archive_by' => $user->name,
                'uploaded_to_central_archive_at' => now()->toISOString(),
                'digital_document_type' => $data['digital_document_type'] ?? 'copia',
                'physical_document_type' => $data['physical_document_type'] ?? 'original',
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    private function translatedDepartmentName(Department $department): string
    {
        $name = data_get($department, 'name');

        if (is_array($name)) {
            return (string) ($name[app()->getLocale()] ?? $name['es'] ?? $name['en'] ?? reset($name) ?: $department->code);
        }

        if (is_string($name) && str_starts_with($name, '{')) {
            $decoded = json_decode($name, true);

            if (is_array($decoded)) {
                return (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?: $department->code);
            }
        }

        return (string) ($name ?: $department->code);
    }

    private function rememberHistoricalBox(User $user, PhysicalLocation $location): void
    {
        $settings = (array) ($user->settings ?? []);

        data_set($settings, 'historical_upload.last_physical_location_id', $location->id);
        data_set($settings, 'historical_upload.last_physical_location_code', $location->code);
        data_set($settings, 'historical_upload.last_physical_location_path', $location->full_path);

        $user->forceFill(['settings' => $settings])->saveQuietly();
    }
}
