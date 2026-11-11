<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner journals and the journal offer (client request 2026-11-11): editors (or the journal role) pick one of the
 * partner journals for a paper after the submission deadline; the author accepts (goes to that journal, done with
 * the conference until the slides) or declines (conference track). An unanswered offer lapses on its due date.
 * The 11 journals come from public/data/แบบตอบรับร่วมคัดเลือกบทความ(SKDIIT2027).xlsx.
 */
return new class extends Migration
{
    private const JOURNALS = [
        ['วารสารคอมพิวเตอร์และเทคโนโลยีสร้างสรรค์', 'https://so13.tci-thaijo.org/index.php/jcct', null, 'อ.ดร.วิจิตรา โพธิสาร', 'บรรณาธิการ', 'jcct@srru.ac.th', '0887235944', 'ลดค่าตีพิมพ์จากเดิม บทความภาษาอังกฤษ 4500 บาท โดยจะลดเหลือ บทความละ 3500 บาท (เท่าบทความภาษาไทย)'],
        ['วารสารวิทยาศาสตร์ เทคโนโลยี และนวัตกรรม มหาวิทยาลัยกาฬสินธุ์', 'https://li01.tci-thaijo.org/index.php/sci_01', null, 'นางสาวนภัทรธิดา พรมดีราช', 'หัวหน้างานอำนวยการและบริหารทั่วไป', 'napattida.pr@ksu.ac.th', '0644974661', 'เข้าร่วมโดยเก็บค่าธรรมเนียมการตีพิมพ์'],
        ['วารสารวิชาการบัณฑิตศึกษา', 'https://so13.tci-thaijo.org/index.php/ajgs', null, 'นางณัฏฐ์นัน ทุนดี', 'นักวิชาการศึกษา', 'nang170925@srru.ac.th', '0945694453', 'วารสารวิชาการบัณฑิตศึกษา TCI2 ขอรับบทความสายบริหารการศึกษา/การศึกษา ฟรี 3 เรื่อง'],
        ['ICCK Transactions on Advanced Computing and Systems', 'https://www.icck.org/tacs', null, 'Inam Ullah', 'Professor', 'inam@gachon.ac.kr', '00923491272038', 'To contribute some papers'],
        ['Journal of Social Innovation and Mass Communication Technology', 'https://so04.tci-thaijo.org/index.php/JRBGS', null, 'สันดุสิทธิ์ บริวงษ์ตระกูล', 'บรรณาธิการ', 'sandusit.13@gmail.com', '0813743100', null],
        ['Interdisciplinary Academic and Research Journal', 'https://so03.tci-thaijo.org/index.php/IARJ/about', 'DR.KEN Institute of Academic Development and Promotion', 'Asst. Prof. Sanya Kenaphoom', 'Editor-in-Chief', 'sanya.ke@rmu.ac.th', '+66946398978', 'One article, having passed the review process by the organizing committee, will be published free of charge.'],
        ['Journal of Education and Learning Reviews (JELR)', 'https://so19.tci-thaijo.org/index.php/JELS/about', 'DR.KEN Institute of Academic Development and Promotion, under a Memorandum of Understanding (MOU) with Rajabhat Mahasarakham University', 'Asst. Prof. Dr. Sanya Kenaphoom', 'Editor-in-Chief', 'sanya.ke@rmu.ac.th', '+66946398978', 'One article, having passed the review process by the organizing committee, will be published free of charge.'],
        ['Innovation Journal of Digital Technologies and Evolution', 'https://so13.tci-thaijo.org/index.php/ijdte', 'AIIDE Publishing & Digital', 'นายนวัฒกร โพธิสาร', 'บรรณาธิการ', 'ijdte.journal@gmail.com, ijdte@aiide.in.th', '0866382433', 'No APC'],
        ['วารสารราชภัฏสุรินทร์วิชาการ', 'https://so10.tci-thaijo.org/index.php/jasrru', 'มหาวิทยาลัยราชภัฏสุรินทร์', 'ผศ.ดร.วันชัย สุขตาม', 'ผู้ช่วยบรรณาธิการ', 'jasrru@srru.ac.th', '091 835 8558', 'ฟรี 1 บทความ'],
        ['Journal of Applied Informatics and Technology (JIT)', 'https://ph01.tci-thaijo.org/index.php/jait', null, 'รศ. ดร.โอฬาริก สุรินต๊ะ', 'Editor-in-Chief', 'olarik.s@msu.ac.th, jit@msu.ac.th', '0866361258', 'ไม่จำกัดจำนวนบทความ ฟรี'],
        ['Journal of Engineering and Industrial Technology, Kalasin University', 'https://ph03.tci-thaijo.org/index.php/JEIT', 'Faculty of Engineering and Industrial Technology, Kalasin University', 'Dr. Sarayut Thitapars', 'Editor-in-Chief', 'jeit@ksu.ac.th', '0885742199', 'Call for Papers, Journal Partnership & Support, Selected Papers Publication, Co-organizer / Co-hosting'],
    ];

    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 500)->nullable();      // where the author submits after accepting
            $table->string('publisher', 500)->nullable(); // affiliation of the journal
            $table->string('contact_name')->nullable();
            $table->string('contact_position')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('terms')->nullable();            // what the journal offers (fees, number of papers)
            $table->boolean('active')->default(true);     // inactive: kept for past papers, not offered any more
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('journals')->insert(collect(self::JOURNALS)->map(fn (array $j, int $i) => [
            'name' => $j[0], 'url' => $j[1], 'publisher' => $j[2], 'contact_name' => $j[3], 'contact_position' => $j[4],
            'contact_email' => $j[5], 'contact_phone' => $j[6], 'terms' => $j[7], 'active' => true, 'sort_order' => $i + 1,
            'created_at' => $now, 'updated_at' => $now,
        ])->all());

        Schema::table('submissions', function (Blueprint $table) {
            // The journal offered to the author (kept after the answer, for the history and the journal's list)
            $table->foreignId('journal_id')->nullable()->after('track_id')->constrained()->nullOnDelete();
            $table->date('journal_answer_due')->nullable()->after('journal_id'); // last day to answer the offer
            $table->timestamp('journal_reminded_at')->nullable()->after('journal_answer_due');
        });

        // The single journal link is replaced by each journal's own URL
        DB::table('settings')->where('key', 'journal_url')->delete();
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_id');
            $table->dropColumn(['journal_answer_due', 'journal_reminded_at']);
        });
        Schema::dropIfExists('journals');
    }
};
