/*
 * Mock data for the first design pass. Replace with server data once the backend is defined.
 * Bilingual values use { en, th } and are rendered with tr().
 */

export const conference = {
    date: '2027-01-28',
    venue: { en: 'Mahasarakham University', th: 'มหาวิทยาลัยมหาสารคาม' },
    email: 'skdiit@msu.ac.th',
    phone: '043-754-321',
};

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

export const statuses = ['confirmed', 'awaiting_submission', 'under_review', 'unconfirmed'];

// ---- Registrants (deterministic, so every reload shows the same list) ----

function seededRandom(seed) {
    return () => {
        seed = (seed + 0x6d2b79f5) | 0;
        let r = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r;
        return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
    };
}

const titles = {
    any: [
        { en: 'Dr.', th: 'ดร.' },
        { en: 'Asst. Prof. Dr.', th: 'ผศ.ดร.' },
        { en: 'Assoc. Prof.', th: 'รศ.' },
        { en: 'Lect.', th: 'อ.' },
    ],
    m: [{ en: 'Mr.', th: 'นาย' }],
    f: [{ en: 'Ms.', th: 'นางสาว' }, { en: 'Mrs.', th: 'นาง' }],
};

const firstNames = [
    ['m', 'Somchai', 'สมชาย'], ['f', 'Kamonwan', 'กมลวรรณ'], ['m', 'Worawut', 'วรวุฒิ'],
    ['f', 'Sudarat', 'สุดารัตน์'], ['m', 'Thanakorn', 'ธนากร'], ['f', 'Jiraporn', 'จิราภรณ์'],
    ['m', 'Ekachai', 'เอกชัย'], ['f', 'Piyanuch', 'ปิยะนุช'], ['m', 'Suradech', 'สุรเดช'],
    ['f', 'Kanlayarat', 'กัลยรัตน์'], ['m', 'Nattapong', 'ณัฐพงศ์'], ['f', 'Pimchanok', 'พิมพ์ชนก'],
    ['m', 'Anucha', 'อนุชา'], ['f', 'Wilaiwan', 'วิไลวรรณ'], ['m', 'Chaiwat', 'ชัยวัฒน์'],
    ['f', 'Siriporn', 'ศิริพร'],
];

const lastNames = [
    ['Jaidee', 'ใจดี'], ['Srisuk', 'ศรีสุข'], ['Kanha', 'กันหา'], ['Makmee', 'มากมี'],
    ['Charoenphon', 'เจริญผล'], ['Saengduean', 'แสงเดือน'], ['Malila', 'มะลิลา'],
    ['Inthong', 'อินทร์ทอง'], ['Phatthanawong', 'พัฒนวงศ์'], ['Phimthai', 'พิมพ์ไทย'],
    ['Boonmee', 'บุญมี'], ['Thongdee', 'ทองดี'], ['Wongsa', 'วงศ์ษา'], ['Rattanakul', 'รัตนกุล'],
];

const paperTitles = [
    { en: 'Gamified Learning Platform for Primary Mathematics', th: 'แพลตฟอร์มการเรียนรู้แบบเกมมิฟิเคชันสำหรับคณิตศาสตร์ระดับประถม' },
    { en: 'Generative AI as a Writing Tutor in Higher Education', th: 'ปัญญาประดิษฐ์เชิงสร้างสรรค์ในฐานะผู้ช่วยสอนการเขียนในระดับอุดมศึกษา' },
    { en: 'Learning Analytics Dashboard for At-Risk Students', th: 'แดชบอร์ดการวิเคราะห์การเรียนรู้สำหรับนักศึกษากลุ่มเสี่ยง' },
    { en: 'Digital Library Services in the Post-Pandemic Era', th: 'บริการห้องสมุดดิจิทัลในยุคหลังโรคระบาด' },
    { en: 'Augmented Reality Media for Local History Lessons', th: 'สื่อความเป็นจริงเสริมสำหรับบทเรียนประวัติศาสตร์ท้องถิ่น' },
    { en: 'Blended Learning Model to Enhance Digital Literacy', th: 'รูปแบบการเรียนแบบผสมผสานเพื่อเสริมสร้างการรู้ดิจิทัล' },
    { en: 'Chatbot Support for University Admission Services', th: 'แชตบอตสนับสนุนงานบริการรับสมัครนักศึกษา' },
    { en: 'Micro-credentials for Teacher Professional Development', th: 'ประกาศนียบัตรย่อยเพื่อการพัฒนาวิชาชีพครู' },
];

function buildRegistrants(count) {
    const rand = seededRandom(2027);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const realAffiliations = affiliations.filter((a) => a.key !== 'other');
    const registeredAt = new Date('2026-10-06T16:00:00+07:00');

    return Array.from({ length: count }, (_, i) => {
        const [gender, firstEn, firstTh] = pick(firstNames);
        const [lastEn, lastTh] = pick(lastNames);
        const title = pick(rand() < 0.55 ? titles.any : titles[gender]);
        const submitted = rand() < 0.56;
        const attend = submitted ? rand() < 0.85 : true;
        const status = submitted
            ? (rand() < 0.65 ? 'under_review' : 'confirmed')
            : (rand() < 0.7 ? 'awaiting_submission' : 'unconfirmed');

        registeredAt.setHours(registeredAt.getHours() - Math.ceil(rand() * 14));

        return {
            id: count - i,
            name: { en: `${title.en} ${firstEn} ${lastEn}`, th: `${title.th}${firstTh} ${lastTh}` },
            email: `${firstEn.toLowerCase()}.${lastEn.slice(0, 2).toLowerCase()}${i}@example.ac.th`,
            affiliation: pick(realAffiliations).key,
            attend,
            submitted,
            status,
            paper: submitted ? pick(paperTitles) : null,
            registeredAt: registeredAt.toISOString(),
        };
    });
}

/*
 * Payment progress shown in the public list (finance is on hold until fees are confirmed; sample only).
 * A separate seed keeps the other fields unchanged.
 */
export const paymentStatuses = ['awaiting_payment', 'pending_review', 'slip_rejected', 'paid'];

function withPayment(list) {
    const rand = seededRandom(99);
    return list.map((row) => {
        const r = rand();
        const payment = r < 0.45 ? 'paid' : r < 0.7 ? 'pending_review' : r < 0.9 ? 'awaiting_payment' : 'slip_rejected';
        return { ...row, payment };
    });
}

export const registrants = withPayment(buildRegistrants(30));

/*
 * Backend view of each registrant, shaped like the users table (participation attend | attend_submit)
 * plus the latest paper status (App\Enums\SubmissionStatus). A second seed keeps the public list above unchanged.
 */
function buildAdminRegistrants(list) {
    const rand = seededRandom(7);
    const pick = (items) => items[Math.floor(rand() * items.length)];
    const reviewing = ['submitted', 'screening_revision', 'in_review', 'in_review', 'revision_minor', 'revision_major', 'revised'];
    const decided = ['accepted', 'option_a', 'camera_ready', 'option_b', 'journal_accepted', 'rejected'];

    return list.map((row) => ({
        id: row.id,
        name: row.name,
        email: row.email,
        affiliation: row.affiliation,
        // No paper yet: most attend only, some chose to submit but have not sent the paper
        participation: row.submitted || rand() < 0.3 ? 'attend_submit' : 'attend',
        paper: row.paper,
        paperStatus: row.submitted ? pick(row.status === 'under_review' ? reviewing : decided) : null,
        payment: row.payment, // finance sees this instead of the paper status
        active: rand() > 0.03,
        verified: rand() > 0.15, // signed in at least once with the emailed password (users.email_verified_at)
        phone: rand() < 0.75 ? `0${[8, 9, 6][Math.floor(rand() * 3)]}${String(Math.floor(rand() * 1e8)).padStart(8, '0')}`.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3') : null,
        registeredAt: row.registeredAt,
    }));
}

export const adminRegistrants = buildAdminRegistrants(registrants);

