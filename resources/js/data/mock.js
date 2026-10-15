/*
 * Mock data for the pages that do not read the database yet. Bilingual values use { en, th } and are rendered with tr().
 *
 * People and papers come from demo.json, the same file DemoSubmissionSeeder puts into the real database, so the
 * sample pages (registrants, publication, presentations, reports, notifications, email and activity logs) show the
 * same 20 registrants, 15 papers, codes and statuses as the real paper pages. Dates are relative to today.
 */
import demo from './demo.json';

export const affiliations = [
    { key: 'msu', en: 'Mahasarakham University', th: 'มหาวิทยาลัยมหาสารคาม' },
    { key: 'kku', en: 'Khon Kaen University', th: 'มหาวิทยาลัยขอนแก่น' },
    { key: 'cmu', en: 'Chiang Mai University', th: 'มหาวิทยาลัยเชียงใหม่' },
    { key: 'nu', en: 'Naresuan University', th: 'มหาวิทยาลัยนเรศวร' },
    { key: 'rmu', en: 'Rajabhat Maha Sarakham University', th: 'มหาวิทยาลัยราชภัฏมหาสารคาม' },
    { key: 'ubu', en: 'Ubon Ratchathani University', th: 'มหาวิทยาลัยอุบลราชธานี' },
    { key: 'tu', en: 'Thammasat University', th: 'มหาวิทยาลัยธรรมศาสตร์' },
    { key: 'kmitl', en: "King Mongkut's Institute of Technology Ladkrabang", th: 'สถาบันเทคโนโลยีพระจอมเกล้าเจ้าคุณทหารลาดกระบัง' },
    { key: 'obec', en: 'Office of the Basic Education Commission', th: 'สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน' },
    { key: 'ksu', en: 'Kalasin University', th: 'มหาวิทยาลัยกาฬสินธุ์' },
    { key: 'other', en: 'Other', th: 'อื่น ๆ' },
];

export const affiliationByKey = Object.fromEntries(affiliations.map((a) => [a.key, a]));

// Same names as the tracks table (admin renames them in the Tracks menu)
export const mockTracks = [
    { id: 1, name: { en: 'Smart Knowledge Library and Information', th: 'ห้องสมุดและสารสนเทศอัจฉริยะ' } },
    { id: 2, name: { en: 'Smart Spaces and Cyber-Physical Environments', th: 'พื้นที่อัจฉริยะและสภาพแวดล้อมไซเบอร์-กายภาพ' } },
    { id: 3, name: { en: 'Digital Media, Communication and Society', th: 'สื่อดิจิทัล การสื่อสาร และสังคม' } },
    { id: 4, name: { en: 'Digital Innovation and Transformation', th: 'นวัตกรรมดิจิทัลและการเปลี่ยนผ่านสู่ดิจิทัล' } },
    { id: 5, name: { en: 'Artificial Intelligence and Intelligent Technologies', th: 'ปัญญาประดิษฐ์และเทคโนโลยีอัจฉริยะ' } },
];
const trackById = Object.fromEntries(mockTracks.map((t) => [t.id, t]));

// Deterministic random numbers, so every reload shows the same sample
function seededRandom(seed) {
    return () => {
        seed = (seed + 0x6d2b79f5) | 0;
        let r = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r;
        return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
    };
}

// ---- People and papers of demo.json (same rules as DemoSubmissionSeeder) ----

const DAY = 86400000;
const NOW = Date.now();
// n days ago at the same time of day the seeder uses
const daysAgo = (n) => {
    const d = new Date(NOW - n * DAY);
    d.setHours(9 + (n % 8), (n * 7) % 60, 0, 0);
    return d.toISOString();
};
const titleEn = { 'ดร.': 'Dr.', 'ผศ.': 'Asst. Prof.', 'ผศ.ดร.': 'Asst. Prof. Dr.', 'รศ.': 'Assoc. Prof.', 'ศ.': 'Prof.', 'อ.': 'Lect.' };
const fullName = (p) => ({ th: `${p.title ?? ''}${p.th}`, en: p.title ? `${titleEn[p.title]} ${p.en}` : p.en });
const emailOf = (key) => `${key}@skdiit.test`;

const people = demo.people.map((p) => ({ ...p, name: fullName(p), email: emailOf(p.key), registeredAt: daysAgo(p.registered) }));
const personByKey = Object.fromEntries(people.map((p) => [p.key, p]));
const reviewerByKey = Object.fromEntries(demo.reviewers.map((r) => [r.key, { ...r, name: fullName(r), email: emailOf(r.key) }]));
const editor = { name: { en: 'Demo Editor', th: 'Demo Editor' }, email: 'editor@skdiit.test', role: 'editor' };
const journal = { name: { en: 'Demo Journal', th: 'Demo Journal' }, email: 'journal@skdiit.test', role: 'journal' };
const admin = { name: { en: 'Administrator', th: 'Administrator' }, email: 'wongnarin.s@msu.ac.th', role: 'admin' };

