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
        'journal_selected' => ['journal', 'due'],
        'journal_offer_reminder' => ['journal', 'due'],
        'journal_accepted' => ['journal', 'journal_url'],
        'journal_offer_expired' => ['journal'],
        'journal_passed_on' => [],
        'screening_pass' => ['comment'],
        'screening_revise' => ['comment'],
        'screening_reject' => ['comment'],
        'decision_accept' => ['comment'],
        'decision_revise' => ['comment', 'due'],
        'decision_reject' => ['comment'],
        'presentation_scheduled' => ['date', 'room'],
        'reviewer_assigned' => ['due'],
        'review_reminder' => ['due'],
        'became_author' => [],
    ];

    /** Account emails, not about a paper: no {code} or {title}. */
    public const ACCOUNT_KEYS = ['became_author'];

    /** key => [subject_th, subject_en, body_th, body_en] */
    public const DEFAULTS = [
        'paper_received' => [
            'ได้รับบทความ {code} แล้ว', 'We received your paper {code}',
            "เรียน {name}\n\nระบบได้รับบทความ \"{title}\" ({code}) ของท่านแล้ว ท่านแก้ไขบทความและเปลี่ยนไฟล์ได้จนถึงวันปิดรับบทความ",
            "Dear {name},\n\nWe received your paper \"{title}\" ({code}). You can edit it and replace the files until the submission deadline.",
        ],
        'journal_selected' => [
            'บทความ {code} ได้รับการพิจารณาจากวารสาร', 'A journal is interested in your paper {code}',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) ของท่านได้รับการพิจารณาจากวารสาร {journal}\n\nกรุณาแจ้งในระบบว่าท่านจะตอบรับหรือไม่ ภายในวันที่ {due}: {link}\n\n- ตอบรับ: ท่านส่งบทความต่อที่เว็บไซต์ของวารสาร (ระบบจะแสดงลิงก์ให้) และไม่ต้องผ่านการประเมินของงานประชุม ผู้จัดงานจะแจ้งอีกครั้งเมื่อถึงเวลาส่งไฟล์นำเสนอ\n- ปฏิเสธ หรือไม่ตอบภายในกำหนด: บทความจะเข้าสู่กระบวนการพิจารณาของงานประชุม (Conference) ตามปกติ",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) has been considered by the journal {journal}.\n\nPlease tell us in the system whether you accept, by {due}: {link}\n\n- Accept: you continue on the journal's website (the system shows the link) and skip the conference review. We will contact you again when the presentation file is due.\n- Decline, or no answer by the due date: your paper continues in the conference process.",
        ],
        'journal_offer_reminder' => [
            'เตือน: กรุณาตอบรับหรือปฏิเสธวารสาร (บทความ {code})', 'Reminder: please answer the journal offer for {code}',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) ได้รับการพิจารณาจากวารสาร {journal} และยังรอคำตอบจากท่าน กรุณาตอบภายในวันที่ {due}: {link}\n\nหากไม่ตอบภายในกำหนด บทความจะเข้าสู่กระบวนการพิจารณาของงานประชุม (Conference)",
            "Dear {name},\n\nThe journal {journal} is still waiting for your answer about \"{title}\" ({code}). Please answer by {due}: {link}\n\nWithout an answer by then, your paper continues in the conference process.",
        ],
        'journal_accepted' => [
            'ท่านตอบรับวารสาร {journal} แล้ว (บทความ {code})', 'You accepted the journal {journal} ({code})',
            "เรียน {name}\n\nท่านตอบรับให้บทความ \"{title}\" ({code}) ไปตีพิมพ์กับวารสาร {journal} แล้ว กรุณาส่งบทความต่อที่เว็บไซต์ของวารสาร: {journal_url}\n\nบทความนี้ไม่ต้องผ่านการประเมินของงานประชุม ผู้จัดงานจะแจ้งอีกครั้งเมื่อถึงเวลาส่งไฟล์นำเสนอผลงานในระบบ",
            "Dear {name},\n\nYou accepted that \"{title}\" ({code}) goes to the journal {journal}. Please submit it on the journal's website: {journal_url}\n\nThe paper skips the conference review. We will contact you again when the presentation file is due in the system.",
        ],
        'journal_offer_expired' => [
            'บทความ {code} เข้าสู่กระบวนการ Conference', 'Your paper {code} moves on to the conference process',
            "เรียน {name}\n\nระบบไม่ได้รับคำตอบเรื่องวารสาร {journal} สำหรับบทความ \"{title}\" ({code}) ภายในกำหนด บทความจึงเข้าสู่กระบวนการพิจารณาของงานประชุม (Conference) บรรณาธิการจะคัดกรองและส่งให้ผู้ทรงคุณวุฒิประเมินต่อไป",
            "Dear {name},\n\nWe did not receive your answer about the journal {journal} for \"{title}\" ({code}) by the due date, so the paper continues in the conference process. The editors will screen it and send it to reviewers.",
        ],
        'journal_passed_on' => [
            'บทความ {code} เข้าสู่กระบวนการ Conference', 'Your paper {code} moves on to the conference process',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) ของท่านเข้าสู่กระบวนการ Conference บรรณาธิการจะคัดกรองและส่งให้ผู้ทรงคุณวุฒิประเมินต่อไป",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) moves on to the conference process. The editors will screen it and send it to reviewers.",
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
        'decision_revise' => [
            'บทความ {code}: ขอให้แก้ไข', 'Your paper {code}: revision requested',
            "เรียน {name}\n\nบรรณาธิการขอให้แก้ไขบทความ \"{title}\" ({code}) ตามความเห็นของผู้ประเมิน กรุณาส่งฉบับแก้ไขภายในวันที่ {due}\n\n{comment}",
            "Dear {name},\n\nThe editor asks you to revise \"{title}\" ({code}) following the reviewers' comments. Please resubmit by {due}.\n\n{comment}",
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
        'became_author' => [
            'บัญชีของท่านส่งผลงานได้แล้ว', 'You can now submit papers',
            "เรียน {name}\n\nบัญชีของท่านเปลี่ยนเป็นผู้เข้าร่วมงานและส่งผลงานแล้ว เข้าสู่ระบบด้วยรหัสผ่านเดิม แล้วส่งบทความได้ที่เมนูบทความของฉัน: {link}",
            "Dear {name},\n\nYour account can now submit papers. Sign in with your current password and submit under My submissions: {link}",
        ],
    ];

    /** Placeholders a template knows, for the template editor. */
    public static function varsOf(string $key): array
    {
        return [...(in_array($key, self::ACCOUNT_KEYS, true) ? ['name', 'link'] : self::COMMON), ...self::VARS[$key]];
    }

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
