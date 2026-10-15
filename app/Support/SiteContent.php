<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Conference information, public-site texts, the important dates and day programme, and the privacy policy that
 * admin edits in Settings (items 36, 37, 40, 43), kept as one JSON
 * setting ('site_content'). Every page gets it as the `site` prop (HandleInertiaRequests) and reads it with useSite().
 * Bilingual texts are { th, en }. Labels and headings stay in the i18n files; only the content lives here.
 */
class SiteContent
{
    private const KEY = 'site_content';

    /** Used until admin saves; also the shape every saved value is merged into. */
    public const DEFAULTS = [
        'conference' => [
            'shortName' => 'SKDIIT 2027',
            'fullName' => [
                'th' => 'การประชุมวิชาการระดับชาติ ด้านนวัตกรรมดิจิทัลเพื่อการศึกษาและการเรียนรู้แห่งอนาคต',
                'en' => 'National Conference on Digital Innovation for Future Education and Learning',
            ],
            'date' => '2027-01-28',
            'venue' => ['th' => 'มหาวิทยาลัยมหาสารคาม', 'en' => 'Mahasarakham University'],
            'email' => 'skdiit@msu.ac.th',
            'phone' => '043-754-321',
        ],
        'home' => [
            'title' => ['th' => 'ระบบลงทะเบียนส่งผลงานวิชาการ', 'en' => 'Academic Paper Registration & Submission'],
        ],
        'about' => [
            'lead' => [
                'th' => 'SKDIIT 2027 เป็นเวทีให้นักการศึกษา นักวิจัย และนักนวัตกรรม ได้แลกเปลี่ยนงานวิจัยและแนวปฏิบัติด้านนวัตกรรมดิจิทัลเพื่อการศึกษาและการเรียนรู้แห่งอนาคต',
                'en' => 'SKDIIT 2027 brings together educators, researchers and innovators to share research and practice on digital innovation for the future of education and learning.',
            ],
            'objectives' => [
                ['th' => 'เป็นเวทีนำเสนอและเผยแพร่ผลงานวิจัยด้านนวัตกรรมดิจิทัลทางการศึกษา', 'en' => 'Provide a forum to present and publish research on digital innovation in education.'],
                ['th' => 'ส่งเสริมความร่วมมือระหว่างมหาวิทยาลัย สถานศึกษา และหน่วยงานทางการศึกษา', 'en' => 'Encourage collaboration between universities, schools and educational agencies.'],
                ['th' => 'เผยแพร่เทคโนโลยีเชิงปฏิบัติที่ช่วยพัฒนาการเรียนการสอน', 'en' => 'Showcase practical technology that improves teaching and learning.'],
            ],
        ],
        'contact' => [
            'lead' => [
                'th' => 'ติดต่อคณะกรรมการจัดงานสำหรับคำถามเกี่ยวกับการลงทะเบียนหรือการส่งผลงาน',
                'en' => 'Reach the organizing committee for any questions about registration or submission.',
            ],
            'address' => [
                'th' => 'สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม อ.กันทรวิชัย จ.มหาสารคาม 44150',
                'en' => 'Academic Resource Center, Mahasarakham University, Kantharawichai, Maha Sarakham 44150',
            ],
            'hours' => ['th' => 'จันทร์–ศุกร์ 08:30–16:30 น.', 'en' => 'Mon–Fri, 08:30–16:30'],
        ],
        // Public Schedule page and the home pages of the backend (Settings › Schedule)
        'dates' => [
            ['date' => '2026-09-01', 'label' => ['th' => 'เปิดลงทะเบียนและรับผลงาน', 'en' => 'Registration and paper submission open']],
            ['date' => '2026-11-30', 'label' => ['th' => 'วันสุดท้ายของการส่งบทความฉบับสมบูรณ์', 'en' => 'Full paper submission deadline']],
            ['date' => '2026-12-20', 'label' => ['th' => 'ประกาศผลการพิจารณาบทความ', 'en' => 'Notification of acceptance']],
            ['date' => '2027-01-08', 'label' => ['th' => 'ส่งบทความฉบับแก้ไขสมบูรณ์', 'en' => 'Camera-ready paper deadline']],
            ['date' => '2027-01-15', 'label' => ['th' => 'ปิดการลงทะเบียน', 'en' => 'Registration closes']],
            ['date' => '2027-01-28', 'label' => ['th' => 'วันจัดงานประชุมวิชาการ', 'en' => 'Conference day']],
        ],
        'program' => [
            ['time' => '08:00 – 09:00', 'label' => ['th' => 'ลงทะเบียน', 'en' => 'Registration']],
            ['time' => '09:00 – 09:30', 'label' => ['th' => 'พิธีเปิด', 'en' => 'Opening ceremony']],
            ['time' => '09:30 – 10:30', 'label' => ['th' => 'ปาฐกถาพิเศษ: นวัตกรรมดิจิทัลเพื่อการเรียนรู้แห่งอนาคต', 'en' => 'Keynote: Digital Innovation for Future Learning']],
            ['time' => '10:45 – 12:00', 'label' => ['th' => 'นำเสนอผลงานภาคบรรยาย (ช่วงที่ 1)', 'en' => 'Oral presentations (Session 1)']],
            ['time' => '13:00 – 15:00', 'label' => ['th' => 'นำเสนอผลงานภาคบรรยาย (ช่วงที่ 2)', 'en' => 'Oral presentations (Session 2)']],
            ['time' => '15:15 – 16:00', 'label' => ['th' => 'มอบรางวัลและพิธีปิด', 'en' => 'Awards and closing']],
        ],
        // PDPA: the policy registrants consent to at sign-up (public page /privacy)
        'privacy' => [
            'body' => [
                'th' => "ผู้จัดงานเก็บรวบรวมข้อมูลส่วนบุคคลของท่าน ได้แก่ ชื่อ-นามสกุล ตำแหน่งทางวิชาการ อีเมล หมายเลขโทรศัพท์ สังกัด และผลงานที่ส่ง เพื่อใช้ในการลงทะเบียนเข้าร่วมงาน การพิจารณาและเผยแพร่ผลงานวิชาการ และการติดต่อประสานงานที่เกี่ยวข้องกับงานประชุมวิชาการนี้เท่านั้น

ข้อมูลของท่านจะเปิดเผยเฉพาะผู้ที่เกี่ยวข้องกับการจัดงาน ได้แก่ ผู้ดูแลระบบ บรรณาธิการ ผู้ประเมิน และวารสารที่พิจารณาบทความ รายชื่อและสังกัดของผู้ลงทะเบียนอาจแสดงในรายชื่อผู้ลงทะเบียนบนหน้าเว็บ ส่วนอีเมลและหมายเลขโทรศัพท์จะไม่เปิดเผยต่อสาธารณะ

ท่านมีสิทธิ์ขอเข้าถึง แก้ไข หรือขอให้ลบข้อมูลส่วนบุคคลของท่าน ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 โดยติดต่อผู้จัดงานตามช่องทางในหน้าติดต่อเรา",
                'en' => "The organisers collect your personal data (name, academic title, email, phone number, affiliation and the papers you submit) only to register you for the conference, to review and publish academic papers, and to contact you about this conference.

Your data is shared only with the people who run the conference: administrators, editors, reviewers and the journal that considers papers. Registrants' names and affiliations may appear in the public list of registrants; emails and phone numbers are never made public.

Under the Personal Data Protection Act B.E. 2562 (2019) you may ask to see, correct or delete your personal data by contacting the organisers through the Contact page.",
            ],
        ],
    ];

    /** Sections admin edits from the "Website content" tab (conference has its own tab). */
    public const PAGES = ['home', 'about', 'contact', 'privacy'];

    /** Sections whose value is a list: a saved list replaces the default instead of merging item by item. */
    private const LISTS = ['dates', 'program'];

    public static function all(): array
    {
        $saved = json_decode((string) Setting::get(self::KEY), true) ?: [];
        $content = array_replace_recursive(self::DEFAULTS, $saved);
        // Lists replace the default list instead of merging item by item
        if (isset($saved['about']['objectives'])) {
            $content['about']['objectives'] = $saved['about']['objectives'];
        }
        foreach (self::LISTS as $list) {
            if (isset($saved[$list])) {
                $content[$list] = $saved[$list];
            }
        }

        return $content;
    }

    public static function put(string $section, array $value): void
    {
        $saved = json_decode((string) Setting::get(self::KEY), true) ?: [];
        $saved[$section] = $value;
        Setting::put(self::KEY, json_encode($saved, JSON_UNESCAPED_UNICODE));
    }
}