/*
 * Papers: { code, author (person), track, status, title, submittedAt, steps: [{ kind, at, ... }] }
 * Step kinds and arguments as in DemoSubmissionSeeder::apply().
 */
const papers = demo.papers.map((p, i) => ({
    code: `SKDIIT27-${String(i + 1).padStart(4, '0')}`,
    author: personByKey[p.author],
    track: trackById[p.track],
    status: p.status,
    title: { th: p.title[0], en: p.title[1] },
    submittedAt: daysAgo(p.submitted),
    steps: p.steps.map(([kind, days, ...args]) => ({ kind, at: daysAgo(days), days, args })),
}));
const paperByAuthor = Object.fromEntries(papers.map((p) => [p.author.key, p]));
const stepOf = (paper, kind) => paper.steps.filter((s) => s.kind === kind).at(-1);
const PRESENTING = ['accepted', 'camera_ready', 'journal_accepted'];

// ---- Registrants (public list and the admin page) ----

export const registrants = [...people]
    .sort((a, b) => a.registered - b.registered)
    .map((p, i) => ({
        id: people.length - i,
        name: p.name,
        email: p.email,
        affiliation: p.affiliation,
        attend: true,
        submitted: p.participation === 'attend_submit',
        registeredAt: p.registeredAt,
    }));

/*
 * Backend view of each registrant, shaped like the users table (participation attend | attend_submit)
 * plus the paper's status (App\Enums\SubmissionStatus) from the same sample papers.
 */
export const adminRegistrants = registrants.map((row) => {
    const person = people.find((p) => p.email === row.email);
    const paper = paperByAuthor[person.key];
    return {
        id: row.id,
        name: row.name,
        email: row.email,
        affiliation: row.affiliation,
        participation: person.participation,
        paper: paper?.title ?? null,
        paperStatus: paper?.status ?? null,
        active: true,
        verified: person.verified, // signed in at least once with the emailed password (users.email_verified_at)
        phone: person.phone,
        registeredAt: row.registeredAt,
    };
});

export const registrantStats = {
    total: registrants.length,
    attend: registrants.filter((r) => r.attend).length,
    submitted: registrants.filter((r) => r.submitted).length,
    underReview: papers.filter((p) => ['in_review', 'revision_minor', 'revision_major', 'revised'].includes(p.status)).length,
};

// ---- Publication (papers that will present) ----

/*
 * { id, code, title, author, email, track, status, route: conference | journal, acceptedAt,
 *   cameraReady: { fileName, size, at } | null, presentation: { at, room } | null, slides: { fileName, size, at } | null }
 * status uses App\Enums\SubmissionStatus: accepted | camera_ready (conference track) | journal_accepted
 */
export const publicationQueue = papers.filter((p) => PRESENTING.includes(p.status)).map((p, i) => {
    const accepted = p.status === 'journal_accepted' ? stepOf(p, 'journalResult') : stepOf(p, 'decide');
    const camera = stepOf(p, 'cameraReady');
    const present = stepOf(p, 'present');
    const slides = stepOf(p, 'slides');
    return {
        id: i + 1,
        code: p.code,
        title: p.title,
        author: p.author.name,
        email: p.author.email,
        track: p.track,
        status: p.status,
        route: p.status === 'journal_accepted' ? 'journal' : 'conference',
        acceptedAt: accepted.at,
        cameraReady: camera ? { fileName: `camera-ready-${p.code}.pdf`, size: 1200, at: camera.at } : null,
        presentation: present ? { at: present.args[0].replace(' ', 'T'), room: present.args[1] } : null,
        slides: slides ? { fileName: `slides-${p.code}.pdf`, size: 1100, at: slides.at } : null,
    };
});

// ---- Conference event: presentation sessions (oral, onsite) ----

export const presentationRooms = [
    { id: 1, name: { en: 'Room 301, IT Building', th: 'ห้อง 301 อาคาร IT' }, capacity: 60 },
    { id: 2, name: { en: 'Room 302, IT Building', th: 'ห้อง 302 อาคาร IT' }, capacity: 60 },
    { id: 3, name: { en: 'Room 405, IT Building', th: 'ห้อง 405 อาคาร IT' }, capacity: 40 },
    { id: 4, name: { en: 'Main Hall, 2nd floor', th: 'ห้องประชุมใหญ่ ชั้น 2' }, capacity: 200 },
];