export const registrantStats = {
    total: registrants.length,
    attend: registrants.filter((r) => r.attend).length,
    submitted: registrants.filter((r) => r.submitted).length,
    underReview: registrants.filter((r) => r.status === 'under_review').length,
};

// ---- Frontend content ----

export const importantDates = [
    { date: '2026-09-01', label: { en: 'Registration and paper submission open', th: 'เปิดลงทะเบียนและรับผลงาน' } },
    { date: '2026-11-30', label: { en: 'Full paper submission deadline', th: 'วันสุดท้ายของการส่งบทความฉบับสมบูรณ์' } },
    { date: '2026-12-20', label: { en: 'Notification of acceptance', th: 'ประกาศผลการพิจารณาบทความ' } },
    { date: '2027-01-08', label: { en: 'Camera-ready paper deadline', th: 'ส่งบทความฉบับแก้ไขสมบูรณ์' } },
    { date: '2027-01-15', label: { en: 'Registration closes', th: 'ปิดการลงทะเบียน' } },
    { date: '2027-01-28', label: { en: 'Conference day', th: 'วันจัดงานประชุมวิชาการ' } },
];

export const program = [
    { time: '08:00 – 09:00', label: { en: 'Registration', th: 'ลงทะเบียน' } },
    { time: '09:00 – 09:30', label: { en: 'Opening ceremony', th: 'พิธีเปิด' } },
    { time: '09:30 – 10:30', label: { en: 'Keynote: Digital Innovation for Future Learning', th: 'ปาฐกถาพิเศษ: นวัตกรรมดิจิทัลเพื่อการเรียนรู้แห่งอนาคต' } },
    { time: '10:45 – 12:00', label: { en: 'Oral presentations (Session 1)', th: 'นำเสนอผลงานภาคบรรยาย (ช่วงที่ 1)' } },
    { time: '13:00 – 15:00', label: { en: 'Oral & poster presentations (Session 2)', th: 'นำเสนอผลงานภาคบรรยายและโปสเตอร์ (ช่วงที่ 2)' } },
    { time: '15:15 – 16:00', label: { en: 'Awards and closing', th: 'มอบรางวัลและพิธีปิด' } },
];

export const documents = [
    { icon: 'file-lines', type: 'DOCX', size: '84 KB', name: { en: 'Full paper template', th: 'แม่แบบบทความฉบับสมบูรณ์' } },
    { icon: 'file-lines', type: 'PDF', size: '312 KB', name: { en: 'Author guidelines', th: 'คำแนะนำสำหรับผู้เขียน' } },
    { icon: 'file-lines', type: 'PPTX', size: '1.2 MB', name: { en: 'Presentation slide template', th: 'แม่แบบสไลด์นำเสนอ' } },
    { icon: 'file-lines', type: 'PDF', size: '220 KB', name: { en: 'Poster guidelines', th: 'แนวทางการจัดทำโปสเตอร์' } },
    { icon: 'file-lines', type: 'PDF', size: '156 KB', name: { en: 'Conference announcement', th: 'ประกาศการจัดงานประชุมวิชาการ' } },
];

// ---- Payments (finance module; fees are not confirmed by the client yet, sample only) ----

/*
 * A paper owes a fee once it is set for publication: Option A (Proceeding) after acceptance,
 * Option B (journal) after the journal accepts. Amounts are placeholders until the client confirms.
 */
export const paymentFees = {
    a: 3000,
    b: 5000,
    // Fee settings (Settings › Fees, sample): early-bird prices, days to pay after a fee is issued, attendee fee
    earlyBird: { enabled: true, a: 2500, b: 4500, until: '2026-12-31' },
    dueDays: 30,
    attendee: { enabled: false, amount: 1000 },
};

const banks = ['KBank', 'SCB', 'Krungthai', 'Bangkok Bank', 'Krungsri', 'ttb'];

const rejectReasons = [
    { en: 'The transferred amount does not match the fee.', th: 'ยอดเงินที่โอนไม่ตรงกับค่าธรรมเนียม' },
    { en: 'The slip image is unclear. Please upload it again.', th: 'ภาพสลิปไม่ชัดเจน กรุณาอัปโหลดใหม่' },
    { en: 'The slip is for a different account.', th: 'สลิปโอนเข้าบัญชีอื่น ไม่ใช่บัญชีของงานประชุม' },
];

/*
 * One fee per paper: { id, ref, name, email, affiliation, paperCode, paper, option, amount, status,
 * issuedAt, slip: { uploadedAt, transferredAt, amount, bank, fileName } | null, reviewedAt, reviewedBy, rejectReason }
 * status follows paymentStatuses above.
 */
function buildPayments(count) {
    const rand = seededRandom(31);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const realAffiliations = affiliations.filter((a) => a.key !== 'other');
    const day = 24 * 60 * 60 * 1000;
    const now = new Date('2026-10-14T10:00:00+07:00').getTime();
    const at = (daysAgo) => new Date(now - daysAgo * day - Math.floor(rand() * 8) * 3600 * 1000).toISOString();

    return Array.from({ length: count }, (_, i) => {
        const [gender, firstEn, firstTh] = pick(firstNames);
        const [lastEn, lastTh] = pick(lastNames);
        const title = pick(rand() < 0.6 ? titles.any : titles[gender]);
        const option = rand() < 0.65 ? 'a' : 'b';
        const amount = paymentFees[option];
        const r = rand();
        const status = r < 0.4 ? 'paid' : r < 0.65 ? 'pending_review' : r < 0.88 ? 'awaiting_payment' : 'slip_rejected';
        const issuedDaysAgo = 10 + Math.floor(rand() * 30);
        const slipDaysAgo = Math.max(0, issuedDaysAgo - 2 - Math.floor(rand() * 8));
        // A few slips under review show the wrong amount, so finance has something to catch
        const slipAmount = status === 'pending_review' && rand() < 0.25 ? amount - 500 : amount;

        return {
            id: count - i,
            ref: `INV-27-${String(count - i).padStart(4, '0')}`,
            name: { en: `${title.en} ${firstEn} ${lastEn}`, th: `${title.th}${firstTh} ${lastTh}` },
            email: `${firstEn.toLowerCase()}.${lastEn.slice(0, 2).toLowerCase()}${i}@example.ac.th`,
            affiliation: pick(realAffiliations).key,
            paperCode: `SKDIIT27-${String(40 + Math.floor(rand() * 160)).padStart(4, '0')}`,
            paper: pick(paperTitles),
            option,
            amount,
            status,
            issuedAt: at(issuedDaysAgo),
            slip: status === 'awaiting_payment' ? null : {
                uploadedAt: at(slipDaysAgo),
                transferredAt: at(slipDaysAgo),
                amount: slipAmount,
                bank: pick(banks),
                fileName: `slip-${firstEn.toLowerCase()}-${i + 1}.jpg`,
            },
            reviewedAt: ['paid', 'slip_rejected'].includes(status) ? at(Math.max(0, slipDaysAgo - 1)) : null,
            reviewedBy: ['paid', 'slip_rejected'].includes(status) ? 'Demo Finance' : null,
            rejectReason: status === 'slip_rejected' ? pick(rejectReasons) : null,
        };
    }).sort((a, b) => b.issuedAt.localeCompare(a.issuedAt));
}

export const adminPayments = buildPayments(36);

// One slip uploaded twice (same bank, transfer time and amount as a confirmed payment), for the duplicate check
(() => {
    const paid = adminPayments.find((p) => p.status === 'paid');
    const copy = adminPayments.find((p) => p.status === 'pending_review' && p.option === paid?.option && p.slip.amount === p.amount);
    if (paid && copy) Object.assign(copy.slip, { bank: paid.slip.bank, transferredAt: paid.slip.transferredAt, amount: paid.slip.amount });
})();

