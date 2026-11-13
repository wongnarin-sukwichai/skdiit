<?php

namespace App\Models;

use App\Mail\LoginPassword;
use App\Mail\TemplatedMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * One email the system sent (2026-11-13). Every send goes through deliver() / password(), which sends it, records
 * whether it went out and never throws: a failed email never blocks the step that caused it.
 * Shown on Notifications › email log (admin: all; editors: all but password emails), where it can be sent again.
 */
class EmailLog extends Model
{
    public const PASSWORD_KINDS = ['password_registered', 'password_reset'];

    protected $fillable = ['user_id', 'submission_id', 'to_email', 'to_name', 'kind', 'subject', 'body_th', 'body_en', 'button_url', 'status', 'error'];

    protected function casts(): array
    {
        return ['resent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resent_by');
    }

    public function isPassword(): bool
    {
        return in_array($this->kind, self::PASSWORD_KINDS, true);
    }

    /** Editors see every email but the password ones. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when($user->accessRole() !== 'admin', fn ($q) => $q->whereNotIn('kind', self::PASSWORD_KINDS));
    }

    /** Send a workflow email, announcement or account email (TemplatedMail) and log it. */
    public static function deliver(User $to, string $kind, string $subject, string $bodyTh, ?string $bodyEn, ?string $url, ?int $submissionId = null): bool
    {
        $log = new static([
            'user_id' => $to->id, 'submission_id' => $submissionId, 'to_email' => $to->email, 'to_name' => $to->name,
            'kind' => $kind, 'subject' => $subject, 'body_th' => $bodyTh, 'body_en' => $bodyEn, 'button_url' => $url,
        ]);

        return $log->attempt(fn () => Mail::to($to)->send(new TemplatedMail($subject, $bodyTh, $bodyEn, $url)));
    }

    /** Send a sign-in password (LoginPassword) and log it without the password. kind: registered | reset */
    public static function password(User $to, string $password, string $kind): bool
    {
        $log = new static([
            'user_id' => $to->id, 'to_email' => $to->email, 'to_name' => $to->name,
            'kind' => 'password_'.$kind, 'subject' => (new LoginPassword($to, '', $kind))->envelope()->subject,
        ]);

        return $log->attempt(fn () => Mail::to($to)->send(new LoginPassword($to, $password, $kind)));
    }

    private function attempt(callable $send): bool
    {
        try {
            $send();
            $this->fill(['status' => 'sent', 'error' => null]);
        } catch (Throwable $e) {
            report($e);
            $this->fill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500)]);
        }
        $this->save();
        if ($this->status === 'failed') {
            \App\Support\Notifier::emailFailed($this); // admin sees it on the bell
        }

        return $this->status === 'sent';
    }

    /**
     * Send this email again to the same person (e.g. "it never arrived"). A password email cannot be repeated
     * (the password is not kept): the account gets a new password, emailed and logged as a new entry.
     */
    public function resend(User $by): bool
    {
        if ($this->isPassword()) {
            abort_unless($this->user, 404);
            $this->user->emailNewPassword();
            $ok = static::where('user_id', $this->user_id)->whereIn('kind', self::PASSWORD_KINDS)->latest('id')->value('status') === 'sent';
        } else {
            $to = $this->user ?? new User(['email' => $this->to_email, 'name' => $this->to_name]);
            $ok = $this->attempt(fn () => Mail::to($to)->send(new TemplatedMail($this->subject, (string) $this->body_th, $this->body_en, $this->button_url)));
        }
        $this->forceFill(['resent' => $this->resent + 1, 'resent_by' => $by->id, 'resent_at' => now()])->save();

        return $ok;
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'to' => $this->to_email,
            'name' => $this->to_name,
            'kind' => $this->kind,
            'password' => $this->isPassword(),
            'subject' => $this->subject,
            'bodyTh' => $this->body_th,
            'bodyEn' => $this->body_en,
            'button' => (bool) $this->button_url,
            'at' => $this->created_at->toIso8601String(),
            'status' => $this->status,
            'error' => $this->error,
            'resent' => $this->resent,
            'resentBy' => $this->resender?->name,
            'resentAt' => $this->resent_at?->toIso8601String(),
        ];
    }
}
