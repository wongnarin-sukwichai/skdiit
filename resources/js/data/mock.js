/*
 * Mock data for the pages that do not read the database yet. Bilingual values use { en, th } and are rendered with tr().
 *
 * People and papers come from demo.json, the same file DemoSubmissionSeeder puts into the real database, so the
 * sample pages (registrants, publication, presentations, reports, notifications, email and activity logs) show the
 * same 20 registrants, 15 papers, codes and statuses as the real paper pages. Dates are relative to today.
 *
 * SHOW_SAMPLE = false (real-data testing, 2026-10-19): those pages show no data until each one reads the database.
 * Set it back to true together with `php artisan db:seed --class=DemoSubmissionSeeder` to present the sample again.
 */
import sample from './demo.json';

export const SHOW_SAMPLE = false;
const demo = SHOW_SAMPLE ? sample : { people: [], papers: [], reviewers: [] };

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

export const registrantStats = {
    total: registrants.length,
    attend: registrants.filter((r) => r.attend).length,
    submitted: registrants.filter((r) => r.submitted).length,
    underReview: papers.filter((p) => ['in_review', 'review_revision', 'revised'].includes(p.status)).length,
};


/*
 * Settings › กำหนดการ, timeline phase 1 (2026-10-20): assumed dates to show the client; nothing here is saved or enforced.
 * ScheduleTab replaces the rows the system already enforces with their real values (submission period, journal
 * selection deadline, slides cut-off, conference day). Not sample data, so SHOW_SAMPLE does not hide it.
 *   mode: lock = the step closes on its end date, warn = only a warning, info = shown only
 *   who: who does the work (all / author / journal / editor / reviewer)
 *   perItem: days each paper or reviewer gets inside the phase (per-paper deadlines never pass the phase end)
 *   milestone: a single day
 *   public: also listed in the important dates on the public site
 */
export const timelinePlan = [
    // Client dates (2026-11-12): submission 1 Nov–15 Dec 2026, peer review 16–28 Dec, notification 4 Jan 2027,
    // camera-ready 4–11 Jan, conference 27–28 Jan. The rows in between are fitted around them.
    { key: 'registration', icon: 'users', who: 'all', start: '2026-11-01', end: '2027-01-15', mode: 'lock', public: true },
    { key: 'submission', icon: 'file-arrow-up', who: 'author', start: '2026-11-01', end: '2026-12-15', mode: 'lock', public: true },
    { key: 'journalSelect', icon: 'newspaper', who: 'selection', start: '2026-12-16', end: '2026-12-20', mode: 'warn' },
    // Offers made during selection: each author has journal_answer_days to answer (ScheduleTab works the end out)
    { key: 'journalAnswer', icon: 'reply', who: 'author', start: '2026-12-16', end: '2026-12-27', mode: 'info' },
    { key: 'screening', icon: 'magnifying-glass', who: 'editor', start: '2026-12-16', end: '2026-12-27', mode: 'warn', perItem: { days: 3, unit: 'paper' } },
    { key: 'review', icon: 'user-check', who: 'reviewer', start: '2026-12-16', end: '2026-12-28', mode: 'warn', perItem: { days: 7, unit: 'reviewer' }, public: true },
    { key: 'notify', icon: 'bullhorn', who: 'editor', start: '2027-01-04', end: '2027-01-04', mode: 'info', milestone: true, public: true },
    { key: 'revision', icon: 'pen-to-square', who: 'author', start: '2027-01-04', end: '2027-01-10', mode: 'lock', perItem: { days: 7, unit: 'paper' } },
    { key: 'finalDecision', icon: 'gavel', who: 'editor', start: '2027-01-05', end: '2027-01-10', mode: 'warn' },
    { key: 'cameraReady', icon: 'file-circle-check', who: 'author', start: '2027-01-04', end: '2027-01-11', mode: 'lock', public: true },
    { key: 'scheduling', icon: 'table-columns', who: 'editor', start: '2027-01-12', end: '2027-01-20', mode: 'info' },
    { key: 'slides', icon: 'file-powerpoint', who: 'author', start: '2027-01-12', end: '2027-01-26', mode: 'lock' },
    { key: 'conference', icon: 'star', who: 'all', start: '2027-01-28', end: '2027-01-28', mode: 'info', milestone: true, public: true },
];
