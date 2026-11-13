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
        'decision_accept_final' => ['comment'],
        'decision_revise' => ['comment', 'due'],
        'decision_reject' => ['comment'],
        'presentation_scheduled' => ['date', 'room'],
        'slides_reminder' => ['date', 'room', 'due'],
        'revision_reminder' => ['due'],
        'revision_closed' => ['comment'],
        'camera_ready_reminder' => [],
        'reviewer_assigned' => ['due'],
        'review_reminder' => ['due'],
        'reviewer_removed' => [],
        'review_due_changed' => ['due'],
        'camera_ready_approved' => ['comment'],
        'camera_ready_fix' => ['comment'],
        'acceptance_cancelled' => ['comment'],
    ];

    /** Account emails, not about a paper: no {code} or {title}. */
    public const ACCOUNT_KEYS = []; // none since 2026-11-13 (became_author removed with attend-only)

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
        'decision_accept_final' => [
            'บทความ {code} ผ่านการประเมิน (ไม่ต้องส่งไฟล์เพิ่ม)', 'Your paper {code} is accepted (no further file needed)',
            "เรียน {name}\n\nขอแสดงความยินดี บทความ \"{title}\" ({code}) ผ่านการประเมินแล้ว บรรณาธิการใช้ไฟล์ฉบับล่าสุดของท่านเป็นฉบับสมบูรณ์ ท่านไม่ต้องส่งไฟล์เพิ่ม ผู้จัดงานจะแจ้งกำหนดการนำเสนอภายหลัง\n\n{comment}",
            "Dear {name},\n\nCongratulations: your paper \"{title}\" ({code}) is accepted. The editors have taken your latest file as the camera-ready version, so you do not need to send another one. We will let you know your presentation slot.\n\n{comment}",
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
        'slides_reminder' => [
            'เตือน: อัปโหลดสไลด์นำเสนอ (บทความ {code})', 'Reminder: please upload your slides ({code})',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) มีกำหนดนำเสนอ {date} ห้อง {room} ระบบยังไม่ได้รับสไลด์นำเสนอของท่าน กรุณาอัปโหลดภายใน {due}: {link}",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) presents on {date}, room {room}. We have not received your slides yet. Please upload them by {due}: {link}",
        ],
        'revision_reminder' => [
            'เตือน: กรุณาส่งฉบับแก้ไข (บทความ {code})', 'Reminder: please send your revision of {code}',
            "เรียน {name}\n\nบรรณาธิการรอฉบับแก้ไขของบทความ \"{title}\" ({code}) กำหนดส่งวันที่ {due} กรุณาส่งฉบับแก้ไขในระบบ: {link}",
            "Dear {name},\n\nThe editors are waiting for your revision of \"{title}\" ({code}), due on {due}. Please send it in the system: {link}",
        ],
        'revision_closed' => [
            'บทความ {code}: ปิดรับฉบับแก้ไข', 'Your paper {code}: revision closed',
            "เรียน {name}\n\nบรรณาธิการไม่ได้รับฉบับแก้ไขของบทความ \"{title}\" ({code}) ภายในกำหนด จึงปิดรับฉบับแก้ไข และบทความไม่ผ่านการพิจารณาในครั้งนี้\n\n{comment}",
            "Dear {name},\n\nThe editors did not receive the revision of \"{title}\" ({code}) in time, so the revision is closed and the paper is not accepted this time.\n\n{comment}",
        ],
        'camera_ready_reminder' => [
            'เตือน: กรุณาส่งไฟล์ฉบับสมบูรณ์ (บทความ {code})', 'Reminder: please upload the camera-ready file of {code}',
            "เรียน {name}\n\nบทความ \"{title}\" ({code}) ผ่านการประเมินแล้ว และยังรอไฟล์ฉบับสมบูรณ์ของท่าน กรุณาอัปโหลดในระบบ: {link}",
            "Dear {name},\n\nYour paper \"{title}\" ({code}) is accepted and we are still waiting for its camera-ready file. Please upload it in the system: {link}",
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
        'reviewer_removed' => [
            'ยกเลิกการประเมินบทความ {code}', 'Review cancelled: {code}',
            "เรียน {name}\n\nบรรณาธิการได้ยกเลิกการประเมินบทความ \"{title}\" ({code}) ของท่านแล้ว ท่านไม่ต้องประเมินบทความนี้ต่อ ขอขอบคุณที่สละเวลา",
            "Dear {name},\n\nThe editors have cancelled your review of \"{title}\" ({code}). You do not need to continue with it. Thank you for your time.",
        ],
        'review_due_changed' => [
            'วันครบกำหนดประเมินบทความ {code} เปลี่ยนแล้ว', 'New due date for your review of {code}',
            "เรียน {name}\n\nวันครบกำหนดประเมินบทความ \"{title}\" ({code}) เปลี่ยนเป็นวันที่ {due} กรุณาส่งผลการประเมินในระบบ: {link}",
            "Dear {name},\n\nThe due date for your review of \"{title}\" ({code}) is now {due}. Please send your evaluation in the system: {link}",
        ],
        'camera_ready_approved' => [
            'ฉบับสมบูรณ์ของบทความ {code} ผ่านการตรวจแล้ว', 'Your camera-ready paper {code} has been approved',
            "เรียน {name}\n\nบรรณาธิการตรวจไฟล์ฉบับสมบูรณ์ของบทความ \"{title}\" ({code}) แล้ว บทความจะรวมอยู่ใน Proceedings ผู้จัดงานจะแจ้งกำหนดการนำเสนอต่อไป\n\n{comment}",
            "Dear {name},\n\nThe editors have checked the camera-ready file of \"{title}\" ({code}). It will be included in the proceedings; we will let you know your presentation slot.\n\n{comment}",
        ],
        'camera_ready_fix' => [
            'บทความ {code}: ขอให้แก้ไขฉบับสมบูรณ์', 'Your paper {code}: please fix the camera-ready file',
            "เรียน {name}\n\nบรรณาธิการขอให้แก้ไขไฟล์ฉบับสมบูรณ์ของบทความ \"{title}\" ({code}) ก่อนรวมเล่ม กรุณาแก้ไขแล้วอัปโหลดใหม่ในระบบ: {link}\n\n{comment}",
            "Dear {name},\n\nThe editors ask you to fix the camera-ready file of \"{title}\" ({code}) before it goes into the proceedings. Please upload a new file in the system: {link}\n\n{comment}",
        ],
        'acceptance_cancelled' => [
            'ยกเลิกการตอบรับบทความ {code}', 'Acceptance of your paper {code} withdrawn',
            "เรียน {name}\n\nบรรณาธิการยกเลิกการตอบรับบทความ \"{title}\" ({code}) ด้วยเหตุผลดังนี้\n\n{comment}",
            "Dear {name},\n\nThe editors have withdrawn the acceptance of \"{title}\" ({code}) for this reason:\n\n{comment}",
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

    /**
     * Fill the placeholders: [subject, body_th, body_en]; unknown or empty placeholders become blank.
     * $varsEn overrides values in the English text (2026-11-13: the English title and dates, not the Thai ones).
     */
    public static function render(string $key, array $vars, array $varsEn = []): array
    {
        $text = self::textOf($key);
        $fill = fn (string $s, array $with) => trim(preg_replace("/\n{3,}/", "\n\n", preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($with[$m[1]] ?? ''), $s)));
        $en = [...$vars, ...$varsEn];

        return [
            'subject' => $fill($text['subject_th'], $vars).' / '.$fill($text['subject_en'], $en),
            'body_th' => $fill($text['body_th'], $vars),
            'body_en' => $fill($text['body_en'], $en),
        ];
    }
}
