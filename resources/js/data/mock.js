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

export const registrants = buildRegistrants(256);

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
