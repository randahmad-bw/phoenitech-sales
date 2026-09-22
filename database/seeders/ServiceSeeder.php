<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Seeds the 8 predefined services with bilingual names.
 */
class ServiceSeeder extends Seeder
{
    /**
     * Insert default service records.
     */
    public function run(): void
    {
        $services = [
            ['name_ar' => 'سوشيال', 'name_en' => 'Social Media'],
            ['name_ar' => 'منيو الكتروني', 'name_en' => 'E-Menu'],
            ['name_ar' => 'تطوير خاص', 'name_en' => 'Custom Development'],
            ['name_ar' => 'منصة توظيف', 'name_en' => 'Recruitment Platform'],
            ['name_ar' => 'تطوير مواقع', 'name_en' => 'Website Development'],
            ['name_ar' => 'تطبيق موبايل', 'name_en' => 'Mobile Application'],
            ['name_ar' => 'تسويق رقمي', 'name_en' => 'Digital Marketing'],
            ['name_ar' => 'استضافة', 'name_en' => 'Hosting'],
            ['name_ar' => 'نظام ERP', 'name_en' => 'ERP'],
            ['name_ar' => 'نظام CRM', 'name_en' => 'CRM'],
            ['name_ar' => 'أخرى', 'name_en' => 'Other'],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['name_en' => $service['name_en']],
                $service
            );
        }
    }
}
