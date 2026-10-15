<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\LoginPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'affiliation',
        'affiliation_other',
        'participation',
        'academic_title_id',
        'phone',
    ];

    /** Staff roles that admin creates and manages; registrants have role 'user'. */
    public const STAFF_ROLES = ['admin', 'editor', 'reviewer', 'journal'];

    /** Optional academic title for staff and reviewers (managed in Settings › Academic titles). */
    public function academicTitle(): BelongsTo
    {
        return $this->belongsTo(AcademicTitle::class);
    }

    /** Tracks a reviewer can be assigned papers from. */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'reviewer_track');
    }

    /** Evaluations assigned to this account as a reviewer (every round). */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /** Papers this account submitted (the account email is the paper's contact). */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** Random 6-digit password emailed to registrants (config/registration.php). */
    public static function generatePassword(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * "Email didn't arrive": a new password admin passes on by private chat. Returned once and stored only
     * as a hash; signing in with it does not confirm the email (password_by_admin).
     */
    public function issuePasswordByAdmin(User $admin): string
    {
        $password = self::generatePassword();
        $this->forceFill(['password' => $password, 'password_by_admin' => true])->save();

        // Until the Activity log module exists
        Log::info('Admin generated a password to pass on', ['admin_id' => $admin->id, 'user_id' => $this->id]);

        return $password;
    }

    /**
     * A new 6-digit password sent to the account's email (nobody sees it on screen).
     * Signing in with it confirms the email address.
     */
    public function emailNewPassword(): string
    {
        $password = self::generatePassword();
        $this->forceFill(['password' => $password, 'password_by_admin' => false])->save();

        try {
            Mail::to($this)->send(new LoginPassword($this, $password, 'reset'));
        } catch (Throwable $e) {
            // The password is changed either way; admin can send again or pass one on by chat
            report($e);
        }

        return $password;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Role used for module access (config/modules.php).
     * Staff roles come from `role`; registrants are an author or an attendee depending on `participation`.
     */
    public function accessRole(): string
    {
        if ($this->role !== 'user') {
            return $this->role;
        }

        return $this->participation === 'attend_submit' ? 'author' : 'attendee';
    }

    /** Access level for a module ('full' | 'view' | 'own'), or null if the user cannot use it. */
    public function moduleAccess(?string $module): ?string
    {
        return config("modules.{$module}.access.{$this->accessRole()}");
    }

    /**
     * Modules this user can open, in sidebar order.
     *
     * @return list<array{key: string, group: string, href: string, icon: string, access: string}>
     */
    public function modules(): array
    {
        $role = $this->accessRole();

        return collect(config('modules'))
            ->filter(fn (array $module) => isset($module['access'][$role]))
            ->map(fn (array $module, string $key) => [
                'key' => $key,
                'group' => $module['group'],
                'href' => $module['href'],
                'icon' => $module['icon'],
                'access' => $module['access'][$role],
            ])
            ->values()
            ->all();
    }

    public function canAccessBackend(): bool
    {
        return $this->modules() !== [];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pdpa_accepted_at' => 'datetime',
            'password' => 'hashed',
            'password_by_admin' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }
}
