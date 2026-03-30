<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_student_verified',
        'student_type',
        'school_email',
        'lrn_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'is_admin'            => 'boolean',
            'is_student_verified' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function reviewedPayments(): HasMany
    {
        return $this->hasMany(ManualPayment::class, 'reviewed_by');
    }

    public function studentVerification(): HasOne
    {
        return $this->hasOne(StudentVerification::class);
    }

    // ── Student helpers ───────────────────────────────────────────────────────

    public function isCollegeStudent(): bool
    {
        return $this->student_type === 'college';
    }

    public function isHighSchoolStudent(): bool
    {
        return $this->student_type === 'highschool';
    }

    public function canBuyStudentTicket(): bool
    {
        return (bool) $this->is_student_verified;
    }

    /**
     * Detect student type from a given email.
     * .edu.ph → college (auto-verify), otherwise → highschool (manual).
     */
    public static function detectStudentType(string $email): string
    {
        return str_ends_with(strtolower($email), '.edu.ph') ? 'college' : 'highschool';
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdminOrAbove(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isValidator(): bool
    {
        return $this->hasRole('validator');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function hasOperatorAccess(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'manager', 'validator', 'staff']);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }
}