// The signed-in author's own fees (author view of the same module)
export const myPayments = [
    {
        id: 101, ref: 'INV-27-0101', paperCode: 'SKDIIT27-0014', option: 'a', amount: paymentFees.a, status: 'awaiting_payment',
        paper: { en: 'An Automatic Lighting Control Prototype for Classroom Buildings', th: 'ต้นแบบระบบควบคุมแสงสว่างอัตโนมัติในอาคารเรียน' },
        issuedAt: '2026-10-10T09:00:00+07:00', slip: null, reviewedAt: null, rejectReason: null,
    },
    {
        id: 102, ref: 'INV-27-0087', paperCode: 'SKDIIT27-0009', option: 'b', amount: paymentFees.b, status: 'slip_rejected',
        paper: { en: 'Digital Payment Adoption among Fresh-Market Vendors', th: 'การยอมรับเทคโนโลยีการชำระเงินดิจิทัลของผู้ค้าในตลาดสด' },
        issuedAt: '2026-09-28T09:00:00+07:00',
        slip: { uploadedAt: '2026-10-02T14:20:00+07:00', transferredAt: '2026-10-02T14:05:00+07:00', amount: 4500, bank: 'SCB', fileName: 'slip-oct-02.jpg' },
        reviewedAt: '2026-10-03T10:00:00+07:00', rejectReason: rejectReasons[0],
    },
    {
        id: 103, ref: 'INV-27-0052', paperCode: 'SKDIIT27-0003', option: 'a', amount: paymentFees.a, status: 'paid',
        paper: { en: 'A Machine-Learning Book Recommender for University Libraries', th: 'ระบบแนะนำหนังสือห้องสมุดด้วยการเรียนรู้ของเครื่อง' },
        issuedAt: '2026-09-15T09:00:00+07:00',
        slip: { uploadedAt: '2026-09-18T11:00:00+07:00', transferredAt: '2026-09-18T10:42:00+07:00', amount: 3000, bank: 'KBank', fileName: 'slip-sep-18.jpg' },
        reviewedAt: '2026-09-19T09:30:00+07:00', rejectReason: null,
    },
];

// ---- Publication and article archive (papers module; mock until these menus are wired to real data) ----

// Same names as the tracks table (admin renames them in the Tracks menu)
export const mockTracks = [
    { id: 1, name: { en: 'Smart Knowledge Library and Information', th: 'ห้องสมุดและสารสนเทศอัจฉริยะ' } },
    { id: 2, name: { en: 'Smart Spaces and Cyber-Physical Environments', th: 'พื้นที่อัจฉริยะและสภาพแวดล้อมไซเบอร์-กายภาพ' } },
    { id: 3, name: { en: 'Digital Media, Communication and Society', th: 'สื่อดิจิทัล การสื่อสาร และสังคม' } },
    { id: 4, name: { en: 'Digital Innovation and Transformation', th: 'นวัตกรรมดิจิทัลและการเปลี่ยนผ่านสู่ดิจิทัล' } },
    { id: 5, name: { en: 'Artificial Intelligence and Intelligent Technologies', th: 'ปัญญาประดิษฐ์และเทคโนโลยีอัจฉริยะ' } },
];

const archiveTitles = [
    ...paperTitles,
    { en: 'A Machine-Learning Book Recommender for University Libraries', th: 'ระบบแนะนำหนังสือห้องสมุดด้วยการเรียนรู้ของเครื่อง' },
    { en: 'Rice Leaf Disease Classification with Convolutional Neural Networks', th: 'การจำแนกโรคใบข้าวด้วยโครงข่ายประสาทเทียมแบบคอนโวลูชัน' },
    { en: 'An IoT Smart Classroom for Energy Saving', th: 'ห้องเรียนอัจฉริยะด้วยเซนเซอร์ IoT เพื่อประหยัดพลังงาน' },
    { en: 'Assessing Metadata Quality in Institutional Repositories', th: 'การประเมินคุณภาพเมทาดาทาในคลังสถาบัน' },
    { en: 'Real-Time Seat Availability Tracking in Libraries', th: 'ระบบติดตามที่นั่งว่างในห้องสมุดแบบเรียลไทม์' },
    { en: 'Digital Media Literacy of Older Adults in Urban Communities', th: 'การรู้เท่าทันสื่อดิจิทัลของผู้สูงอายุในชุมชนเมือง' },
    { en: 'Thai Fake News Detection with Transformer Models', th: 'การตรวจจับข่าวปลอมภาษาไทยด้วยโมเดล Transformer' },
    { en: 'Digital Storytelling for Community Tourism', th: 'การเล่าเรื่องดิจิทัลเพื่อส่งเสริมการท่องเที่ยวชุมชน' },
    { en: 'Forecasting Library Visits with Time-Series Models', th: 'การพยากรณ์จำนวนผู้ใช้บริการห้องสมุดด้วยอนุกรมเวลา' },
    { en: 'Success Factors of ERP Use in Universities', th: 'ปัจจัยความสำเร็จของการใช้ระบบ ERP ในมหาวิทยาลัย' },
    { en: 'Open Data Portals for Local Government Transparency', th: 'พอร์ทัลข้อมูลเปิดเพื่อความโปร่งใสขององค์กรปกครองส่วนท้องถิ่น' },
    { en: 'Virtual Reality Tours of Heritage Sites', th: 'การนำชมโบราณสถานด้วยความเป็นจริงเสมือน' },
    { en: 'Predicting Student Dropout with Learning Management System Logs', th: 'การทำนายการออกกลางคันของนักศึกษาจากข้อมูลระบบจัดการเรียนรู้' },
    { en: 'Smart Parking Guidance on a University Campus', th: 'ระบบนำทางที่จอดรถอัจฉริยะในมหาวิทยาลัย' },
    { en: 'Podcast Use for Lifelong Learning among Working Adults', th: 'การใช้พอดแคสต์เพื่อการเรียนรู้ตลอดชีวิตของวัยทำงาน' },
    { en: 'Cybersecurity Awareness of Small Business Owners', th: 'ความตระหนักด้านความมั่นคงปลอดภัยไซเบอร์ของผู้ประกอบการรายย่อย' },
];

const journals = [
    'วารสารสารสนเทศศาสตร์ มหาวิทยาลัยมหาสารคาม',
    'Journal of Information Science Research and Practice',
    'วารสารวิทยาการจัดการ',
    'วารสารเทคโนโลยีและสื่อสารการศึกษา',
];

function personName(rand) {
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const [gender, firstEn, firstTh] = pick(firstNames);
    const [lastEn, lastTh] = pick(lastNames);
    const title = pick(rand() < 0.6 ? titles.any : titles[gender]);
    return {
        name: { en: `${title.en} ${firstEn} ${lastEn}`, th: `${title.th}${firstTh} ${lastTh}` },
        email: `${firstEn.toLowerCase()}.${lastEn.slice(0, 2).toLowerCase()}@example.ac.th`,
    };
}

/*
 * Accepted papers of this year, by publication step:
 * { id, code, title, author, email, track, status, acceptedAt, option, cameraReady: { fileName, size, at } | null,
 *   journal: { name, sentAt, result: null | 'accepted' | 'rejected', at } | null, presentation: { at, room } | null }
 * status uses App\Enums\SubmissionStatus: accepted | option_a | camera_ready | option_b | journal_accepted
 */