// Time blocks of the conference day (follow the public programme)
export const presentationBlocks = [
    { id: 1, label: { en: 'Session 1', th: 'ช่วงที่ 1' }, start: '10:45', end: '12:00' },
    { id: 2, label: { en: 'Session 2', th: 'ช่วงที่ 2' }, start: '13:00', end: '15:00' },
];

/*
 * Sessions: one room and one track per time block, with a chair and papers in running order.
 * { id, block, room, track, chair, minutes (per paper), papers: [{ code, title, author, slides }] }
 * Built from the sample papers that have a slot; unscheduledPapers: papers that will present but have no slot yet.
 */
function buildSessions() {
    const chairs = ['rv.orawan', 'rv.preecha', 'rv.suda', 'rv.nattaya'].map((key) => reviewerByKey[key].name);
    const entry = (p) => ({ code: p.code, title: p.title, author: p.author.name, slides: !!stepOf(p, 'slides') });
    const sessions = [];
    papers.filter((p) => PRESENTING.includes(p.status) && stepOf(p, 'present')).forEach((p) => {
        const [when, roomName] = stepOf(p, 'present').args;
        const time = when.slice(11, 16);
        const block = presentationBlocks.find((b) => time >= b.start && time < b.end) ?? presentationBlocks[0];
        const room = presentationRooms.find((r) => r.name.th === roomName) ?? presentationRooms[0];
        let session = sessions.find((s) => s.block === block.id && s.room === room.id);
        if (!session) {
            session = { id: sessions.length + 1, block: block.id, room: room.id, track: p.track.id, chair: chairs[sessions.length % chairs.length], minutes: 15, papers: [] };
            sessions.push(session);
        }
        session.papers.push(entry(p));
    });
    const unscheduled = papers.filter((p) => PRESENTING.includes(p.status) && !stepOf(p, 'present')).map(entry);
    return { sessions, unscheduled };
}

const builtSessions = buildSessions();
export const presentationSessions = builtSessions.sessions;
export const unscheduledPapers = builtSessions.unscheduled;

// ---- Notifications (mock until email/SMTP is decided; every email the system sends also becomes one of these) ----

const minutesAgo = (n) => new Date(NOW - n * 60 * 1000).toISOString();
const note = (id, type, icon, at, read, href, title, body) => ({ id, type, icon, at, read, href, title, body });
const P = Object.fromEntries(papers.map((p) => [p.code.slice(-2), p])); // P['11'] = SKDIIT27-0011
const due = (paper) => {
    const step = stepOf(paper, 'decide');
    const d = new Date(NOW + step.args[2] * DAY);
    return { en: d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }), th: d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' }) };
};

/*
 * By access role (User::accessRole()). type: paper | review | system.
 * { id, type, icon, at, read, href, title: { en, th }, body: { en, th } }
 */
