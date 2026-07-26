<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\SocialMedia\SmPackage;
use App\Models\SocialMedia\ContentPlan;
use App\Models\SocialMedia\ContentItem;
use App\Models\SocialMedia\PhotoSession;
use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Seeds social media packages, plans, items, and sessions for real contracts.
 */
class SocialMediaSeeder extends Seeder
{
    public function run(): void
    {
        $designers = Employee::whereIn('department', ['design', 'photography', 'video'])->get();
        if ($designers->isEmpty()) {
            $designers = Employee::take(3)->get();
        }

        // 1. Contract: المخبر (Laboratory)
        $makhbarContract = Contract::whereHas('company', function ($q) {
            $q->where('name', 'like', '%المخبر%');
        })->first();

        if ($makhbarContract) {
            $package = SmPackage::updateOrCreate(
                ['contract_id' => $makhbarContract->id],
                [
                    'package_name' => 'باقة عقد المخبر',
                    'monthly_posts' => 6,
                    'monthly_reels' => 6,
                    'monthly_stories' => 12,
                    'notes' => '6 ريل + 6 بوست + 12 ستوري شهرياً',
                ]
            );

            $plan = ContentPlan::updateOrCreate(
                [
                    'contract_id' => $makhbarContract->id,
                    'month' => now()->month,
                    'year' => now()->year,
                ],
                [
                    'company_id' => $makhbarContract->company_id,
                    'sm_package_id' => $package->id,
                    'status' => 'active',
                    'notes' => 'خطة شهر ' . now()->month . ' لمخبر التحاليل الطبية',
                ]
            );

            $session = PhotoSession::create([
                'content_plan_id' => $plan->id,
                'company_id' => $makhbarContract->company_id,
                'session_date' => now()->addDays(2)->toDateString(),
                'session_time' => '10:00',
                'photographer_id' => $designers->where('department', 'photography')->first()->id ?? $designers->first()->id,
                'status' => 'scheduled',
                'notes' => 'تصوير فيديوهات ريلز داخل المخبر (الأجهزة والفريق)',
            ]);

            $today = now()->toDateString();
            $tomorrow = now()->addDay()->toDateString();

            // 6 Reels for المخبر
            $reels = [
                ['title' => 'ريلز 1: جولة داخل أفرع المخبر وأحدث أجهزة التحاليل', 'pub' => $today, 'designed' => true, 'pubd' => false],
                ['title' => 'ريلز 2: أهم الفحوصات الطبية الواجب إجراؤها بشكل دوري', 'pub' => $tomorrow, 'designed' => true, 'pubd' => false],
                ['title' => 'ريلز 3: طريقة سحب العينات بسلاسة وبدون ألم', 'pub' => now()->addDays(4)->toDateString(), 'designed' => true, 'pubd' => false],
                ['title' => 'ريلز 4: خدمة السحب المنزلي المجاني لكبار السن', 'pub' => now()->addDays(7)->toDateString(), 'designed' => false, 'pubd' => false],
                ['title' => 'ريلز 5: فحص الفيتامينات والمعادن وأثره على الصحة', 'pub' => now()->addDays(10)->toDateString(), 'designed' => false, 'pubd' => false],
                ['title' => 'ريلز 6: كيف تقرأ نتائج التحاليل بنفسك؟', 'pub' => now()->addDays(14)->toDateString(), 'designed' => false, 'pubd' => false],
            ];

            foreach ($reels as $i => $r) {
                ContentItem::create([
                    'content_plan_id' => $plan->id,
                    'title' => $r['title'],
                    'content_type' => 'reel',
                    'design_date' => now()->subDays(3 - $i)->toDateString(),
                    'publish_date' => $r['pub'],
                    'assigned_to' => $designers[$i % $designers->count()]->id,
                    'photo_session_id' => $session->id,
                    'is_designed' => $r['designed'],
                    'is_published' => $r['pubd'],
                    'status' => $r['pubd'] ? 'completed' : ($r['designed'] ? 'in_progress' : 'pending'),
                ]);
            }

            // 6 Posts for المخبر
            $posts = [
                ['title' => 'بوست 1: انفوجرافيك - شروط صيام تحليل السكر والتراكمي', 'pub' => now()->addDays(1)->toDateString(), 'designed' => true, 'pubd' => true],
                ['title' => 'بوست 2: باقة الفحص الشامل خصم 25% هذا الشهر', 'pub' => now()->addDays(3)->toDateString(), 'designed' => true, 'pubd' => false],
                ['title' => 'بوست 3: نصائح طبية للحفاظ على صحة الكبد والكلى', 'pub' => now()->addDays(5)->toDateString(), 'designed' => false, 'pubd' => false],
                ['title' => 'بوست 4: أوقات الدوام الرسمية وساعات عمل المخبر', 'pub' => now()->addDays(8)->toDateString(), 'designed' => false, 'pubd' => false],
                ['title' => 'بوست 5: خدمة استلام النتائج عبر الواتساب فور ظهورها', 'pub' => now()->addDays(11)->toDateString(), 'designed' => false, 'pubd' => false],
                ['title' => 'بوست 6: أهمية فحص الغدة الدرقية والأعراض الشائعة', 'pub' => now()->addDays(15)->toDateString(), 'designed' => false, 'pubd' => false],
            ];

            foreach ($posts as $i => $p) {
                ContentItem::create([
                    'content_plan_id' => $plan->id,
                    'title' => $p['title'],
                    'content_type' => 'post',
                    'design_date' => now()->addDays($i)->toDateString(),
                    'publish_date' => $p['pub'],
                    'assigned_to' => $designers[$i % $designers->count()]->id,
                    'is_designed' => $p['designed'],
                    'is_published' => $p['pubd'],
                    'status' => $p['pubd'] ? 'completed' : ($p['designed'] ? 'in_progress' : 'pending'),
                ]);
            }
        }

        // Seed packages for other social contracts
        $otherSocialContracts = Contract::where('category', 'social')
            ->where('id', '!=', $makhbarContract?->id)
            ->get();

        foreach ($otherSocialContracts as $sc) {
            SmPackage::updateOrCreate(
                ['contract_id' => $sc->id],
                [
                    'package_name' => 'باقة ' . ($sc->company?->name ?? 'العقد'),
                    'monthly_posts' => 6,
                    'monthly_reels' => 6,
                    'monthly_stories' => 12,
                ]
            );
        }
    }
}