function buildPublication(count) {
    const rand = seededRandom(57);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const day = 24 * 60 * 60 * 1000;
    const now = new Date('2026-10-14T10:00:00+07:00').getTime();
    const at = (daysAgo) => new Date(now - daysAgo * day).toISOString();
    const rooms = ['ห้อง 301 อาคาร IT', 'ห้อง 302 อาคาร IT', 'ห้องประชุมใหญ่ ชั้น 2', 'ห้อง 405 อาคาร IT'];
    const slots = ['2027-01-28T10:45', '2027-01-28T11:15', '2027-01-28T13:00', '2027-01-28T13:30', '2027-01-28T14:00', '2027-01-28T14:30'];

    return Array.from({ length: count }, (_, i) => {
        const person = personName(rand);
        const r = rand();
        const status = r < 0.15 ? 'accepted' : r < 0.4 ? 'option_a' : r < 0.65 ? 'camera_ready' : r < 0.85 ? 'option_b' : 'journal_accepted';
        const acceptedDaysAgo = 5 + Math.floor(rand() * 35);
        const option = status === 'accepted' ? null : ['option_a', 'camera_ready'].includes(status) ? 'a' : 'b';
        // Some Option A papers came back from a journal that declined them
        const journalDeclined = option === 'a' && rand() < 0.2;

        return {
            id: i + 1,
            code: `SKDIIT27-${String(3 + i * 4).padStart(4, '0')}`,
            title: pick(archiveTitles),
            author: person.name,
            email: person.email,
            track: pick(mockTracks),
            status,
            acceptedAt: at(acceptedDaysAgo),
            option,
            cameraReady: status === 'camera_ready'
                ? { fileName: `camera-ready-${i + 1}.pdf`, size: 400000 + Math.floor(rand() * 2000000), at: at(Math.max(0, acceptedDaysAgo - 8)) }
                : null,
            journal: option === 'b' || journalDeclined
                ? {
                    name: pick(journals),
                    sentAt: at(Math.max(0, acceptedDaysAgo - 3)),
                    result: status === 'journal_accepted' ? 'accepted' : journalDeclined ? 'rejected' : null,
                    at: status === 'journal_accepted' || journalDeclined ? at(Math.max(0, acceptedDaysAgo - 12)) : null,
                }
                : null,
            presentation: ['camera_ready', 'journal_accepted'].includes(status) && rand() < 0.6
                ? { at: pick(slots), room: pick(rooms) }
                : null,
        };
    });
}

export const publicationQueue = buildPublication(30);

/*
 * Finished papers from every conference year (the archive keeps growing if the system is reused):
 * { id, code, year, title, authors: [{ name, affiliation }], track, type: 'proceeding' | 'journal',
 *   journal: { name, issue } | null, pages, keywords, abstract, file: { name, size }, downloads }
 */
function buildArchive() {
    const rand = seededRandom(83);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const realAffiliations = affiliations.filter((a) => a.key !== 'other');
    const perYear = { 2025: 18, 2026: 22, 2027: 9 };
    const list = [];

    Object.entries(perYear).forEach(([year, count]) => {
        let page = 1;
        for (let i = 0; i < count; i++) {
            const title = pick(archiveTitles);
            const type = rand() < 0.7 ? 'proceeding' : 'journal';
            const length = 8 + Math.floor(rand() * 5);
            const authors = Array.from({ length: 1 + Math.floor(rand() * 3) }, () => ({
                name: personName(rand).name,
                affiliation: pick(realAffiliations),
            }));
            list.push({
                id: list.length + 1,
                code: `SKDIIT${year.slice(2)}-${String(i * 3 + 2).padStart(4, '0')}`,
                year: Number(year),
                title,
                authors,
                track: pick(mockTracks),
                type,
                journal: type === 'journal' ? { name: pick(journals), issue: `${pick(['Vol. 18', 'Vol. 19', 'Vol. 7'])}, ${pick(['No. 1', 'No. 2'])} (${year})` } : null,
                pages: type === 'proceeding' ? `${page}–${page + length - 1}` : null,
                keywords: { en: 'digital innovation, education, information technology', th: 'นวัตกรรมดิจิทัล, การศึกษา, เทคโนโลยีสารสนเทศ' },
                abstract: {
                    en: `This paper studies ${title.en.toLowerCase()}. Data were collected from participants in north-eastern Thailand and analysed with mixed methods. The findings offer practical guidelines for institutions in similar settings.`,
                    th: `บทความนี้ศึกษา${title.th} เก็บข้อมูลจากกลุ่มตัวอย่างในภาคตะวันออกเฉียงเหนือ วิเคราะห์ด้วยวิธีวิจัยแบบผสานวิธี ผลการวิจัยให้แนวทางที่นำไปใช้ได้จริงกับหน่วยงานที่มีบริบทใกล้เคียง`,
                },
                file: { name: `SKDIIT${year}-${i + 1}.pdf`, size: 300000 + Math.floor(rand() * 2500000) },
                downloads: Math.floor(rand() * (2028 - Number(year)) * 120),
            });
            if (type === 'proceeding') page += length;
        }
    });

    return list.sort((a, b) => b.year - a.year || a.code.localeCompare(b.code));
}

export const articleArchive = buildArchive();

// ---- Conference event: presentation sessions (mock until wired to real data) ----

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
 * { id, block, room, track, chair, minutes (per paper), papers: [{ code, title, author, mode: 'onsite' | 'online' }] }
 * unscheduledPapers: papers that can present (Option A / camera-ready / journal accepted) but have no session yet.
 */
function buildSessions() {
    const rand = seededRandom(71);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    let code = 5;
    const paper = () => ({
        code: `SKDIIT27-${String((code += 3)).padStart(4, '0')}`,
        title: pick(archiveTitles),
        author: personName(rand).name,
        mode: rand() < 0.8 ? 'onsite' : 'online',
    });

    const sessions = [];
    presentationBlocks.forEach((block) => {
        presentationRooms.slice(0, block.id === 1 ? 4 : 3).forEach((room, i) => {
            const count = block.id === 1 ? 3 + Math.floor(rand() * 3) : 4 + Math.floor(rand() * 4);
            sessions.push({
                id: sessions.length + 1,
                block: block.id,
                room: room.id,
                track: mockTracks[(i + block.id - 1) % mockTracks.length].id,
                chair: personName(rand).name,
                minutes: 15,
                papers: Array.from({ length: count }, paper),
            });
        });
    });

    return { sessions, unscheduled: Array.from({ length: 6 }, paper) };
}

const builtSessions = buildSessions();
export const presentationSessions = builtSessions.sessions;
export const unscheduledPapers = builtSessions.unscheduled;

// ---- Notifications (mock until email/SMTP is decided; every email the system sends also becomes one of these) ----

const minutesAgo = (n) => new Date(Date.now() - n * 60 * 1000).toISOString();
const note = (id, type, icon, minutes, read, href, title, body) => ({ id, type, icon, at: minutesAgo(minutes), read, href, title, body });

/*
 * By access role (User::accessRole()). type: paper | review | payment | system.
 * { id, type, icon, at, read, href, title: { en, th }, body: { en, th } }
 */