export const notificationsByRole = {
    // Demo Author: SKDIIT27-0011
    author: [
        note(1, 'paper', 'pen', stepOf(P['11'], 'decide').at, false, '/admin/my-submissions', { en: 'Minor revision requested', th: 'บทความต้องแก้ไขเล็กน้อย' }, { en: `SKDIIT27-0011: please resubmit by ${due(P['11']).en}.`, th: `SKDIIT27-0011: กรุณาส่งฉบับแก้ไขภายใน ${due(P['11']).th}` }),
        note(2, 'paper', 'newspaper', stepOf(P['11'], 'journalSelect').at, true, '/admin/my-submissions', { en: 'Moved to the conference process', th: 'บทความเข้าสู่กระบวนการ Conference' }, { en: 'SKDIIT27-0011 continues with editor screening and peer review.', th: 'SKDIIT27-0011 เข้าสู่การคัดกรองของบรรณาธิการและการประเมิน' }),
        note(3, 'paper', 'paper-plane', P['11'].submittedAt, true, '/admin/my-submissions', { en: 'Paper received', th: 'ได้รับบทความแล้ว' }, { en: 'SKDIIT27-0011 was submitted. You can edit it until the deadline.', th: 'ส่งบทความ SKDIIT27-0011 แล้ว แก้ไขได้จนถึงวันปิดรับ' }),
    ],
    // Demo Reviewer: SKDIIT27-0008 to review, 0009 and 0011 sent
    reviewer: [
        note(1, 'review', 'clipboard-check', stepOf(P['08'], 'assign').at, false, '/admin/reviews', { en: 'New paper to review', th: 'มีบทความใหม่ให้ประเมิน' }, { en: 'SKDIIT27-0008 was assigned to you.', th: 'คุณได้รับมอบหมายบทความ SKDIIT27-0008' }),
        note(2, 'review', 'circle-check', stepOf(P['09'], 'review').at, true, '/admin/reviews', { en: 'Review received', th: 'ส่งผลประเมินแล้ว' }, { en: 'Thank you: your review of SKDIIT27-0009 was sent.', th: 'ขอบคุณ ส่งผลประเมิน SKDIIT27-0009 แล้ว' }),
    ],
    editor: [
        note(1, 'review', 'user-xmark', stepOf(P['10'], 'unavailable').at, false, '/admin/submissions?queue=unavailable', { en: 'A reviewer cannot review', th: 'ผู้ประเมินแจ้งไม่สะดวกประเมิน' }, { en: 'Kittisak Phandee cannot review SKDIIT27-0010. Please assign someone else.', th: 'กิตติศักดิ์ พันธ์ดี ไม่สะดวกประเมิน SKDIIT27-0010 กรุณามอบหมายผู้ประเมินแทน' }),
        note(2, 'paper', 'rotate', stepOf(P['12'], 'resubmit').at, false, '/admin/submissions?queue=revised', { en: 'Revision received', th: 'ได้รับฉบับแก้ไข' }, { en: 'SKDIIT27-0012 was revised. Decide or start a new review round.', th: 'SKDIIT27-0012 ส่งฉบับแก้ไขแล้ว ตัดสินได้เลยหรือส่งประเมินรอบใหม่' }),
        note(3, 'review', 'clipboard-check', stepOf(P['09'], 'review').at, false, '/admin/submissions?queue=readyToDecide', { en: 'All reviews are in', th: 'ผู้ประเมินส่งผลครบแล้ว' }, { en: 'SKDIIT27-0009: 2 of 2 reviews received. Ready for your decision.', th: 'SKDIIT27-0009: ผู้ประเมินส่งผลครบ 2/2 พร้อมให้ตัดสิน' }),
        note(4, 'paper', 'newspaper', stepOf(P['05'], 'journalSelect').at, true, '/admin/submissions?queue=screening', { en: 'Moved to the conference process', th: 'บทความเข้าสู่กระบวนการ Conference' }, { en: 'The journal passed SKDIIT27-0005 on. It is waiting for screening.', th: 'Journal ส่งต่อ SKDIIT27-0005 เข้ากระบวนการ Conference บทความรอคัดกรอง' }),
    ],
    journal: [
        note(1, 'paper', 'inbox', P['02'].submittedAt, false, '/admin/journal?tab=waiting', { en: 'Papers to select', th: 'มีบทความรอคัดเลือก' }, { en: 'SKDIIT27-0001 and SKDIIT27-0002 are waiting for your selection.', th: 'SKDIIT27-0001 และ SKDIIT27-0002 รอการคัดเลือก' }),
        note(2, 'paper', 'paper-plane', stepOf(P['03'], 'journalSelect').at, true, '/admin/journal?tab=selected', { en: 'Waiting for the journal’s answer', th: 'รอผลจากวารสาร' }, { en: 'SKDIIT27-0003 was selected. Record the journal’s final answer when it arrives.', th: 'SKDIIT27-0003 คัดเลือกแล้ว บันทึกผลสุดท้ายเมื่อวารสารแจ้งกลับ' }),
    ],
    admin: [
        note(1, 'system', 'envelope', minutesAgo(30), false, '/admin/notifications?tab=emails', { en: 'An email could not be sent', th: 'ส่งอีเมลไม่สำเร็จ' }, { en: 'The sign-in password to chaiwat@skdiit.test bounced. Check the email log.', th: 'อีเมลรหัสผ่านถึง chaiwat@skdiit.test ส่งไม่สำเร็จ ตรวจสอบที่ประวัติอีเมล' }),
        note(2, 'system', 'user-plus', personByKey.siriporn.registeredAt, false, '/admin/registrants', { en: 'New registrant today', th: 'วันนี้มีผู้ลงทะเบียนใหม่' }, { en: 'Siriporn Phimthai registered (attend only) and has not signed in yet.', th: 'ศิริพร พิมพ์ไทย ลงทะเบียน (เข้าร่วมงาน) และยังไม่ได้เข้าสู่ระบบ' }),
        note(3, 'review', 'user-xmark', stepOf(P['10'], 'unavailable').at, true, '/admin/submissions?queue=unavailable', { en: 'A reviewer cannot review', th: 'ผู้ประเมินแจ้งไม่สะดวกประเมิน' }, { en: 'Kittisak Phandee cannot review SKDIIT27-0010.', th: 'กิตติศักดิ์ พันธ์ดี ไม่สะดวกประเมิน SKDIIT27-0010' }),
    ],
};

/*
 * Every email the system sent (admin): { id, to, name, subject, template, body: { th: [lines], en: [lines] }, button,
 * at, status: sent | failed | queued, error, resent (times sent again) }
 * One per workflow step of the sample papers (and the sign-in password of every registrant).
 * Template names match the planned Mailables (LoginPassword is real; the others come with SMTP).
 */
