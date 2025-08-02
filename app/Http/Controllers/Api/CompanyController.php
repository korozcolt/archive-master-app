<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CompanyController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/companies/current",
     *     tags={"Companies"},
     *     summary="Obtener empresa actual",
     *     description="Obtener información de la empresa del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Información de la empresa",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Empresa ABC S.A."),
     *                 @OA\Property(property="legal_name", type="string", example="Empresa ABC Sociedad Anónima"),
     *                 @OA\Property(property="tax_id", type="string", example="12345678-9"),
     *                 @OA\Property(property="address", type="string", example="Av. Principal 123, Ciudad"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="email", type="string", example="contacto@empresa.com"),
     *                 @OA\Property(property="website", type="string", example="https://www.empresa.com"),
     *                 @OA\Property(property="logo_url", type="string", example="https://sistema.com/storage/logos/empresa.png"),
     *                 @OA\Property(property="primary_color", type="string", example="#1f2937"),
     *                 @OA\Property(property="secondary_color", type="string", example="#3b82f6"),
     *                 @OA\Property(property="active", type="boolean", example=true),
     *                 @OA\Property(property="branches_count", type="integer", example=5),
     *                 @OA\Property(property="departments_count", type="integer", example=12),
     *                 @OA\Property(property="users_count", type="integer", example=45),
     *                 @OA\Property(property="documents_count", type="integer", example=1250),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function current(): JsonResponse
    {
        $company = Company::with(['branches', 'departments'])
            ->withCount(['branches', 'departments', 'users', 'documents'])
            ->find(Auth::user()->company_id);

        if (!$company) {
            return $this->errorResponse('Empresa no encontrada', 404);
        }

        return $this->successResponse($company);
    }

    /**
     * @OA\Get(
     *     path="/api/companies/current/branches",
     *     tags={"Companies"},
     *     summary="Obtener sucursales de la empresa",
     *     description="Obtener lista de sucursales de la empresa actual",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de sucursales",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sucursal Centro"),
     *                     @OA\Property(property="address", type="string", example="Calle Principal 456"),
     *                     @OA\Property(property="phone", type="string", example="+1234567891"),
     *                     @OA\Property(property="email", type="string", example="centro@empresa.com"),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="departments_count", type="integer", example=3),
     *                     @OA\Property(property="users_count", type="integer", example=15)
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function branches(): JsonResponse
    {
        $branches = Auth::user()->company->branches()
            ->withCount(['departments', 'users'])
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse($branches);
    }

    /**
     * @OA\Get(
     *     path="/api/companies/current/departments",
     *     tags={"Companies"},
     *     summary="Obtener departamentos de la empresa",
     *     description="Obtener lista de departamentos de la empresa actual",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="Filtrar por sucursal",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de departamentos",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Recursos Humanos"),
     *                     @OA\Property(property="description", type="string", example="Departamento de gestión de personal"),
     *                     @OA\Property(property="branch_id", type="integer", example=1),
     *                     @OA\Property(property="active", type="boolean", example=true),
     *                     @OA\Property(property="branch", type="object"),
     *                     @OA\Property(property="users_count", type="integer", example=8),
     *                     @OA\Property(property="documents_count", type="integer", example=125)
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function departments(Request $request): JsonResponse
    {
        $query = Auth::user()->company->departments()
            ->with('branch')
            ->withCount(['users', 'documents'])
            ->where('active', true);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $departments = $query->orderBy('name')->get();

        return $this->successResponse($departments);
    }

    /**
     * @OA\Get(
     *     path="/api/companies/current/stats",
     *     tags={"Companies"},
     *     summary="Obtener estadísticas de la empresa",
     *     description="Obtener estadísticas generales de la empresa actual",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas de la empresa",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_documents", type="integer", example=1250),
     *                 @OA\Property(property="pending_documents", type="integer", example=85),
     *                 @OA\Property(property="completed_documents", type="integer", example=1165),
     *                 @OA\Property(property="overdue_documents", type="integer", example=12),
     *                 @OA\Property(property="total_users", type="integer", example=45),
     *                 @OA\Property(property="active_users", type="integer", example=42),
     *                 @OA\Property(property="total_branches", type="integer", example=5),
     *                 @OA\Property(property="total_departments", type="integer", example=12),
     *                 @OA\Property(property="total_categories", type="integer", example=8),
     *                 @OA\Property(
     *                     property="documents_by_status",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="status_name", type="string", example="En Proceso"),
     *                         @OA\Property(property="count", type="integer", example=45),
     *                         @OA\Property(property="percentage", type="number", format="float", example=3.6)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="documents_by_category",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="category_name", type="string", example="Contratos"),
     *                         @OA\Property(property="count", type="integer", example=250),
     *                         @OA\Property(property="percentage", type="number", format="float", example=20.0)
     *                     )
     *                 ),
     *                 @OA\Property(property="last_updated", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $company = Auth::user()->company;

        // Estadísticas básicas
        $totalDocuments = $company->documents()->count();
        $pendingDocuments = $company->documents()
            ->whereHas('status', fn($q) => $q->where('is_final', false))
            ->count();
        $completedDocuments = $company->documents()
            ->whereHas('status', fn($q) => $q->where('is_final', true))
            ->count();
        $overdueDocuments = $company->documents()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', fn($q) => $q->where('is_final', false))
            ->count();

        // Documentos por estado
        $documentsByStatus = $company->documents()
            ->join('statuses', 'documents.status_id', '=', 'statuses.id')
            ->selectRaw('statuses.name as status_name, COUNT(*) as count')
            ->groupBy('statuses.id', 'statuses.name')
            ->get()
            ->map(function ($item) use ($totalDocuments) {
                return [
                    'status_name' => $item->status_name,
                    'count' => $item->count,
                    'percentage' => $totalDocuments > 0 ? round(($item->count / $totalDocuments) * 100, 1) : 0
                ];
            });

        // Documentos por categoría
        $documentsByCategory = $company->documents()
            ->join('categories', 'documents.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as category_name, COUNT(*) as count')
            ->groupBy('categories.id', 'categories.name')
            ->get()
            ->map(function ($item) use ($totalDocuments) {
                return [
                    'category_name' => $item->category_name,
                    'count' => $item->count,
                    'percentage' => $totalDocuments > 0 ? round(($item->count / $totalDocuments) * 100, 1) : 0
                ];
            });

        return $this->successResponse([
            'total_documents' => $totalDocuments,
            'pending_documents' => $pendingDocuments,
            'completed_documents' => $completedDocuments,
            'overdue_documents' => $overdueDocuments,
            'total_users' => $company->users()->count(),
            'active_users' => $company->users()->where('is_active', true)->count(),
            'total_branches' => $company->branches()->count(),
            'total_departments' => $company->departments()->count(),
            'total_categories' => $company->categories()->count(),
            'documents_by_status' => $documentsByStatus,
            'documents_by_category' => $documentsByCategory,
            'last_updated' => now(),
        ]);
    }
}