export const notificationsByRole = {
    author: [
        note(1, 'paper', 'pen', 25, false, '/admin/my-submissions', { en: 'Major revision requested', th: 'บทความต้องแก้ไขมาก' }, { en: 'SKDIIT27-0011: the editor asked for a major revision. Please resubmit by the due date.', th: 'SKDIIT27-0011: Editor ขอให้แก้ไขมาก กรุณาส่งฉบับแก้ไขภายในกำหนด' }),
        note(2, 'paper', 'circle-check', 180, false, '/admin/my-submissions', { en: 'Paper accepted', th: 'บทความผ่านการพิจารณา' }, { en: 'SKDIIT27-0014 was accepted. Choose Option A or B to continue.', th: 'SKDIIT27-0014 ผ่านการพิจารณาแล้ว กรุณาเลือก Option A หรือ B' }),
        note(3, 'payment', 'receipt', 60 * 20, false, '/admin/payments', { en: 'Slip not accepted', th: 'สลิปไม่ผ่านการตรวจสอบ' }, { en: 'INV-27-0087: the transferred amount does not match the fee. Please upload a new slip.', th: 'INV-27-0087: ยอดเงินที่โอนไม่ตรงกับค่าธรรมเนียม กรุณาอัปโหลดสลิปใหม่' }),
        note(4, 'paper', 'clock', 60 * 26, true, '/admin/my-submissions', { en: 'Revision due in 3 days', th: 'อีก 3 วันครบกำหนดส่งฉบับแก้ไข' }, { en: 'SKDIIT27-0011 revision is due on 11 Oct 2026.', th: 'SKDIIT27-0011 ครบกำหนดส่งฉบับแก้ไขวันที่ 11 ต.ค. 2569' }),
        note(5, 'payment', 'circle-check', 60 * 24 * 4, true, '/admin/payments', { en: 'Payment confirmed', th: 'ยืนยันการชำระเงินแล้ว' }, { en: 'INV-27-0052: finance confirmed your payment of 3,000 THB.', th: 'INV-27-0052: ฝ่ายการเงินยืนยันการชำระเงิน 3,000 บาทแล้ว' }),
        note(6, 'paper', 'person-chalkboard', 60 * 24 * 6, true, '/admin/my-submissions', { en: 'Presentation slot set', th: 'กำหนดเวลานำเสนอแล้ว' }, { en: 'SKDIIT27-0003 presents on 28 Jan 2027, 10:45, Room 301.', th: 'SKDIIT27-0003 นำเสนอวันที่ 28 ม.ค. 2570 เวลา 10:45 ห้อง 301' }),
        note(7, 'paper', 'paper-plane', 60 * 24 * 30, true, '/admin/my-submissions', { en: 'Paper received', th: 'ได้รับบทความแล้ว' }, { en: 'SKDIIT27-0001 was submitted. You can edit it until the deadline.', th: 'ส่งบทความ SKDIIT27-0001 แล้ว แก้ไขได้จนถึงวันปิดรับ' }),
    ],
    reviewer: [
        note(1, 'review', 'clipboard-check', 40, false, '/admin/reviews', { en: 'New paper to review', th: 'มีบทความใหม่ให้ประเมิน' }, { en: 'SKDIIT27-0006 was assigned to you. Due 28 Oct 2026.', th: 'คุณได้รับมอบหมายบทความ SKDIIT27-0006 ครบกำหนด 28 ต.ค. 2569' }),
        note(2, 'review', 'clock', 60 * 5, false, '/admin/reviews', { en: 'Review due in 2 days', th: 'อีก 2 วันครบกำหนดประเมิน' }, { en: 'SKDIIT27-0007 is due on 16 Oct 2026.', th: 'SKDIIT27-0007 ครบกำหนดประเมินวันที่ 16 ต.ค. 2569' }),
        note(3, 'review', 'triangle-exclamation', 60 * 24, true, '/admin/reviews', { en: 'Review overdue', th: 'เลยกำหนดประเมิน' }, { en: 'SKDIIT27-0013 was due yesterday. Please send your evaluation.', th: 'SKDIIT27-0013 เลยกำหนดมาแล้ว 1 วัน กรุณาส่งผลการประเมิน' }),
        note(4, 'review', 'xmark', 60 * 24 * 9, true, '/admin/reviews', { en: 'Removed from a paper', th: 'ถูกนำออกจากบทความ' }, { en: 'The editor removed you from SKDIIT27-0021. No action needed.', th: 'Editor นำคุณออกจากบทความ SKDIIT27-0021 แล้ว ไม่ต้องดำเนินการต่อ' }),
    ],
    editor: [
        note(1, 'paper', 'file-arrow-up', 15, false, '/admin/submissions', { en: 'New paper submitted', th: 'มีบทความใหม่' }, { en: 'SKDIIT27-0003 “Social media news consumption…” is waiting for screening.', th: 'SKDIIT27-0003 “พฤติกรรมการรับข่าวสารผ่าน TikTok…” รอคัดกรอง' }),
        note(2, 'review', 'clipboard-check', 70, false, '/admin/submissions', { en: 'All reviews are in', th: 'ผู้ประเมินส่งผลครบแล้ว' }, { en: 'SKDIIT27-0010: 2 of 2 reviews received. Ready for your decision.', th: 'SKDIIT27-0010: ผู้ประเมินส่งผลครบ 2/2 พร้อมให้ตัดสิน' }),
        note(3, 'paper', 'rotate', 60 * 6, false, '/admin/submissions', { en: 'Revision received', th: 'ได้รับฉบับแก้ไข' }, { en: 'SKDIIT27-0012 was revised. Decide or start a new review round.', th: 'SKDIIT27-0012 ส่งฉบับแก้ไขแล้ว ตัดสินได้เลยหรือส่งประเมินรอบใหม่' }),
        note(4, 'review', 'triangle-exclamation', 60 * 22, true, '/admin/submissions', { en: 'Reviewer overdue', th: 'ผู้ประเมินเลยกำหนด' }, { en: 'ณัฐยา ทองดี is 9 days late on SKDIIT27-0008.', th: 'ณัฐยา ทองดี เลยกำหนดประเมิน SKDIIT27-0008 มา 9 วัน' }),
        note(5, 'paper', 'book', 60 * 24 * 2, true, '/admin/publication', { en: 'Option chosen', th: 'ผู้เขียนเลือกช่องทางเผยแพร่แล้ว' }, { en: 'SKDIIT27-0018 chose Option B. Forward it to the journal.', th: 'SKDIIT27-0018 เลือก Option B ส่งต่อวารสารได้' }),
    ],
    finance: [
        note(1, 'payment', 'receipt', 10, false, '/admin/payments', { en: 'New slip to check', th: 'มีสลิปใหม่รอตรวจ' }, { en: 'INV-27-0031: a slip of 3,000 THB was uploaded.', th: 'INV-27-0031: อัปโหลดสลิป 3,000 บาท' }),
        note(2, 'payment', 'triangle-exclamation', 95, false, '/admin/payments', { en: 'Amount does not match', th: 'ยอดในสลิปไม่ตรง' }, { en: 'INV-27-0024: the slip shows 4,500 THB, the fee is 5,000 THB.', th: 'INV-27-0024: สลิประบุ 4,500 บาท แต่ค่าธรรมเนียม 5,000 บาท' }),
        note(3, 'payment', 'receipt', 60 * 3, true, '/admin/payments', { en: 'New slip to check', th: 'มีสลิปใหม่รอตรวจ' }, { en: 'INV-27-0019: a slip of 5,000 THB was uploaded.', th: 'INV-27-0019: อัปโหลดสลิป 5,000 บาท' }),
    ],
    admin: [
        note(1, 'system', 'envelope', 30, false, '/admin/notifications?tab=emails', { en: '2 emails could not be sent', th: 'ส่งอีเมลไม่สำเร็จ 2 ฉบับ' }, { en: 'Check the email log: the mailbox may not exist.', th: 'ตรวจสอบที่ประวัติอีเมล: อาจเป็นที่อยู่อีเมลที่ไม่มีอยู่จริง' }),
        note(2, 'paper', 'file-arrow-up', 15, false, '/admin/submissions', { en: 'New paper submitted', th: 'มีบทความใหม่' }, { en: 'SKDIIT27-0003 is waiting for screening.', th: 'SKDIIT27-0003 รอคัดกรอง' }),
        note(3, 'system', 'user-plus', 60 * 2, false, '/admin/registrants', { en: '12 new registrants today', th: 'วันนี้มีผู้ลงทะเบียนใหม่ 12 คน' }, { en: '3 of them have not signed in with their emailed password yet.', th: 'ในจำนวนนี้ 3 คนยังไม่ได้เข้าสู่ระบบด้วยรหัสที่ส่งทางอีเมล' }),
        note(4, 'review', 'triangle-exclamation', 60 * 22, true, '/admin/submissions', { en: 'Reviewer overdue', th: 'ผู้ประเมินเลยกำหนด' }, { en: 'ณัฐยา ทองดี is 9 days late on SKDIIT27-0008.', th: 'ณัฐยา ทองดี เลยกำหนดประเมิน SKDIIT27-0008 มา 9 วัน' }),
        note(5, 'system', 'calendar-days', 60 * 24 * 3, true, '/admin/settings', { en: 'Submissions close in 47 days', th: 'อีก 47 วันปิดรับบทความ' }, { en: 'The deadline in Settings is 30 Nov 2026.', th: 'วันปิดรับที่ตั้งไว้คือ 30 พ.ย. 2569' }),
    ],
};