export const emailLog = (() => {
    const templates = {
        LoginPassword: {
            subject: { en: 'Your SKDIIT 2027 sign-in password', th: 'รหัสผ่านสำหรับเข้าสู่ระบบ SKDIIT 2027' },
            body: () => ({
                th: ['ลงทะเบียนเรียบร้อยแล้ว ใช้รหัสผ่านด้านล่างนี้คู่กับอีเมลของคุณเพื่อเข้าสู่ระบบ', 'รหัสผ่าน: ••••••  (ระบบไม่เก็บรหัสผ่านไว้ในประวัติ)'],
                en: ['Thank you for registering. Use the password below with your email to sign in.', 'Password: ••••••  (passwords are never kept in the log)'],
            }),
            button: { th: 'เข้าสู่ระบบ', en: 'Sign in' },
        },
        PaperReceived: {
            subject: { en: 'We received your paper', th: 'ได้รับบทความของคุณแล้ว' },
            body: (c) => ({
                th: [`ระบบได้รับบทความ ${c} ของคุณแล้ว`, 'คุณแก้ไขบทความและเปลี่ยนไฟล์ได้จนถึงวันปิดรับบทความ'],
                en: [`We received your paper ${c}.`, 'You can edit it and replace the files until the submission deadline.'],
            }),
            button: { th: 'ดูบทความของฉัน', en: 'View my papers' },
        },
        JournalSelection: {
            subject: { en: 'Your paper and the journal', th: 'ผลการพิจารณาของ Journal' },
            body: (c, outcome) => (outcome === 'select'
                ? { th: [`Journal คัดเลือกบทความ ${c} ของคุณแล้ว`, 'กรุณาส่งบทความที่เว็บไซต์ของวารสาร (ลิงก์อยู่ในหน้าบทความ)'], en: [`The journal selected your paper ${c}.`, 'Please submit it on the journal’s website (the link is on your paper’s page).'] }
                : { th: [`บทความ ${c} เข้าสู่กระบวนการ Conference`, 'บรรณาธิการจะคัดกรองและส่งให้ผู้ประเมินต่อไป'], en: [`Your paper ${c} moves on to the conference process.`, 'The editors will screen it and send it to reviewers.'] }),
            button: { th: 'ดูบทความของฉัน', en: 'View my papers' },
        },
        ScreeningResult: {
            subject: { en: 'Screening result of your paper', th: 'ผลการคัดกรองบทความ' },
            body: (c, outcome) => ({
                th: [`ผลการคัดกรองบทความ ${c}: ${{ pass: 'ผ่าน', revise: 'ส่งกลับให้แก้ไข', reject: 'ไม่ผ่าน' }[outcome]}`, 'ดูรายละเอียดและความเห็นของบรรณาธิการได้ที่หน้าบทความ'],
                en: [`Screening result of ${c}: ${{ pass: 'passed', revise: 'sent back for revision', reject: 'not accepted' }[outcome]}.`, 'See the details and the editor’s comment on your paper’s page.'],
            }),
            button: { th: 'ดูรายละเอียด', en: 'View details' },
        },
        ReviewerAssigned: {
            subject: { en: 'A paper has been assigned to you', th: 'คุณได้รับมอบหมายบทความให้ประเมิน' },
            body: (c) => ({
                th: [`คุณได้รับมอบหมายให้ประเมินบทความ ${c}`, 'กรุณาส่งผลการประเมินภายในวันครบกำหนด'],
                en: [`You have been asked to review paper ${c}.`, 'Please send your evaluation by the due date.'],
            }),
            button: { th: 'ไปที่งานประเมิน', en: 'Open my reviews' },
        },
        EditorDecision: {
            subject: { en: 'Decision on your paper', th: 'ผลการพิจารณาบทความ' },
            body: (c, outcome) => ({
                th: [`บรรณาธิการตัดสินผลบทความ ${c} แล้ว: ${{ accept: 'ผ่านการประเมิน', minor: 'แก้ไขเล็กน้อย', major: 'แก้ไขมาก', reject: 'ไม่ผ่าน' }[outcome]}`, 'ดูรายละเอียดได้ที่หน้าบทความ'],
                en: [`The editor decided on ${c}: ${{ accept: 'accepted', minor: 'minor revision', major: 'major revision', reject: 'not accepted' }[outcome]}.`, 'See the details on your paper’s page.'],
            }),
            button: { th: 'ดูผลการพิจารณา', en: 'View the decision' },
        },
    };

    const list = [];
    const send = (template, person, at, arg, outcome, status = 'sent', error = null) => list.push({
        to: person.email, name: person.name, template, subject: templates[template].subject,
        body: templates[template].body(arg, outcome), button: templates[template].button, at, status, error, resent: 0,
    });
    people.forEach((p) => send('LoginPassword', p, p.registeredAt, null, null,
        p.key === 'chaiwat' ? 'failed' : p.key === 'siriporn' ? 'queued' : 'sent', p.key === 'chaiwat' ? '550 Mailbox not found' : null));
    papers.forEach((paper) => {
        send('PaperReceived', paper.author, paper.submittedAt, paper.code);
        paper.steps.forEach((s) => {
            if (s.kind === 'journalSelect') send('JournalSelection', paper.author, s.at, paper.code, s.args[0]);
            if (s.kind === 'screen') send('ScreeningResult', paper.author, s.at, paper.code, s.args[0]);
            if (s.kind === 'assign') send('ReviewerAssigned', reviewerByKey[s.args[0]], s.at, paper.code);
            if (s.kind === 'decide') send('EditorDecision', paper.author, s.at, paper.code, s.args[0]);
        });
    });
    return list.sort((a, b) => b.at.localeCompare(a.at)).map((e, i, all) => ({ id: all.length - i, ...e }));
})();

