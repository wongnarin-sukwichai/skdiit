<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The emails the paper workflow sends (App\Support\WorkflowMail), in Thai and English. Editors and admin may
 * rewrite them (Announcements › Email templates); a template nobody changed uses DEFAULTS. Placeholders in
 * braces ({name}, {code}, ...) are filled in when the email is sent; the ones each email knows are in VARS.
 */
class EmailTemplate extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'subject_th', 'subject_en', 'body_th', 'body_en'];

    /** Placeholders every paper email knows; some emails add their own (see VARS). */
    public const COMMON = ['name', 'code', 'title', 'link'];

    /** key => extra placeholders */
    public const VARS = [
        'paper_received' => [],
        'journal_selected' => ['journal_url'],
        'journal_passed_on' => [],
        'journal_accepted' => [],
        'screening_pass' => ['comment'],
        'screening_revise' => ['comment'],
        'screening_reject' => ['comment'],
        'decision_accept' => ['comment'],
        'decision_minor' => ['comment', 'due'],
        'decision_major' => ['comment', 'due'],
        'decision_reject' => ['comment'],
        'presentation_scheduled' => ['date', 'room'],
        'reviewer_assigned' => ['due'],
        'review_reminder' => ['due'],
    ];

    /** key => [subject_th, subject_en, body_th, body_en] */
    public const DEFAULTS = [
        'paper_received' => [
            'ได้รับบทความ {code} แล้ว', 'We received your paper {code}',
            "เรียน {name}\n\nระบบได้รับบทความ \"{title}\" ({code}) ของท่านแล้ว ท่านแก้ไขบทความและเปลี่ยนไฟล์ได้จนถึงวันปิดรับบทความ",
            "Dear {name},\n\nWe received your paper \"{title}\" ({code}). You can edit it and replace the files until the submission deadline.",
        ],
        'journal_selected' => [
            'Journal คัดเลือกบทความ {code}', 'The journal selected your paper {code}',
            "เรียน {name}\n\nJournal คัดเลือกบทความ \"{title}\" ({code}) ของท่าน กรุณาส่งบทความที่เว็บไซต์ของวารสาร: {journal_url}\n\nหากวารสารไม่ตอบรับ บทความของท่านจะเข้าสู่กระบวนการ Conference ต่อ",
            "Dear {name},\n\nThe journal selected your paper \"{title}\" ({code}). Please submit it on the journal's website: {journal_url}\n\nIf the journal does not accept it, your paper continues in the conference process.",
        ],
        'journal_passed_on' => [
            'บทความ {code} เข้าสู่กระบวนการ Conference', 'Your paper {code} moves on to the conference process',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) ของท่านเข้าสู่กระบวนการ Conference บรรณาธิการจะคัดกรองและส่งให้ผู้ทรงคุณวุฒิประเมินต่อไป",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) moves on to the conference process. The editors will screen it and send it to reviewers.",
        ],
        'journal_accepted' => [
            'วารสารตอบรับบทความ {code}', 'The journal accepted your paper {code}',
            "เรียน {name}\n\nยินดีด้วย วารสารตอบรับบทความ \"{title}\" ({code}) ของท่านแล้ว ท่านจะได้นำเสนอผลงานในงานประชุม ผู้จัดงานจะแจ้งวันเวลานำเสนอให้ทราบ",
            "Dear {name},\n\nCongratulations: the journal accepted your paper \"{title}\" ({code}). You will present it at the conference; we will let you know your slot.",
        ],
        'screening_pass' => [
            'บทความ {code} ผ่านการคัดกรอง', 'Your paper {code} passed screening',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) ผ่านการคัดกรองเบื้องต้นแล้ว และจะเข้าสู่การประเมินโดยผู้ทรงคุณวุฒิ\n\n{comment}",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) passed screening and now goes to peer review.\n\n{comment}",
        ],
        'screening_revise' => [
            'บทความ {code} ถูกส่งกลับให้แก้ไข', 'Please revise your paper {code}',
            "เรียน {name}\n\nบรรณาธิการส่งบทความ \"{title}\" ({code}) กลับให้แก้ไขก่อนเข้าสู่การประเมิน\n\n{comment}\n\nกรุณาแก้ไขแล้วส่งกลับในระบบ",
            "Dear {name},\n\nThe editors sent your paper \"{title}\" ({code}) back for revision before peer review.\n\n{comment}\n\nPlease revise it and resubmit it in the system.",
        ],
        'screening_reject' => [
            'ผลการคัดกรองบทความ {code}', 'Screening result of your paper {code}',
            "เรียน {name}\n\nขอบคุณที่ส่งบทความ \"{title}\" ({code}) บทความไม่ผ่านการคัดกรองเบื้องต้นในครั้งนี้\n\n{comment}",
            "Dear {name},\n\nThank you for submitting \"{title}\" ({code}). It did not pass screening this time.\n\n{comment}",
        ],
        'decision_accept' => [
            'บทความ {code} ผ่านการประเมิน', 'Your paper {code} has been accepted',
            "เรียน {name}\n\nยินดีด้วย บทความ \"{title}\" ({code}) ผ่านการประเมินแล้ว กรุณาอัปโหลดไฟล์ฉบับสมบูรณ์ (camera-ready) ในระบบ\n\n{comment}",
            "Dear {name},\n\nCongratulations: your paper \"{title}\" ({code}) has been accepted. Please upload the camera-ready file in the system.\n\n{comment}",
        ],
        'decision_minor' => [
            'บทความ {code}: ขอให้แก้ไขเล็กน้อย', 'Your paper {code}: minor revision',
            "เรียน {name}\n\nบรรณาธิการขอให้แก้ไขบทความ \"{title}\" ({code}) เล็กน้อยตามความเห็นของผู้ประเมิน กรุณาส่งฉบับแก้ไขภายในวันที่ {due}\n\n{comment}",
            "Dear {name},\n\nThe editor asks for a minor revision of \"{title}\" ({code}) following the reviewers' comments. Please resubmit by {due}.\n\n{comment}",
        ],
        'decision_major' => [
            'บทความ {code}: ขอให้แก้ไขมาก', 'Your paper {code}: major revision',
            "เรียน {name}\n\nบรรณาธิการขอให้แก้ไขบทความ \"{title}\" ({code}) ตามความเห็นของผู้ประเมิน กรุณาส่งฉบับแก้ไขภายในวันที่ {due}\n\n{comment}",
            "Dear {name},\n\nThe editor asks for a major revision of \"{title}\" ({code}) following the reviewers' comments. Please resubmit by {due}.\n\n{comment}",
        ],
        'decision_reject' => [
            'ผลการพิจารณาบทความ {code}', 'Decision on your paper {code}',
            "เรียน {name}\n\nขอบคุณที่ส่งบทความ \"{title}\" ({code}) บทความยังไม่ผ่านการพิจารณาในครั้งนี้\n\n{comment}",
            "Dear {name},\n\nThank you for submitting \"{title}\" ({code}). It was not accepted this time.\n\n{comment}",
        ],
        'presentation_scheduled' => [
            'กำหนดการนำเสนอบทความ {code}', 'Your presentation slot for {code}',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) มีกำหนดนำเสนอ {date} ห้อง {room} กรุณาอัปโหลดสไลด์นำเสนอในระบบ",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) presents on {date}, room {room}. Please upload your slides in the system.",
        ],
        'reviewer_assigned' => [
            'ขอเรียนเชิญประเมินบทความ {code}', 'Please review paper {code}',
            "เรียน {name}\n\nขอเรียนเชิญท่านประเมินบทความ \"{title}\" ({code}) กรุณาส่งผลการประเมินภายในวันที่ {due}",
            "Dear {name},\n\nWe would like to ask you to review \"{title}\" ({code}). Please send your evaluation by {due}.",
        ],
        'review_reminder' => [
            'เตือนการประเมินบทความ {code}', 'Reminder: review of {code}',
            "เรียน {name}\n\nขอเตือนการประเมินบทความ \"{title}\" ({code}) ซึ่งครบกำหนดวันที่ {due} หากไม่สะดวกประเมิน กรุณาแจ้งในระบบ",
            "Dear {name},\n\nThis is a reminder about your review of \"{title}\" ({code}), due on {due}. If you cannot review it, please say so in the system.",
        ],
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The current text of a template: the saved one, or the default. */
    public static function textOf(string $key): array
    {
        $saved = static::find($key);
        [$subjectTh, $subjectEn, $bodyTh, $bodyEn] = self::DEFAULTS[$key];

        return $saved
            ? ['subject_th' => $saved->subject_th, 'subject_en' => $saved->subject_en, 'body_th' => $saved->body_th, 'body_en' => $saved->body_en]
            : ['subject_th' => $subjectTh, 'subject_en' => $subjectEn, 'body_th' => $bodyTh, 'body_en' => $bodyEn];
    }

    /** Fill the placeholders: [subject, body_th, body_en]; unknown or empty placeholders become blank. */
    public static function render(string $key, array $vars): array
    {
        $text = self::textOf($key);
        $fill = fn (string $s) => trim(preg_replace("/\n{3,}/", "\n\n", preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $s)));

        return [
            'subject' => $fill($text['subject_th']).' / '.$fill($text['subject_en']),
            'body_th' => $fill($text['body_th']),
            'body_en' => $fill($text['body_en']),
        ];
    }
}
