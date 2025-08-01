<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Http\Requests\Api\UpdateCompanyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Companies",
 *     description="API Endpoints for company management"
 * )
 */
class CompanyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/companies",
     *     summary="Get list of companies",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Company"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Company::class);
        
        $query = Company::with(['branches', 'departments', 'categories', 'statuses', 'tags']);
        
        // Super admin can see all companies, others only their own
        if (!Auth::user()->hasRole('super-admin')) {
            $query->where('id', Auth::user()->company_id);
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min($request->get('per_page', 15), 100);
        $companies = $query->paginate($perPage);

        return CompanyResource::collection($companies);
    }

    /**
     * @OA\Get(
     *     path="/api/companies/{id}",
     *     summary="Get company by ID",
     *     tags={"Companies"},
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
     *         @OA\JsonContent(ref="#/components/schemas/Company")
     *     )
     * )
     */
    public function show(Company $company)
    {
        $this->authorize('view', $company);
        
        $company->load([
            'branches', 'departments', 'categories', 'statuses', 'tags',
            'users' => function ($query) {
                $query->select('id', 'name', 'email', 'company_id', 'is_active');
            }
        ]);

        return new CompanyResource($company);
    }

    /**
     * @OA\Put(
     *     path="/api/companies/{id}",
     *     summary="Update company",
     *     tags={"Companies"},
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
     *             @OA\Property(property="name", type="string", example="Company Name Updated"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Company")
     *     )
     * )
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $data = $request->validated();

        $company->update($data);
        $company->load(['branches', 'departments', 'categories', 'statuses', 'tags']);

        return new CompanyResource($company);
    }
}