// ---- Reports and the admin home (counted from the same sample) ----

// Sign-ups per day for the last 30 days, ending today
function signupsPerDay(days) {
    return Array.from({ length: days }, (_, i) => {
        const n = days - 1 - i;
        return { date: new Date(NOW - n * DAY).toISOString().slice(0, 10), value: people.filter((p) => p.registered === n).length };
    });
}
const countBy = (list, key) => Object.entries(list.reduce((acc, x) => ({ ...acc, [key(x)]: (acc[key(x)] ?? 0) + 1 }), {})).sort((a, b) => b[1] - a[1]);

// Every review assignment of the sample, with when it was sent (or null), its due date and the recommendation
const assignments = papers.flatMap((paper) => {
    const rows = [];
    let round = 0;
    paper.steps.forEach((s) => {
        if (s.kind === 'screen' && s.args[0] === 'pass') round = 1;
        if (s.kind === 'round') round++;
        if (s.kind === 'assign') rows.push({ paper, round, reviewer: s.args[0], assignedDays: s.days, dueDays: s.days - s.args[1], sentDays: null, recommendation: null });
        if (s.kind === 'review') {
            const row = rows.find((r) => r.reviewer === s.args[0] && r.round === round);
            Object.assign(row, { sentDays: s.days, recommendation: s.args[1] });
        }
    });
    return rows;
});
const decided = (statuses) => papers.filter((p) => statuses.includes(p.status)).length;
const acceptedCount = decided(['accepted', 'camera_ready', 'journal_accepted']);

export const reportData = {
    registrants: {
        kpis: { total: people.length, attendOnly: people.filter((p) => p.participation === 'attend').length, authors: people.filter((p) => p.participation === 'attend_submit').length, notSignedIn: people.filter((p) => !p.verified).length },
        trend: signupsPerDay(30),
        byAffiliation: countBy(people, (p) => p.affiliation),
        byParticipation: countBy(people, (p) => p.participation),
    },
    papers: {
        kpis: { submitted: papers.length, inReview: decided(['in_review', 'revision_minor', 'revision_major', 'revised']), accepted: acceptedCount, rejected: decided(['screening_rejected', 'rejected']) },
        byTrack: countBy(papers, (p) => p.track.id).map(([id, n]) => [Number(id), n]),
        byStatus: ['submitted', 'journal_selected', 'journal_accepted', 'screening', 'screening_revision', 'screening_rejected',
            'in_review', 'revision_minor', 'revision_major', 'revised', 'accepted', 'rejected', 'camera_ready'].map((s) => [s, decided([s])]),
        // Papers that will present, by route: accepted by the journal, or through the conference track
        routes: [['journal', decided(['journal_accepted'])], ['conference', decided(['accepted', 'camera_ready'])]],
    },
    reviews: (() => {
        const sent = assignments.filter((a) => a.sentDays !== null);
        const late = (a) => a.sentDays === null && a.dueDays > 0; // due date already passed (days ago)
        const avg = (rows) => (rows.length ? Math.round((rows.reduce((n, a) => n + (a.assignedDays - a.sentDays), 0) / rows.length) * 10) / 10 : 0);
        return {
            kpis: { assigned: assignments.length, done: sent.length, avgDays: avg(sent), overdue: assignments.filter(late).length },
            recommendations: ['accept', 'minor', 'major', 'reject'].map((r) => [r, sent.filter((a) => a.recommendation === r).length]),
            reviewers: demo.reviewers.map((r) => {
                const mine = assignments.filter((a) => a.reviewer === r.key);
                return { name: reviewerByKey[r.key].name, tracks: r.tracks, assigned: mine.length, done: mine.filter((a) => a.sentDays !== null).length, avgDays: avg(mine.filter((a) => a.sentDays !== null)), late: mine.filter(late).length };
            }),
        };
    })(),
};

