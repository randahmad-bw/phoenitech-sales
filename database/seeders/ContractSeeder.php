<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Attachment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds contracts matching the production database dump.
 */
class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $contractsData = [
            [
                'company_name' => 'مقهى اليغرو',
                'employee_name' => 'مايكل',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-01-10',
                'end_date' => '2026-02-10',
                'status' => 'completed',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'الباقة: 6 ريل + 6 بوست. مدة العقد: شهر',
                'file_name' => 'اليغرو.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'المصور إحسان',
                'employee_name' => 'مايكل',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-01-27',
                'end_date' => '2026-02-27',
                'status' => 'completed',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'الباقة: 6 ريل + 6 بوست. مدة العقد: شهر',
                'file_name' => 'إحسان.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'المخبر',
                'employee_name' => 'الإدارة',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-02-28',
                'end_date' => '2026-03-28',
                'status' => 'active',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'الباقة: 6 ريل + 6 بوست. مدة العقد: شهر',
                'file_name' => 'مخبر.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'يامي ستور',
                'employee_name' => 'سارة',
                'service_name' => 'منيو الكتروني',
                'value' => 100,
                'start_date' => '2026-04-20',
                'end_date' => '2027-04-20',
                'status' => 'active',
                'progress' => 30,
                'category' => 'menu',
                'notes' => 'مدة العقد: سنة',
                'file_name' => 'يامي ستور.pdf',
                'payments' => [100],
            ],
            [
                'company_name' => 'الأسطورة',
                'employee_name' => 'مايكل',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-04-28',
                'end_date' => '2026-05-28',
                'status' => 'completed',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'الباقة: 6 ريل + 6 بوست. مدة العقد: شهر',
                'file_name' => 'الأسطورة.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'جدارات - موقع',
                'employee_name' => 'الإدارة',
                'service_name' => 'تطوير خاص',
                'value' => 1200,
                'start_date' => '2026-05-04',
                'end_date' => '2026-07-15',
                'status' => 'completed',
                'progress' => 100,
                'category' => 'custom_dev',
                'notes' => 'موقع جدارات. مدة العقد: شهرين وأسبوع',
                'file_name' => 'موقع جدارات.pdf',
                'payments' => [600, 600],
            ],
            [
                'company_name' => 'جدارات - سوشال',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 1800,
                'start_date' => '2026-05-04',
                'end_date' => '2027-05-04',
                'status' => 'active',
                'progress' => 20,
                'category' => 'social',
                'notes' => 'جدارات. الباقة: 6 ريل + 6 بوست. مدة العقد: سنة',
                'file_name' => 'جدارات.pdf',
                'payments' => [300, 300],
            ],
            [
                'company_name' => 'برو كافيه',
                'employee_name' => 'مايكل',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-06-01',
                'end_date' => '2026-07-01',
                'status' => 'active',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'برو. الباقة: 8 ريل + 6 بوست + 15 ستوري. مدة العقد: شهر',
                'file_name' => 'برو.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'أغافيا',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 1500,
                'start_date' => '2026-07-27',
                'end_date' => '2027-07-27',
                'status' => 'signed',
                'progress' => 0,
                'category' => 'social',
                'notes' => 'أغافيا. الباقة: 6 ريل + 10 بوست + 15 ستوري. مدة العقد: سنة',
                'file_name' => null,
                'payments' => [],
            ],
            [
                'company_name' => 'المول الصيني',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 100,
                'start_date' => '2026-06-10',
                'end_date' => '2026-07-10',
                'status' => 'completed',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'المول الصيني. مدة العقد: شهر',
                'file_name' => 'المول الصيني.pdf',
                'payments' => [100],
            ],
            [
                'company_name' => 'مركز أغاليا',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 1500,
                'start_date' => '2026-06-27',
                'end_date' => '2027-06-27',
                'status' => 'active',
                'progress' => 10,
                'category' => 'social',
                'notes' => 'سوشال ميديا',
                'file_name' => null,
                'payments' => [150],
            ],
            [
                'company_name' => 'سالي - سوشال',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-07-20',
                'end_date' => '2026-08-20',
                'status' => 'active',
                'progress' => 0,
                'category' => 'social',
                'notes' => '6 ريل + 6 بوست',
                'file_name' => null,
                'payments' => [150],
            ],
            [
                'company_name' => 'البلسم - سشال',
                'employee_name' => 'مايكل',
                'service_name' => 'سوشيال',
                'value' => 100,
                'start_date' => '2026-07-19',
                'end_date' => '2026-08-19',
                'status' => 'active',
                'progress' => 0,
                'category' => 'social',
                'notes' => '4 ريل - 4 بوست',
                'file_name' => null,
                'payments' => [100],
            ],
            [
                'company_name' => 'وصل - سوشال',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 400,
                'start_date' => '2026-07-14',
                'end_date' => '2026-08-12',
                'status' => 'active',
                'progress' => 100,
                'category' => 'social',
                'notes' => 'سوشال تداول',
                'file_name' => null,
                'payments' => [400],
            ],
            [
                'company_name' => 'الكورنيش - منيو',
                'employee_name' => 'مايكل',
                'service_name' => 'منيو الكتروني',
                'value' => 100,
                'start_date' => '2026-05-16',
                'end_date' => '2027-06-15',
                'status' => 'active',
                'progress' => 100,
                'category' => 'menu',
                'notes' => 'منيو الكورنيش',
                'file_name' => null,
                'payments' => [100],
            ],
        ];

        $year = now()->year;
        $counter = 1;

        foreach ($contractsData as $data) {
            $company = Company::where('name', $data['company_name'])->first();
            $employee = Employee::where('name', 'like', explode(' ', $data['employee_name'])[0] . '%')->first();
            $service = Service::where('name_ar', $data['service_name'])
                ->orWhere('name_en', $data['service_name'])
                ->first();

            if (!$company || !$employee) {
                continue;
            }

            $contractNumber = sprintf('CNT-%d-%04d', $year, $counter++);

            $contract = Contract::updateOrCreate(
                ['company_id' => $company->id, 'start_date' => $data['start_date']],
                [
                    'contract_number' => $contractNumber,
                    'company_id' => $company->id,
                    'employee_id' => $employee->id,
                    'service_id' => $service ? $service->id : 1,
                    'contract_value' => $data['value'],
                    'currency' => 'USD',
                    'end_date' => $data['end_date'],
                    'status' => $data['status'],
                    'progress_percentage' => $data['progress'],
                    'category' => $data['category'],
                    'notes' => $data['notes'],
                ]
            );

            // Payments
            foreach ($data['payments'] as $amount) {
                Payment::updateOrCreate(
                    [
                        'contract_id' => $contract->id,
                        'amount' => $amount,
                        'payment_date' => $data['start_date'] ?? now()->format('Y-m-d'),
                    ],
                    [
                        'method' => 'cash',
                        'status' => 'paid',
                        'notes' => 'دفعة مسددة',
                    ]
                );
            }

            // Mock Attachment
            if (!empty($data['file_name'])) {
                Attachment::updateOrCreate(
                    [
                        'attachable_type' => Contract::class,
                        'attachable_id' => $contract->id,
                        'original_name' => $data['file_name'],
                    ],
                    [
                        'stored_name' => Str::random(40) . '.pdf',
                        'disk' => 'public',
                        'path' => 'attachments/' . Str::random(40) . '.pdf',
                        'mime_type' => 'application/pdf',
                        'size_bytes' => rand(100000, 5000000),
                    ]
                );
            }
        }
    }
}