/*
 * Every email the system sent (admin): { id, to, name, subject, template, body: { th: [lines], en: [lines] }, button,
 * at, status: sent | failed | queued, error, resent (times sent again) }
 * template names match the planned Mailables (LoginPassword is real; the others come with SMTP).
 */
export const emailLog = (() => {
    const rand = seededRandom(19);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const code = () => `SKDIIT27-${String(1 + Math.floor(rand() * 120)).padStart(4, '0')}`;

    /*
     * Planned Mailables (LoginPassword is real; the others come with SMTP). body() gives the message in both
     * languages, the way the email shows it (Thai first, then English). Passwords are never kept in the log.
     */
    const templates = [
        {
            name: 'LoginPassword',
            subject: { en: 'Your SKDIIT 2027 sign-in password', th: 'รหัสผ่านสำหรับเข้าสู่ระบบ SKDIIT 2027' },
            body: () => ({
                th: ['ลงทะเบียนเรียบร้อยแล้ว ใช้รหัสผ่านด้านล่างนี้คู่กับอีเมลของคุณเพื่อเข้าสู่ระบบ', 'รหัสผ่าน: ••••••  (ระบบไม่เก็บรหัสผ่านไว้ในประวัติ)'],
                en: ['Thank you for registering. Use the password below with your email to sign in.', 'Password: ••••••  (passwords are never kept in the log)'],
            }),
            button: { th: 'เข้าสู่ระบบ', en: 'Sign in' },
        },
        {
            name: 'PaperReceived',
            subject: { en: 'We received your paper', th: 'ได้รับบทความของคุณแล้ว' },
            body: (c) => ({
                th: [`ระบบได้รับบทความ ${c} ของคุณแล้ว`, 'คุณแก้ไขบทความและเปลี่ยนไฟล์ได้จนถึงวันปิดรับบทความ'],
                en: [`We received your paper ${c}.`, 'You can edit it and replace the file until the submission deadline.'],
            }),
            button: { th: 'ดูบทความของฉัน', en: 'View my papers' },
        },
        {
            name: 'ScreeningResult',
            subject: { en: 'Screening result of your paper', th: 'ผลการคัดกรองบทความ' },
            body: (c) => ({
                th: [`บทความ ${c} ผ่านการคัดกรองเบื้องต้นแล้ว`, 'บทความของคุณจะเข้าสู่ขั้นตอนการประเมินโดยผู้ทรงคุณวุฒิ'],
                en: [`Your paper ${c} passed screening.`, 'It now moves on to peer review.'],
            }),
            button: { th: 'ดูรายละเอียด', en: 'View details' },
        },
        {
            name: 'ReviewerAssigned',
            subject: { en: 'A paper has been assigned to you', th: 'คุณได้รับมอบหมายบทความให้ประเมิน' },
            body: (c) => ({
                th: [`คุณได้รับมอบหมายให้ประเมินบทความ ${c}`, 'กรุณาส่งผลการประเมินภายใน 14 วัน'],
                en: [`You have been asked to review paper ${c}.`, 'Please send your evaluation within 14 days.'],
            }),
            button: { th: 'ไปที่งานประเมิน', en: 'Open my reviews' },
        },
        {
            name: 'ReviewReminder',
            subject: { en: 'Review due soon', th: 'ใกล้ครบกำหนดประเมินบทความ' },
            body: (c) => ({
                th: [`อีก 2 วันจะครบกำหนดประเมินบทความ ${c}`, 'หากส่งไม่ทันกำหนด กรุณาแจ้ง Editor'],
                en: [`Your review of ${c} is due in 2 days.`, 'If you cannot make it, please tell the editor.'],
            }),
            button: { th: 'ไปที่งานประเมิน', en: 'Open my reviews' },
        },
        {
            name: 'EditorDecision',
            subject: { en: 'Decision on your paper', th: 'ผลการพิจารณาบทความ' },
            body: (c) => ({
                th: [`Editor ตัดสินผลบทความ ${c} แล้ว: แก้ไขเล็กน้อย`, 'กรุณาแก้ไขตามข้อเสนอแนะของผู้ประเมิน และส่งฉบับแก้ไขภายในกำหนด'],
                en: [`The editor decided on ${c}: minor revision.`, 'Please revise it following the reviewers’ comments and resubmit by the due date.'],
            }),
            button: { th: 'ดูผลการพิจารณา', en: 'View the decision' },
        },
        {
            name: 'PaymentResult',
            subject: { en: 'Payment check result', th: 'ผลการตรวจสอบการชำระเงิน' },
            body: (c) => ({
                th: [`ฝ่ายการเงินยืนยันการชำระค่าธรรมเนียมของบทความ ${c} แล้ว`, 'ขอบคุณที่ร่วมงานประชุมวิชาการ SKDIIT 2027'],
                en: [`Finance confirmed the fee payment for ${c}.`, 'Thank you for joining SKDIIT 2027.'],
            }),
            button: { th: 'ดูการชำระเงิน', en: 'View payments' },
        },
    ];

    return Array.from({ length: 42 }, (_, i) => {
        const person = personName(rand);
        const template = pick(templates);
        const r = rand();
        const status = r < 0.88 ? 'sent' : r < 0.95 ? 'failed' : 'queued';
        return {
            id: 42 - i,
            to: person.email.replace('@', `${i}@`),
            name: person.name,
            subject: template.subject,
            template: template.name,
            body: template.body(code()),
            button: template.button,
            at: minutesAgo(i * 47 + Math.floor(rand() * 40)),
            status,
            error: status === 'failed' ? pick(['550 Mailbox not found', '552 Mailbox full', 'Connection timed out']) : null,
            resent: 0,
        };
    });
})();

// ---- Reports and role dashboards (mock; numbers are made up but add up, for the client demo) ----

// One value per day for the last `days` days, ending today (Asia/Bangkok)
function dailySeries(seed, days, base, spread, growth = 0) {
    const rand = seededRandom(seed);
    const today = new Date('2026-10-14T12:00:00+07:00');
    return Array.from({ length: days }, (_, i) => {
        const date = new Date(today.getTime() - (days - 1 - i) * 86400000);
        const weekend = [0, 6].includes(date.getDay()) ? 0.55 : 1;
        return { date: date.toISOString().slice(0, 10), value: Math.max(0, Math.round((base + growth * i + (rand() - 0.5) * spread) * weekend)) };
    });
}

const registrationTrend = dailySeries(5, 30, 9, 10, 0.35);

