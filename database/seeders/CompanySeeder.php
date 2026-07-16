<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Seeds sample companies with associated contact persons.
 */
class CompanySeeder extends Seeder
{
    /**
     * Insert sample companies and their contacts.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'مقهى اليغرو',
                'employee_name' => 'مايكل',
                'activity' => 'مقهى',
                'contacts' => [
                    ['name' => 'ابراهيم مهنا', 'position' => 'العميل', 'mobile' => '0991403366'],
                ],
            ],
            [
                'name' => 'المصور إحسان',
                'employee_name' => 'مايكل',
                'activity' => 'تصوير',
                'contacts' => [
                    ['name' => 'وائل مصري زاده', 'position' => 'العميل', 'mobile' => '0947773477'],
                ],
            ],
            [
                'name' => 'المخبر',
                'employee_name' => 'الإدارة',
                'activity' => 'مخبر',
                'contacts' => [
                    ['name' => 'نور المصري', 'position' => 'العميل', 'mobile' => '0944759224'],
                ],
            ],
            [
                'name' => 'يامي ستور',
                'employee_name' => 'سارة',
                'activity' => 'متجر',
                'contacts' => [
                    ['name' => 'منار صديق صالح', 'position' => 'العميل', 'mobile' => '0938892764'],
                ],
            ],
            [
                'name' => 'الأسطورة',
                'employee_name' => 'مايكل',
                'activity' => 'ملابس',
                'contacts' => [
                    ['name' => 'عبد الرحمن عبد الكريم', 'position' => 'العميل', 'mobile' => '0969086957'],
                ],
            ],
            [
                'name' => 'جدارات',
                'employee_name' => 'الإدارة',
                'activity' => 'مقاولات',
                'contacts' => [
                    ['name' => 'كمال خيال', 'position' => 'العميل', 'mobile' => null],
                ],
            ],
            [
                'name' => 'صعب لوجيستك',
                'employee_name' => 'الإدارة',
                'activity' => 'خدمات لوجستية',
                'contacts' => [],
            ],
            [
                'name' => 'برو كافيه',
                'employee_name' => 'مايكل',
                'activity' => 'مقهى',
                'contacts' => [
                    ['name' => 'مرهف، ابراهيم شمعة', 'position' => 'العميل', 'mobile' => '0983475488'],
                ],
            ],
            [
                'name' => 'أغافيا',
                'employee_name' => 'سارة',
                'activity' => 'مطعم',
                'contacts' => [
                    ['name' => 'كنانة رضوان', 'position' => 'العميل', 'mobile' => '0992501501'],
                ],
            ],
            [
                'name' => 'المول الصيني',
                'employee_name' => 'سارة',
                'activity' => 'مركز تجاري',
                'contacts' => [
                    ['name' => 'اليسار جمعة', 'position' => 'العميل', 'mobile' => '0985221467'],
                ],
            ],
        ];

        foreach ($companies as $companyData) {
            $contacts = $companyData['contacts'];
            unset($companyData['contacts']);

            $employee = Employee::where('name', 'like', $companyData['employee_name'] . '%')->first();
            $companyData['employee_id'] = $employee ? $employee->id : null;
            unset($companyData['employee_name']);

            $company = Company::updateOrCreate(
                ['name' => $companyData['name']],
                $companyData
            );

            foreach ($contacts as $contactData) {
                Contact::updateOrCreate(
                    ['company_id' => $company->id, 'name' => $contactData['name']],
                    array_merge($contactData, ['company_id' => $company->id])
                );
            }
        }
    }
}