/*
 * Admin home: what to do now (counts link to the page where the work happens). The paper numbers are replaced
 * by real ones (DashboardController); the rest is counted from the sample.
 */
export const dashboardData = {
    admin: {
        kpis: { registrantsToday: people.filter((p) => p.registered === 0).length, waitingScreening: 0, overdueReviews: 0 },
        tasks: [
            { key: 'screening', icon: 'file-lines', count: 0, href: '/admin/submissions' },
            { key: 'overdueReviews', icon: 'triangle-exclamation', count: 0, href: '/admin/submissions' },
            { key: 'failedEmails', icon: 'envelope', count: emailLog.filter((e) => e.status === 'failed').length, href: '/admin/notifications?tab=emails' },
            { key: 'notSignedIn', icon: 'user-clock', count: people.filter((p) => !p.verified).length, href: '/admin/registrants?account=unverified' },
            { key: 'noSession', icon: 'person-chalkboard', count: unscheduledPapers.length, href: '/admin/presentations' },
        ],
        // Latest activity of the sample: sign-ups and paper steps
        activity: [
            ...people.map((p) => ({ icon: 'user-plus', at: p.registeredAt, text: { en: `${p.name.en} registered`, th: `${p.name.th} ลงทะเบียน` } })),
            ...papers.flatMap((paper) => [
                { icon: 'file-arrow-up', at: paper.submittedAt, text: { en: `${paper.code} was submitted`, th: `มีบทความใหม่ ${paper.code}` } },
                ...paper.steps.filter((s) => ['journalSelect', 'journalResult', 'screen', 'review', 'decide'].includes(s.kind)).map((s) => ({
                    icon: { journalSelect: 'newspaper', journalResult: 'newspaper', screen: 'clipboard-check', review: 'clipboard-check', decide: 'gavel' }[s.kind],
                    at: s.at,
                    text: {
                        journalSelect: { en: `Journal ${s.args[0] === 'select' ? 'selected' : 'passed on'} ${paper.code}`, th: `Journal ${s.args[0] === 'select' ? 'คัดเลือก' : 'ส่งต่อ'} ${paper.code}` },
                        journalResult: { en: `Journal answer recorded for ${paper.code}`, th: `บันทึกผลจากวารสาร ${paper.code}` },
                        screen: { en: `Demo Editor screened ${paper.code}`, th: `Demo Editor คัดกรอง ${paper.code}` },
                        review: { en: `${reviewerByKey[s.args[0]]?.name.en} sent a review for ${paper.code}`, th: `${reviewerByKey[s.args[0]]?.name.th} ส่งผลประเมิน ${paper.code}` },
                        decide: { en: `Demo Editor decided on ${paper.code}`, th: `Demo Editor ตัดสินผล ${paper.code}` },
                    }[s.kind],
                })),
            ]),
        ].sort((a, b) => b.at.localeCompare(a.at)).slice(0, 6),
    },
};

// ---- Activity log (mock; will read submission_events, account changes, sign-ins and impersonation) ----

/*
 * { id, at, category, action, actor: { name, role, email }, target, detail: { en, th } | null, ip, device, flagged }
 * category: auth | account | paper | review | system | impersonation
 * flagged marks events worth a second look (failed sign-ins, impersonation, deletions).
 * Paper and account events replay the sample; sign-ins are spread over the same people.
 */
