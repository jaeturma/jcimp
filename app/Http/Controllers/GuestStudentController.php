<?php
namespace App\Http\Controllers;

use App\Mail\StudentVerificationApprovedMail;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GuestStudentController extends Controller
{
    // POST /api/student/send-otp
    // Body: { email }
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);
        $email = strtolower(trim($request->input('email')));

        // If logged-in user already verified, return their token
        if ($user = $request->user()) {
            if ($user->is_student_verified) {
                $sv = StudentVerification::where('user_id', $user->id)->where('status', 'approved')->latest()->first();
                if ($sv) {
                    $this->refreshToken($sv);
                    return response()->json(['status' => 'approved', 'access_token' => $sv->access_token, 'student_type' => $sv->student_type]);
                }
            }
        }

        // Check for existing approved verification for this email
        $existing = StudentVerification::where('guest_email', $email)->where('status', 'approved')->latest()->first();
        if ($existing) {
            $this->refreshToken($existing);
            // Still require OTP re-verify for security — fall through to generate new OTP
        }

        // Check for pending review — don't re-send OTP, just inform them
        $pending = StudentVerification::where('guest_email', $email)->where('status', 'pending')->latest()->first();
        if ($pending) {
            return response()->json([
                'status' => 'pending_review',
                'message' => 'Your verification is still pending admin review. You will receive an email once approved.',
            ]);
        }

        // Generate OTP
        $otp  = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $type = $this->detectType($email);

        // Expire any old unverified entries
        StudentVerification::where('guest_email', $email)
            ->whereNull('otp_verified_at')
            ->whereIn('status', ['pending_otp'])
            ->delete();

        // Create new OTP entry
        $sv = StudentVerification::create([
            'user_id'        => null,
            'guest_email'    => $email,
            'student_type'   => $type,
            'school_email'   => $email,
            'status'         => 'pending_otp',
            'otp_hash'       => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP email (plain mail)
        Mail::raw(
            "Your student verification OTP is: {$otp}\n\nThis code expires in 10 minutes.\n\nDo not share this code.",
            fn($m) => $m->to($email)->subject('Your Student Verification OTP — Concert Ticketing')
        );

        return response()->json([
            'status'  => 'otp_sent',
            'message' => "OTP sent to {$email}. Check your inbox.",
            'type'    => $type, // college or highschool
        ]);
    }

    // POST /api/student/verify-otp  (multipart for highschool)
    // Body: { email, otp, lrn_number?, student_id? }
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email'      => 'required|email|max:255',
            'otp'        => 'required|string|size:6',
            'lrn_number' => 'nullable|digits:12',
            'student_id' => 'nullable|file|image|max:4096',
        ]);
        $email = strtolower(trim($request->input('email')));

        $sv = StudentVerification::where('guest_email', $email)
            ->where('status', 'pending_otp')
            ->whereNull('otp_verified_at')
            ->latest()
            ->first();

        if (!$sv) {
            return response()->json(['message' => 'No pending OTP found for this email. Please request a new OTP.'], 422);
        }
        if ($sv->otp_expires_at->isPast()) {
            return response()->json(['message' => 'OTP has expired. Please request a new one.'], 422);
        }
        if (!Hash::check($request->input('otp'), $sv->otp_hash)) {
            return response()->json(['message' => 'Incorrect OTP. Please try again.'], 422);
        }

        $sv->update(['otp_verified_at' => now()]);

        // College (.edu.ph): auto-approve immediately
        if ($sv->student_type === 'college') {
            $this->refreshToken($sv);
            $sv->update(['status' => 'approved', 'reviewed_at' => now()]);
            return response()->json([
                'status'       => 'approved',
                'student_type' => 'college',
                'access_token' => $sv->fresh()->access_token,
                'message'      => 'Verified! Your .edu.ph email has been automatically accepted.',
            ]);
        }

        // Highschool: validate LRN + ID were submitted with the OTP
        if (!$request->filled('lrn_number') || !$request->hasFile('student_id')) {
            return response()->json(['message' => 'LRN number and student ID image are required for non-.edu.ph emails.'], 422);
        }

        $path = $request->file('student_id')->store('student-ids', 'local');

        $sv->update([
            'lrn_number'      => $request->input('lrn_number'),
            'student_id_path' => $path,
            'status'          => 'pending',
        ]);

        return response()->json([
            'status'       => 'pending_review',
            'student_type' => 'highschool',
            'message'      => 'OTP verified. Your details have been submitted for admin review. You will receive an email once approved.',
        ]);
    }

    // POST /api/student/submit-details  (multipart)
    // Body: { email, sv_id, lrn_number, student_id (file) }
    public function submitDetails(Request $request): JsonResponse
    {
        $request->validate([
            'email'      => 'required|email|max:255',
            'sv_id'      => 'required|integer',
            'lrn_number' => 'required|digits:12',
            'student_id' => 'required|file|image|max:4096',
        ]);
        $email = strtolower(trim($request->input('email')));

        $sv = StudentVerification::where('id', $request->input('sv_id'))
            ->where('guest_email', $email)
            ->where('student_type', 'highschool')
            ->whereNotNull('otp_verified_at')
            ->where('status', 'pending_otp')
            ->first();

        if (!$sv) {
            return response()->json(['message' => 'Verification session not found or already submitted.'], 422);
        }

        $path = $request->file('student_id')->store('student-ids', 'local');

        $sv->update([
            'lrn_number'      => $request->input('lrn_number'),
            'student_id_path' => $path,
            'status'          => 'pending',
        ]);

        return response()->json([
            'status'  => 'pending_review',
            'message' => 'Submitted! An admin will review your details. You will receive an email once approved.',
        ]);
    }

    // GET /api/student/check-status?email=
    // For highschool guests to poll their approval status
    public function checkStatus(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);
        $email = strtolower(trim($request->input('email')));

        $sv = StudentVerification::where('guest_email', $email)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest()
            ->first();

        if (!$sv) {
            return response()->json(['status' => 'not_found']);
        }

        $result = ['status' => $sv->status, 'student_type' => $sv->student_type];

        if ($sv->isApproved()) {
            $this->refreshToken($sv);
            $result['access_token'] = $sv->fresh()->access_token;
        }

        if ($sv->isRejected()) {
            $result['rejection_reason'] = $sv->rejection_reason;
        }

        return response()->json($result);
    }

    private function detectType(string $email): string
    {
        return preg_match('/@[^@]+\.edu\.ph$/i', $email) ? 'college' : 'highschool';
    }

    private function refreshToken(StudentVerification $sv): void
    {
        if (!$sv->access_token || !$sv->token_expires_at || $sv->token_expires_at->isPast()) {
            $sv->update([
                'access_token'     => Str::uuid()->toString(),
                'token_expires_at' => now()->addHours(2),
            ]);
        }
    }
}
