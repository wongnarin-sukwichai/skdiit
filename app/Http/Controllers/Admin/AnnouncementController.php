<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\Audience;
use App\Support\WorkflowMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Announcements (editors and admin): email a group of people (item 38), and the email templates of the paper
 * workflow (item 8). Emails go through the configured mailer: the log until SMTP is set up, then real email.
 */
class AnnouncementController extends Controller
{
    private const MESSAGES = ['required' => 'validation.required', 'max' => 'validation.too_long', 'audience.in' => 'validation.required'];

    public function index(): Response
    {
        return Inertia::render('Admin/Announcements', [
            'counts' => Audience::counts(),
            'history' => Announcement::with('user')->latest()->limit(50)->get()->map->toClient(),
            'templates' => collect(EmailTemplate::DEFAULTS)->keys()->map(function (string $key) {
                $saved = EmailTemplate::with('updatedBy')->find($key);

                return [
                    'key' => $key,
                    ...EmailTemplate::textOf($key),
                    'vars' => [...EmailTemplate::COMMON, ...EmailTemplate::VARS[$key]],
                    'changed' => $saved ? ['by' => $saved->updatedBy?->name, 'at' => $saved->updated_at->toIso8601String()] : null,
                ];
            }),
        ]);
    }

    /** Send to everyone in the group now; {name} becomes each recipient's name. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(Audience::KEYS)],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ], self::MESSAGES);

        $recipients = Audience::query($data['audience'])->get();
        abort_if($recipients->isEmpty(), 422);

        $recipients->each(fn (User $user) => WorkflowMail::send(
            $user,
            str_replace('{name}', $user->name, $data['subject']),
            str_replace('{name}', $user->name, $data['body']),
            null,
            url($user->modules() ? '/admin' : '/'),
        ));

        $announcement = new Announcement([...$data, 'recipients' => $recipients->count()]);
        $announcement->user()->associate($request->user());
        $announcement->save();

        return back()->with('success', 'announcements.sent');
    }

    public function updateTemplate(Request $request, string $key): RedirectResponse
    {
        abort_unless(isset(EmailTemplate::DEFAULTS[$key]), 404);

        $data = $request->validate([
            'subject_th' => ['required', 'string', 'max:200'],
            'subject_en' => ['required', 'string', 'max:200'],
            'body_th' => ['required', 'string', 'max:10000'],
            'body_en' => ['required', 'string', 'max:10000'],
        ], self::MESSAGES);

        $template = EmailTemplate::firstOrNew(['key' => $key]);
        $template->fill($data)->forceFill(['updated_by' => $request->user()->id])->save();

        return back()->with('success', 'announcements.templateSaved');
    }

    /** Back to the default text. */
    public function resetTemplate(string $key): RedirectResponse
    {
        abort_unless(isset(EmailTemplate::DEFAULTS[$key]), 404);
        EmailTemplate::whereKey($key)->delete();

        return back()->with('success', 'announcements.templateReset');
    }
}