export const activityLog = (() => {
    const rand = seededRandom(43);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const ip = () => pick(['202.28.32.', '202.28.34.', '49.228.', '1.46.', '171.97.']) + `${Math.floor(rand() * 250) + 2}`;
    const device = () => pick(['Chrome · Windows', 'Chrome · macOS', 'Safari · iPhone', 'Edge · Windows', 'Chrome · Android', 'Firefox · Windows']);
    const actor = (who, role) => ({ name: who.name.th, role, email: who.email });
    const author = (p) => actor(p, p.participation === 'attend' ? 'attendee' : 'author');
    const reviewer = (key) => actor(reviewerByKey[key], 'reviewer');
    const decision = { pass: { en: 'Passed screening', th: 'ผ่านการคัดกรอง' }, revise: { en: 'Sent back for revision', th: 'ส่งกลับให้แก้ไข' }, reject: { en: 'Rejected at screening', th: 'ไม่ผ่านการคัดกรอง' } };
    const outcome = { accept: { en: 'Accepted', th: 'ตอบรับ' }, minor: { en: 'Minor revision', th: 'แก้ไขเล็กน้อย' }, major: { en: 'Major revision', th: 'แก้ไขมาก' }, reject: { en: 'Rejected', th: 'ไม่ผ่าน' } };

    const events = [];
    const add = (at, category, action, who, target = null, detail = null, flagged = false) => events.push({ at, category, action, actor: who, target, detail, flagged });

    people.forEach((p) => {
        add(p.registeredAt, 'account', 'registered', author(p), p.email, p.participation === 'attend' ? { en: 'Attend only', th: 'เข้าร่วมงานอย่างเดียว' } : { en: 'Attend + submit', th: 'เข้าร่วม + ส่งผลงาน' });
        if (p.verified) add(new Date(new Date(p.registeredAt).getTime() + 40 * 60000).toISOString(), 'auth', 'login', author(p));
    });
    add(daysAgo(1), 'system', 'email_failed', { name: 'System', role: 'system', email: '' }, 'chaiwat@skdiit.test', { en: '550 Mailbox not found', th: '550 ไม่พบกล่องจดหมาย' }, true);
    add(daysAgo(2), 'auth', 'login_failed', { name: 'pimchanok@skdiit.test', role: 'author', email: 'pimchanok@skdiit.test' }, null, { en: '3 wrong passwords in a row', th: 'ใส่รหัสผ่านผิด 3 ครั้งติดกัน' }, true);
    add(daysAgo(3), 'impersonation', 'impersonation_start', actor(admin, 'admin'), 'author@skdiit.test', { en: 'Viewing as this user (read only)', th: 'ดูในฐานะผู้ใช้นี้ (ดูอย่างเดียว)' }, true);
    add(new Date(new Date(daysAgo(3)).getTime() + 6 * 60000).toISOString(), 'impersonation', 'impersonation_stop', actor(admin, 'admin'), 'author@skdiit.test', { en: 'Back to admin after 6 min', th: 'กลับเป็นแอดมินหลัง 6 นาที' }, true);
    add(daysAgo(20), 'system', 'settings_changed', actor(admin, 'admin'), 'submission_deadline', { en: 'Deadline set', th: 'กำหนดวันปิดรับ' });
    demo.reviewers.forEach((r, i) => add(daysAgo(55 - i), 'account', 'staff_created', actor(admin, 'admin'), emailOf(r.key), { en: 'Role: reviewer', th: 'บทบาท: ผู้ประเมิน' }));

    papers.forEach((paper) => {
        add(paper.submittedAt, 'paper', 'submitted', author(paper.author), paper.code);
        paper.steps.forEach((s) => {
            const a = s.args;
            if (s.kind === 'updated') add(s.at, 'paper', 'updated', author(paper.author), paper.code, { en: 'Files replaced', th: 'เปลี่ยนไฟล์บทความ' });
            if (s.kind === 'journalSelect') add(s.at, 'paper', 'journal_screened', actor(journal, 'journal'), paper.code, a[0] === 'select' ? { en: 'Selected for the journal', th: 'คัดเลือกเข้าวารสาร' } : { en: 'Passed on to the conference', th: 'ส่งต่อกระบวนการ Conference' });
            if (s.kind === 'journalResult') add(s.at, 'paper', 'journal_result', actor(journal, 'journal'), paper.code, a[0] === 'accepted' ? { en: 'Journal accepted', th: 'วารสารตอบรับ' } : { en: 'Journal did not accept', th: 'วารสารไม่รับ' });
            if (s.kind === 'track') add(s.at, 'paper', 'track_changed', actor(editor, 'editor'), paper.code, { en: `→ Track ${a[0]}`, th: `→ กลุ่มสาขา ${a[0]}` });
            if (s.kind === 'screen') add(s.at, 'paper', 'screened', actor(editor, 'editor'), paper.code, decision[a[0]]);
            if (s.kind === 'assign') add(s.at, 'review', 'assigned', actor(editor, 'editor'), paper.code, { en: `Reviewer: ${reviewerByKey[a[0]].name.en}`, th: `ผู้ประเมิน: ${reviewerByKey[a[0]].name.th}` });
            if (s.kind === 'review') add(s.at, 'review', 'review_submitted', reviewer(a[0]), paper.code, outcome[a[1]]);
            if (s.kind === 'decide') add(s.at, 'paper', 'decided', actor(editor, 'editor'), paper.code, outcome[a[0]]);
        });
    });
    // Staff sign in now and then
    for (let d = 0; d < 30; d += 2) add(daysAgo(d), 'auth', 'login', pick([actor(admin, 'admin'), actor(editor, 'editor'), actor(journal, 'journal'), reviewer(pick(Object.keys(reviewerByKey)))]));

    return events
        .sort((a, b) => b.at.localeCompare(a.at))
        .map((e, i, all) => ({ id: all.length - i, ip: ip(), device: device(), ...e }));
})();
