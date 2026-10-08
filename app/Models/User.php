<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'participation',
    ];

    /** Staff roles that admin creates and manages; registrants have role 'user'. */
    public const STAFF_ROLES = ['admin', 'editor', 'reviewer', 'finance'];

    /** Tracks a reviewer can be assigned papers from. */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'reviewer_track');
    }

    /** Papers this account submitted (the account email is the paper's contact). */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
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
     * @return list<array{key: string, href: string, icon: string, access: string}>
     */
    public function modules(): array
    {
        $role = $this->accessRole();

        return collect(config('modules'))
            ->filter(fn (array $module) => isset($module['access'][$role]))
            ->map(fn (array $module, string $key) => [
                'key' => $key,
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
            'password' => 'hashed',
        ];
    }
}