export const reportData = {
    registrants: {
        kpis: { total: 412, attendOnly: 268, authors: 144, notSignedIn: 37 },
        trend: registrationTrend,
        byAffiliation: [['msu', 128], ['kku', 61], ['rmu', 52], ['nu', 38], ['ubu', 33], ['cmu', 27], ['ksu', 24], ['tu', 19], ['kmitl', 16], ['obec', 9], ['other', 5]],
        byParticipation: [['attend', 268], ['attend_submit', 144]],
    },
    papers: {
        kpis: { submitted: 126, inReview: 38, accepted: 40, rejected: 17 },
        byTrack: [[1, 34], [5, 31], [3, 24], [4, 21], [2, 16]],
        byStatus: [
            ['submitted', 9], ['screening_revision', 4], ['screening_rejected', 6], ['in_review', 38], ['revision_minor', 7],
            ['revision_major', 5], ['revised', 6], ['accepted', 5], ['rejected', 11], ['option_a', 12], ['camera_ready', 13], ['option_b', 4], ['journal_accepted', 6],
        ],
        options: [['a', 25], ['b', 10]],
    },
    reviews: {
        kpis: { assigned: 214, done: 171, avgDays: 9.4, overdue: 12 },
        recommendations: [['accept', 38], ['minor', 71], ['major', 44], ['reject', 18]],
        reviewers: [
            { name: { en: 'Assoc. Prof. Preecha Khamkaew', th: 'รศ.ปรีชา คำแก้ว' }, tracks: [2, 4], assigned: 31, done: 29, avgDays: 7.2, late: 0 },
            { name: { en: 'Asst. Prof. Suda Meesuk', th: 'ผศ.สุดา มีสุข' }, tracks: [1, 3], assigned: 28, done: 24, avgDays: 8.1, late: 1 },
            { name: { en: 'Prof. Orawan Sriwong', th: 'ศ.อรวรรณ ศรีวงศ์' }, tracks: [1, 5], assigned: 26, done: 25, avgDays: 6.5, late: 0 },
            { name: { en: 'Asst. Prof. Kittisak Phandee', th: 'ผศ.กิตติศักดิ์ พันธ์ดี' }, tracks: [4, 5], assigned: 25, done: 19, avgDays: 11.8, late: 3 },
            { name: { en: 'Lect. Nattaya Thongdee', th: 'อ.ณัฐยา ทองดี' }, tracks: [3, 5], assigned: 24, done: 15, avgDays: 13.6, late: 5 },
            { name: { en: 'Assoc. Prof. Demo Reviewer', th: 'รศ.Demo Reviewer' }, tracks: [1, 2], assigned: 22, done: 20, avgDays: 8.9, late: 1 },
            { name: { en: 'Dr. Wichai Saengthong', th: 'ดร.วิชัย แสงทอง' }, tracks: [2, 3], assigned: 21, done: 18, avgDays: 10.2, late: 1 },
            { name: { en: 'Asst. Prof. Malee Boonma', th: 'ผศ.มาลี บุญมา' }, tracks: [4], assigned: 19, done: 15, avgDays: 9.7, late: 1 },
            { name: { en: 'Dr. Anan Wongthai', th: 'ดร.อนันต์ วงศ์ไทย' }, tracks: [5], assigned: 18, done: 6, avgDays: 15.1, late: 0 },
        ],
    },
    finance: {
        receiptsTrend: dailySeries(11, 30, 9000, 12000, 250),
    },
};

/*
 * Role dashboards: what each role has to do now. Counts link to the page where the work happens.
 * { kpis, tasks: [{ key, icon, count, href }], ... } per access role.
 */
const ago = (minutes) => new Date(Date.now() - minutes * 60000).toISOString();
export const dashboardData = {
    admin: {
        kpis: { registrantsToday: 12, waitingScreening: 9, overdueReviews: 12, slipsToCheck: 12 },
        tasks: [
            { key: 'screening', icon: 'file-lines', count: 9, href: '/admin/submissions' },
            { key: 'overdueReviews', icon: 'triangle-exclamation', count: 12, href: '/admin/submissions' },
            { key: 'failedEmails', icon: 'envelope', count: 2, href: '/admin/notifications?tab=emails' },
            { key: 'notSignedIn', icon: 'user-clock', count: 37, href: '/admin/registrants' },
            { key: 'noSession', icon: 'person-chalkboard', count: 6, href: '/admin/presentations' },
        ],
        activity: [
            { icon: 'user-plus', at: ago(8), text: { en: 'Kamonwan Srisuk registered (attend + submit)', th: 'กมลวรรณ ศรีสุข ลงทะเบียน (เข้าร่วม + ส่งผลงาน)' } },
            { icon: 'file-arrow-up', at: ago(15), text: { en: 'SKDIIT27-0126 was submitted', th: 'มีบทความใหม่ SKDIIT27-0126' } },
            { icon: 'clipboard-check', at: ago(42), text: { en: 'Prof. Orawan Sriwong sent a review for SKDIIT27-0088', th: 'ศ.อรวรรณ ศรีวงศ์ ส่งผลประเมิน SKDIIT27-0088' } },
            { icon: 'receipt', at: ago(65), text: { en: 'A slip of 3,000 THB was uploaded for INV-27-0031', th: 'อัปโหลดสลิป 3,000 บาท ของ INV-27-0031' } },
            { icon: 'gavel', at: ago(130), text: { en: 'Demo Editor accepted SKDIIT27-0071', th: 'Demo Editor ตัดสินรับบทความ SKDIIT27-0071' } },
            { icon: 'envelope', at: ago(185), text: { en: 'An email to anucha.ra7@example.ac.th could not be delivered', th: 'ส่งอีเมลถึง anucha.ra7@example.ac.th ไม่สำเร็จ' } },
        ],
    },
    editor: {
        kpis: { waitingScreening: 9, readyToDecide: 7, revisionsBack: 6, overdueReviews: 12 },
        tasks: [
            { key: 'screening', icon: 'file-lines', count: 9, href: '/admin/submissions' },
            { key: 'readyToDecide', icon: 'gavel', count: 7, href: '/admin/submissions' },
            { key: 'revisionsBack', icon: 'rotate', count: 6, href: '/admin/submissions' },
            { key: 'needReviewers', icon: 'user-check', count: 4, href: '/admin/submissions' },
            { key: 'journalWaiting', icon: 'paper-plane', count: 4, href: '/admin/publication' },
        ],
        overdue: [
            { reviewer: { en: 'Lect. Nattaya Thongdee', th: 'อ.ณัฐยา ทองดี' }, code: 'SKDIIT27-0008', days: 9 },
            { reviewer: { en: 'Asst. Prof. Kittisak Phandee', th: 'ผศ.กิตติศักดิ์ พันธ์ดี' }, code: 'SKDIIT27-0045', days: 5 },
            { reviewer: { en: 'Dr. Wichai Saengthong', th: 'ดร.วิชัย แสงทอง' }, code: 'SKDIIT27-0052', days: 3 },
            { reviewer: { en: 'Asst. Prof. Suda Meesuk', th: 'ผศ.สุดา มีสุข' }, code: 'SKDIIT27-0061', days: 1 },
        ],
    },
    reviewer: {
        kpis: { pendingReviews: 3, dueSoon: 1, overdueMine: 1, doneReviews: 20 },
        reviews: [
            { code: 'SKDIIT27-0013', title: { en: 'Thai Fake News Detection with Transformer Models', th: 'การตรวจจับข่าวปลอมภาษาไทยด้วยโมเดล Transformer' }, due: '2026-10-13', state: 'overdue' },
            { code: 'SKDIIT27-0007', title: { en: 'Real-Time Seat Availability Tracking in Libraries', th: 'ระบบติดตามที่นั่งว่างในห้องสมุดแบบเรียลไทม์' }, due: '2026-10-16', state: 'dueSoon' },
            { code: 'SKDIIT27-0006', title: { en: 'Assessing Metadata Quality in Institutional Repositories', th: 'การประเมินคุณภาพเมทาดาทาในคลังสถาบัน' }, due: '2026-10-28', state: 'pending' },
        ],
    },
    author: {
        papers: [
            { code: 'SKDIIT27-0011', title: { en: 'Digital Payment Adoption among Fresh-Market Vendors', th: 'การยอมรับเทคโนโลยีการชำระเงินดิจิทัลของผู้ค้าในตลาดสด' }, status: 'revision_major', next: { en: 'Revise and resubmit (3 days late)', th: 'แก้ไขและส่งกลับ (เลยกำหนด 3 วัน)' }, urgent: true },
            { code: 'SKDIIT27-0014', title: { en: 'An Automatic Lighting Control Prototype for Classroom Buildings', th: 'ต้นแบบระบบควบคุมแสงสว่างอัตโนมัติในอาคารเรียน' }, status: 'accepted', next: { en: 'Choose Option A or B', th: 'เลือก Option A หรือ B' }, urgent: true },
            { code: 'SKDIIT27-0001', title: { en: 'A Machine-Learning Book Recommender for University Libraries', th: 'ระบบแนะนำหนังสือห้องสมุดด้วยการเรียนรู้ของเครื่อง' }, status: 'submitted', next: { en: 'Waiting for screening — you can still edit it', th: 'รอคัดกรอง ยังแก้ไขได้' }, urgent: false },
        ],
        paymentsDue: 1,
    },
    finance: {
        kpis: { slipsToCheck: 12, receivedToday: 11000, outstandingBaht: 83000, paidCount: 11 },
    },
};

