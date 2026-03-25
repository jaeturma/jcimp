<?php

namespace App\Services;

use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StudentVerificationService
{
    /**
     * Submit a verification request.
     * College (.edu.ph) → auto-approved.
     * High school       → pending, requires admin review.
     */
    public function submit(
        User $user,
        string $schoolEmail,
        ?string $lrnNumber,
        ?UploadedFile $studentIdFile
    ): StudentVerification {
        $type = User::detectStudentType($schoolEmail);

        if ($type === 'highschool') {
            if (! $lrnNumber) {
                throw new RuntimeException('LRN number is required for high school students.');
            }
            if (! $studentIdFile) {
                throw new RuntimeException('Student ID image is required for high school students.');
            }
            $this->validateLrn($lrnNumber);
        }

        // Prevent duplicate pending submissions
        $existing = StudentVerification::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            throw new RuntimeException(
                $existing->isApproved()
                    ? 'Your student status is already verified.'
                    : 'You already have a pending verification request.'
            );
        }

        $idPath = null;
        if ($studentIdFile) {
            $idPath = $studentIdFile->store('student-ids', 'local');
        }

        $verification = StudentVerification::create([
            'user_id'         => $user->id,
            'student_type'    => $type,
            'school_email'    => $schoolEmail,
            'lrn_number'      => $lrnNumber,
            'student_id_path' => $idPath,
            'status'          => $type === 'college' ? 'approved' : 'pending',
            'reviewed_at'     => $type === 'college' ? now() : null,
        ]);

        // Auto-approve college students immediately
        if ($type === 'college') {
            $user->update([
                'is_student_verified' => true,
                'student_type'        => 'college',
                'school_email'        => $schoolEmail,
            ]);
        } else {
            // Store fields on user even before approval
            $user->update([
                'student_type'  => 'highschool',
                'school_email'  => $schoolEmail,
                'lrn_number'    => $lrnNumber,
            ]);
        }

        return $verification;
    }

    /**
     * Admin approves a high-school verification.
     */
    public function approve(StudentVerification $verification, User $reviewer): void
    {
        if (! $verification->isPending()) {
            throw new RuntimeException('Only pending verifications can be approved.');
        }

        $token = Str::uuid()->toString();

        $verification->update([
            'status'           => 'approved',
            'reviewed_by'      => $reviewer->id,
            'reviewed_at'      => now(),
            'access_token'     => $token,
            'token_expires_at' => now()->addHours(24),
        ]);

        // If it's a user-linked verification, update user record
        if ($verification->user_id && $verification->user) {
            $verification->user->update(['is_student_verified' => true]);
        }

        // Notify guest by email
        if ($verification->isGuest()) {
            \Illuminate\Support\Facades\Mail::to($verification->displayEmail())
                ->send(new \App\Mail\StudentVerificationApprovedMail($verification));
        }
    }

    /**
     * Admin rejects a high-school verification.
     */
    public function reject(StudentVerification $verification, User $reviewer, string $reason): void
    {
        if (! $verification->isPending()) {
            throw new RuntimeException('Only pending verifications can be rejected.');
        }

        $verification->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => $reviewer->id,
            'reviewed_at'      => now(),
        ]);
    }

    /**
     * Generate a signed URL for the admin to view the uploaded student ID image.
     */
    public function temporaryUrl(StudentVerification $verification): ?string
    {
        if (! $verification->student_id_path) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.student-id.view',
            now()->addMinutes(10),
            ['verification' => $verification->id]
        );
    }

    private function validateLrn(string $lrn): void
    {
        // LRN is exactly 12 digits
        if (! preg_match('/^\d{12}$/', $lrn)) {
            throw new RuntimeException('LRN must be exactly 12 digits.');
        }
    }
}
