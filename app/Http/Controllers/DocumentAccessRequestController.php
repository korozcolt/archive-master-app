<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\User;
use App\Notifications\DocumentAccessRequestedNotification;
use App\Notifications\DocumentAccessRequestResolvedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentAccessRequestController extends Controller
{
    private const APPROVER_ROLES = [
        Role::ArchiveManager->value,
        'admin',
        'super_admin',
        'branch_admin',
        Role::Receptionist->value,
    ];

    public function store(Request $request, Document $document)
    {
        $user = Auth::user();

        if ($document->company_id !== $user->company_id) {
            abort(403, 'No tienes permiso para solicitar acceso a este documento.');
        }

        if ($document->canBeAccessedByPortalUser($user)) {
            return back()->with('info', 'Ya tienes acceso a este documento.');
        }

        if ($document->accessRequests()->forRequester($user->id)->pending()->exists()) {
            return back()->withErrors(['reason' => 'Ya tienes una solicitud pendiente para este documento.']);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $accessRequest = DocumentAccessRequest::create([
            'document_id' => $document->id,
            'company_id' => $document->company_id,
            'requested_by' => $user->id,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
            'requested_at' => now(),
        ]);

        $approvers = $this->eligibleApprovers($document);

        foreach ($approvers as $approver) {
            $approver->notifyNow(new DocumentAccessRequestedNotification($accessRequest, $user->name));
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'Solicitud de acceso enviada. Te notificaremos cuando sea revisada.');
    }

    public function index()
    {
        $user = Auth::user();
        $isApprover = $user->hasAnyRole(self::APPROVER_ROLES);

        $accessRequests = DocumentAccessRequest::with(['document', 'requester'])
            ->where('company_id', $user->company_id)
            ->pending()
            ->when(! $isApprover, fn ($query) => $query->whereHas('document', fn ($documentQuery) => $documentQuery->where('created_by', $user->id)))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('access-requests.index', compact('accessRequests'));
    }

    public function approve(Request $request, DocumentAccessRequest $accessRequest)
    {
        $user = Auth::user();

        if (! $this->canResolve($user, $accessRequest)) {
            abort(403, 'No tienes permisos para aprobar esta solicitud.');
        }

        if (! $accessRequest->isPending()) {
            return back()->with('error', 'Esta solicitud ya fue procesada');
        }

        $validated = $request->validate([
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $hours = (int) data_get(
            $accessRequest->document->company->settings,
            'document_governance.access_request_hours',
            config('documents.access_requests.default_hours')
        );

        $accessRequest->approve($user, $validated['resolution_note'] ?? null, $hours);

        $accessRequest->requester->notifyNow(new DocumentAccessRequestResolvedNotification($accessRequest));

        return redirect()->route('access-requests.index')
            ->with('success', 'Solicitud aprobada correctamente');
    }

    public function reject(Request $request, DocumentAccessRequest $accessRequest)
    {
        $user = Auth::user();

        if (! $this->canResolve($user, $accessRequest)) {
            abort(403, 'No tienes permisos para rechazar esta solicitud.');
        }

        if (! $accessRequest->isPending()) {
            return back()->with('error', 'Esta solicitud ya fue procesada');
        }

        $validated = $request->validate([
            'resolution_note' => 'required|string|max:1000',
        ]);

        $accessRequest->reject($user, $validated['resolution_note']);

        $accessRequest->requester->notifyNow(new DocumentAccessRequestResolvedNotification($accessRequest));

        return redirect()->route('access-requests.index')
            ->with('success', 'Solicitud rechazada correctamente');
    }

    private function canResolve(User $user, DocumentAccessRequest $accessRequest): bool
    {
        if ($accessRequest->company_id !== $user->company_id) {
            return false;
        }

        return $accessRequest->document->created_by === $user->id
            || $user->hasAnyRole(self::APPROVER_ROLES);
    }

    private function eligibleApprovers(Document $document)
    {
        $approvers = User::query()
            ->where('company_id', $document->company_id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::APPROVER_ROLES))
            ->get();

        if ($document->creator) {
            $approvers->push($document->creator);
        }

        return $approvers->unique('id');
    }
}