// ---- Activity log (mock; will read submission_events, account changes, sign-ins and impersonation) ----

/*
 * { id, at, category, action, actor: { name, role, email }, target, detail: { en, th } | null, ip, device, flagged }
 * category: auth | account | paper | review | payment | system | impersonation
 * flagged marks events worth a second look (failed sign-ins, impersonation, deletions).
 */
export const activityLog = (() => {
    const rand = seededRandom(43);
    const pick = (list) => list[Math.floor(rand() * list.length)];
    const code = () => `SKDIIT27-${String(1 + Math.floor(rand() * 126)).padStart(4, '0')}`;
    const ip = () => pick(['202.28.32.', '202.28.34.', '49.228.', '1.46.', '171.97.']) + `${Math.floor(rand() * 250) + 2}${rand() < 0.4 ? '' : `.${Math.floor(rand() * 250) + 2}`}`;
    const device = () => pick(['Chrome · Windows', 'Chrome · macOS', 'Safari · iPhone', 'Edge · Windows', 'Chrome · Android', 'Firefox · Windows']);

    const admin = { name: 'Administrator', role: 'admin', email: 'wongnarin.s@msu.ac.th' };
    const editor = { name: 'Demo Editor', role: 'editor', email: 'editor@skdiit.test' };
    const finance = { name: 'Demo Finance', role: 'finance', email: 'finance@skdiit.test' };
    const reviewers = [
        { name: 'ศ.อรวรรณ ศรีวงศ์', role: 'reviewer', email: 'rv.orawan@skdiit.test' },
        { name: 'ผศ.สุดา มีสุข', role: 'reviewer', email: 'rv.suda@skdiit.test' },
        { name: 'อ.ณัฐยา ทองดี', role: 'reviewer', email: 'rv.nattaya@skdiit.test' },
    ];
    const someone = () => {
        const p = personName(rand);
        return { name: p.name.th, role: 'author', email: p.email };
    };

    // [weight, builder] - builders return the event without time/ip/device
    const kinds = [
        [14, () => ({ category: 'auth', action: 'login', actor: pick([admin, editor, finance, ...reviewers, someone(), someone()]) })],
        [3, () => {
            const p = someone();
            return { category: 'auth', action: 'login_failed', actor: { ...p, name: p.email }, detail: { en: '3 wrong passwords in a row', th: 'ใส่รหัสผ่านผิด 3 ครั้งติดกัน' }, flagged: true };
        }],
        [8, () => {
            const p = someone();
            return { category: 'account', action: 'registered', actor: p, target: p.email, detail: pick([{ en: 'Attend only', th: 'เข้าร่วมงานอย่างเดียว' }, { en: 'Attend + submit', th: 'เข้าร่วม + ส่งผลงาน' }]) };
        }],
        [2, () => ({ category: 'account', action: 'password_sent', actor: admin, target: someone().email, detail: { en: 'New password sent by email', th: 'ส่งรหัสผ่านใหม่ทางอีเมล' } })],
        [1, () => ({ category: 'account', action: 'suspended', actor: admin, target: someone().email, detail: { en: 'Duplicate account', th: 'บัญชีซ้ำ' }, flagged: true })],
        [1, () => ({ category: 'account', action: 'staff_created', actor: admin, target: pick(reviewers).email, detail: { en: 'Role: reviewer', th: 'บทบาท: ผู้ประเมิน' } })],
        [9, () => ({ category: 'paper', action: 'submitted', actor: someone(), target: code() })],
        [3, () => ({ category: 'paper', action: 'updated', actor: someone(), target: code(), detail: { en: 'File replaced', th: 'เปลี่ยนไฟล์บทความ' } })],
        [5, () => ({ category: 'paper', action: 'screened', actor: editor, target: code(), detail: pick([{ en: 'Passed screening', th: 'ผ่านการคัดกรอง' }, { en: 'Sent back for revision', th: 'ส่งกลับให้แก้ไข' }]) })],
        [4, () => ({ category: 'paper', action: 'decided', actor: editor, target: code(), detail: pick([{ en: 'Accepted', th: 'ตอบรับ' }, { en: 'Minor revision', th: 'แก้ไขเล็กน้อย' }, { en: 'Major revision', th: 'แก้ไขมาก' }]) })],
        [2, () => ({ category: 'paper', action: 'track_changed', actor: editor, target: code(), detail: { en: 'Track 3 → Track 5', th: 'กลุ่มสาขา 3 → กลุ่มสาขา 5' } })],
        [5, () => {
            const reviewer = pick(reviewers);
            return { category: 'review', action: 'assigned', actor: editor, target: code(), detail: { en: `Reviewer: ${reviewer.name}`, th: `ผู้ประเมิน: ${reviewer.name}` } };
        }],
        [6, () => ({ category: 'review', action: 'review_submitted', actor: pick(reviewers), target: code(), detail: pick([{ en: 'Accept', th: 'ผ่าน' }, { en: 'Minor revision', th: 'แก้ไขเล็กน้อย' }, { en: 'Major revision', th: 'แก้ไขมาก' }]) })],
        [4, () => ({ category: 'payment', action: 'slip_uploaded', actor: someone(), target: `INV-27-${String(Math.floor(rand() * 40) + 1).padStart(4, '0')}`, detail: { en: '3,000 THB', th: '3,000 บาท' } })],
        [3, () => ({ category: 'payment', action: 'payment_approved', actor: finance, target: `INV-27-${String(Math.floor(rand() * 40) + 1).padStart(4, '0')}` })],
        [1, () => ({ category: 'payment', action: 'payment_rejected', actor: finance, target: `INV-27-${String(Math.floor(rand() * 40) + 1).padStart(4, '0')}`, detail: { en: 'Amount does not match', th: 'ยอดเงินไม่ตรง' } })],
        [1, () => ({ category: 'system', action: 'settings_changed', actor: admin, target: pick(['submission_deadline', 'upload_max_mb', 'submission_opens']), detail: { en: 'Deadline: 30 Nov 2026 23:59', th: 'วันปิดรับ: 30 พ.ย. 2569 23:59' } })],
        [1, () => ({ category: 'system', action: 'email_failed', actor: { name: 'System', role: 'system', email: '' }, target: someone().email, detail: { en: '550 Mailbox not found', th: '550 ไม่พบกล่องจดหมาย' }, flagged: true })],
        [1, () => ({ category: 'impersonation', action: 'impersonation_start', actor: admin, target: pick([editor.email, someone().email]), detail: { en: 'Viewing as this user (read only)', th: 'ดูในฐานะผู้ใช้นี้ (ดูอย่างเดียว)' }, flagged: true })],
    ];
    const totalWeight = kinds.reduce((n, [w]) => n + w, 0);
    const draw = () => {
        let r = rand() * totalWeight;
        for (const [w, build] of kinds) {
            if ((r -= w) < 0) return build();
        }
        return kinds[0][1]();
    };

    const now = Date.now();
    let minutes = 3;
    const list = [];
    for (let i = 0; i < 160; i++) {
        const event = draw();
        const at = new Date(now - minutes * 60000).toISOString();
        list.push({ id: 160 - i, at, target: null, detail: null, flagged: false, ip: ip(), device: device(), ...event });
        // Every impersonation start has a matching stop a few minutes later (listed first: newer)
        if (event.action === 'impersonation_start') {
            list.splice(list.length - 1, 0, { ...list[list.length - 1], id: 1000 + i, action: 'impersonation_stop', at: new Date(now - (minutes - 6) * 60000).toISOString(), detail: { en: 'Back to admin after 6 min', th: 'กลับเป็นแอดมินหลัง 6 นาที' } });
        }
        minutes += 2 + Math.floor(rand() * 35);
    }
    return list;
})();
