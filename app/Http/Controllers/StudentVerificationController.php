<?php

namespace App\Http\Controllers;

use App\Services\StudentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentVerificationController extends Controller
{
    public function __construct(private readonly StudentVerificationService $service) {}

    /**
     * Submit a student verification request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_email'   => 'required|email|max:255',
            'lrn_number'     => 'nullable|digits:12',
            'student_id'     => 'nullable|file|image|max:4096',   // max 4 MB
        ]);

        try {
            $verification = $this->service->submit(
                user: $request->user(),
                schoolEmail: $request->input('school_email'),
                lrnNumber: $request->input('lrn_number'),
                studentIdFile: $request->file('student_id'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'      => $verification->status === 'approved'
                ? 'Your student status has been automatically verified.'
                : 'Your verification request has been submitted and is pending review.',
            'status'       => $verification->status,
            'student_type' => $verification->student_type,
        ], 201);
    }

    /**
     * Return the current user's verification status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // For guest users, return null status
        if (!$user) {
            return response()->json([
                'is_verified'  => false,
                'student_type' => null,
                'status'       => null,
            ]);
        }

        $verification = $user->studentVerification;

        return response()->json([
            'is_verified'  => $user->is_student_verified,
            'student_type' => $user->student_type,
            'status'       => $verification?->status,
        ]);
    }
}
