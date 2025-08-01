<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Http\Requests\Api\StoreDocumentRequest;
use App\Http\Requests\Api\UpdateDocumentRequest;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Documents",
 *     description="API Endpoints for document management"
 * )
 */
class DocumentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/documents",
     *     summary="Get list of documents",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Document")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Document::with(['company', 'branch', 'department', 'category', 'status', 'creator', 'assignedTo', 'tags'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('status_id')) {
            $query->where('status_id', $request->get('status_id'));
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->get('assigned_to'));
        }

        $perPage = min($request->get('per_page', 15), 100);
        $documents = $query->paginate($perPage);

        return DocumentResource::collection($documents);
    }

    /**
     * @OA\Post(
     *     path="/api/documents",
     *     summary="Create a new document",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Document Title"),
     *                 @OA\Property(property="description", type="string", example="Document description"),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Document created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Document")
     *     )
     * )
     */
    public function store(StoreDocumentRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = auth()->user()->company_id;
        $data['branch_id'] = auth()->user()->branch_id;
        $data['department_id'] = auth()->user()->department_id;
        $data['created_by'] = auth()->id();
        $data['document_number'] = $this->generateDocumentNumber();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('documents', $filename, 'public');
            
            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
            $data['mime_type'] = $file->getMimeType();
        }

        $document = Document::create($data);
        $document->load(['company', 'branch', 'department', 'category', 'status', 'creator', 'assignedTo', 'tags']);

        return new DocumentResource($document);
    }

    /**
     * @OA\Get(
     *     path="/api/documents/{id}",
     *     summary="Get document by ID",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Document")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Document not found"
     *     )
     * )
     */
    public function show(Document $document)
    {
        $this->authorize('view', $document);
        
        $document->load([
            'company', 'branch', 'department', 'category', 'status', 
            'creator', 'assignedTo', 'tags', 'versions', 'workflowHistory'
        ]);

        return new DocumentResource($document);
    }

    /**
     * @OA\Put(
     *     path="/api/documents/{id}",
     *     summary="Update document",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Title"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Document")
     *     )
     * )
     */
    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $this->authorize('update', $document);
        
        $data = $request->validated();
        
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            $file = $request->file('file');
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('documents', $filename, 'public');
            
            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
            $data['mime_type'] = $file->getMimeType();
            $data['version'] = $document->version + 1;
        }

        $document->update($data);
        $document->load(['company', 'branch', 'department', 'category', 'status', 'creator', 'assignedTo', 'tags']);

        return new DocumentResource($document);
    }

    /**
     * @OA\Delete(
     *     path="/api/documents/{id}",
     *     summary="Delete document",
     *     tags={"Documents"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Document deleted successfully"
     *     )
     * )
     */
    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);
        
        // Delete file if exists
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        
        $document->delete();
        
        return response()->noContent();
    }

    /**
     * Generate unique document number
     */
    private function generateDocumentNumber(): string
    {
        $prefix = 'DOC';
        $year = date('Y');
        $month = date('m');
        
        $lastDocument = Document::where('document_number', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderBy('document_number', 'desc')
            ->first();
        
        if ($lastDocument) {
            $lastNumber = (int) substr($lastDocument->document_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $newNumber);
    }
}