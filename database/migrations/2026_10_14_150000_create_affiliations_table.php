<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliations become a list admin manages (Settings › Affiliations). users.affiliation keeps the code
 * (e.g. 'msu'); 'other' means the registrant typed their own, kept in users.affiliation_other.
 */
return new class extends Migration
{
    /** Starting list: the codes the sign-up form already used. */
    private const STARTING = [
        'msu' => ['Mahasarakham University', 'มหาวิทยาลัยมหาสารคาม'],
        'kku' => ['Khon Kaen University', 'มหาวิทยาลัยขอนแก่น'],
        'cmu' => ['Chiang Mai University', 'มหาวิทยาลัยเชียงใหม่'],
        'nu' => ['Naresuan University', 'มหาวิทยาลัยนเรศวร'],
        'rmu' => ['Rajabhat Maha Sarakham University', 'มหาวิทยาลัยราชภัฏมหาสารคาม'],
        'ubu' => ['Ubon Ratchathani University', 'มหาวิทยาลัยอุบลราชธานี'],
        'tu' => ['Thammasat University', 'มหาวิทยาลัยธรรมศาสตร์'],
        'kmitl' => ["King Mongkut's Institute of Technology Ladkrabang", 'สถาบันเทคโนโลยีพระจอมเกล้าเจ้าคุณทหารลาดกระบัง'],
        'obec' => ['Office of the Basic Education Commission', 'สำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน'],
        'ksu' => ['Kalasin University', 'มหาวิทยาลัยกาฬสินธุ์'],
    ];

    public function up(): void
    {
        Schema::create('affiliations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // stored in users.affiliation
            $table->string('name_en');
            $table->string('name_th');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('affiliation_other')->nullable()->after('affiliation');
        });

        $now = now();
        $order = 0;
        foreach (self::STARTING as $code => [$en, $th]) {
            DB::table('affiliations')->insert(['code' => $code, 'name_en' => $en, 'name_th' => $th, 'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('affiliation_other');
        });
        Schema::dropIfExists('affiliations');
    }
};
