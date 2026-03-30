<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentVerification;
use App\Services\StudentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StudentVerificationController extends Controller
{
    public function __construct(private readonly StudentVerificationService $service) {}

    /**
     * List verification requests (filterable by status).
     */
    public function index(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('verify students');
        }

        $query = StudentVerification::with(['user:id,name,email', 'reviewer:id,name'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%"))
                ->orWhere('lrn_number', 'like', "%{$term}%");
        }

        $perPage = intval($request->input('per_page', 20)) > 0 ? intval($request->input('per_page', 20)) : 20;
        $items = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $items->map(fn ($v) => $this->format($v)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    /**
     * Get a single verification with a temporary ID image URL.
     */
    public function show(StudentVerification $studentVerification): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('verify students');
        }
        $studentVerification->load(['user:id,name,email', 'reviewer:id,name']);

        return response()->json([
            'data' => array_merge(
                $this->format($studentVerification),
                ['id_image_url' => $this->service->temporaryUrl($studentVerification)]
            ),
        ]);
    }

    /**
     * Approve or reject a pending verification.
     */
    public function review(Request $request, StudentVerification $studentVerification): JsonResponse
    {
        if (!auth()->user()->hasRole('super_admin')) {
            $this->authorize('verify students');
        }

        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|nullable|string|max:500',
        ]);

        try {
            if ($request->input('action') === 'approve') {
                $this->service->approve($studentVerification, $request->user());
            } else {
                $this->service->reject($studentVerification, $request->user(), $request->input('reason'));
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Verification ' . $request->input('action') . 'd.']);
    }

    /**
     * Serve the student ID image via signed URL (admin only).
     */
    public function viewImage(StudentVerification $verification): Response
    {
        abort_unless($verification->student_id_path && Storage::disk('local')->exists($verification->student_id_path), 404);

        $contents = Storage::disk('local')->get($verification->student_id_path);
        $mime = Storage::disk('local')->mimeType($verification->student_id_path) ?: 'image/jpeg';

        return response($contents, 200, ['Content-Type' => $mime]);
    }

    private function format(StudentVerification $v): array
    {
        return [
            'id'               => $v->id,
            'user_id'          => $v->user_id,
            'user_name'        => $v->user?->name ?? '(Guest)',
            'user_email'       => $v->displayEmail(),
            'student_type'     => $v->student_type,
            'school_email'     => $v->school_email,
            'lrn_number'       => $v->lrn_number,
            'has_id_image'     => (bool) $v->student_id_path,
            'status'           => $v->status,
            'rejection_reason' => $v->rejection_reason,
            'reviewer_name'    => $v->reviewer?->name,
            'reviewed_at'      => $v->reviewed_at?->toISOString(),
            'created_at'       => $v->created_at?->toISOString(),
        ];
    }
}
