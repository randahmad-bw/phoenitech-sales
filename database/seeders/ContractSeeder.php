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
 * Seeds sample contracts with associated payments for development and testing.
 */
class ContractSeeder extends Seeder
{
    /**
     * Insert sample contracts and their payment records.
     */
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
                'status' => 'completed',
                'progress' => 100,
                'notes' => 'مدة العقد: شهر',
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
                'notes' => 'الباقة: 6 ريل + 6 بوست. مدة العقد: شهر',
                'file_name' => 'الأسطورة.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'جدارات',
                'employee_name' => 'الإدارة',
                'service_name' => 'تطوير خاص',
                'value' => 1200,
                'start_date' => '2026-05-04',
                'end_date' => '2026-07-15',
                'status' => 'completed',
                'progress' => 100,
                'notes' => 'موقع جدارات. مدة العقد: شهرين وأسبوع',
                'file_name' => 'موقع جدارات.pdf',
                'payments' => [600, 600],
            ],
            [
                'company_name' => 'جدارات',
                'employee_name' => 'الإدارة',
                'service_name' => 'سوشيال',
                'value' => 1800,
                'start_date' => '2026-05-04',
                'end_date' => '2027-05-04',
                'status' => 'active',
                'progress' => 20,
                'notes' => 'جدارات. الباقة: 6 ريل + 6 بوست. مدة العقد: سنة',
                'file_name' => 'جدارات.pdf',
                'payments' => [300, 300],
            ],
            [
                'company_name' => 'صعب لوجيستك',
                'employee_name' => 'الإدارة',
                'service_name' => 'تطوير خاص',
                'value' => 600,
                'start_date' => null,
                'end_date' => null,
                'status' => 'active',
                'progress' => 10,
                'notes' => 'صعب. مدة العقد: شهر وأسبوع',
                'file_name' => 'صعب.pdf',
                'payments' => [300],
            ],
            [
                'company_name' => 'برو كافيه',
                'employee_name' => 'مايكل',
                'service_name' => 'سوشيال',
                'value' => 150,
                'start_date' => '2026-06-01',
                'end_date' => '2026-07-01',
                'status' => 'completed',
                'progress' => 100,
                'notes' => 'برو. الباقة: 8 ريل + 6 بوست + 15 ستوري. مدة العقد: شهر',
                'file_name' => 'برو.pdf',
                'payments' => [150],
            ],
            [
                'company_name' => 'أغاليا',
                'employee_name' => 'سارة',
                'service_name' => 'سوشيال',
                'value' => 1500,
                'start_date' => '2026-07-27',
                'end_date' => '2027-07-27',
                'status' => 'signed',
                'progress' => 0,
                'notes' => 'أغافيا. الباقة: 6 ريل + 10 بوست + 15 ستوري. مدة العقد: سنة (دفع شهري)',
                'file_name' => 'أغافيا.pdf',
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
                'notes' => 'المول الصيني. مدة العقد: شهر',
                'file_name' => 'المول الصيني.pdf',
                'payments' => [100],
            ],
            [
                'company_name' => 'وصل',
                'employee_name' => 'الإدارة',
                'service_name' => 'سوشيال',
                'value' => 700,
                'start_date' => '2026-07-13',
                'end_date' => '2026-08-13',
                'status' => 'completed',
                'progress' => 100,
                'notes' => 'شركة التداول',
                'file_name' => ' ',
                'payments' => [700],
            ],
        ];

        $year = now()->year;
        $counter = 1;

        foreach ($contractsData as $data) {
            $company = Company::where('name', $data['company_name'])->first();
            $employee = Employee::where('name', 'like', $data['employee_name'] . '%')->first();
            $service = Service::where('name_ar', $data['service_name'])
                ->orWhere('name_en', $data['service_name'])
                ->first();

            if (!$company || !$employee || !$service) {
                continue;
            }

            $contractNumber = sprintf('CNT-%d-%04d', $year, $counter++);

            $contract = Contract::updateOrCreate(
                ['company_id' => $company->id, 'service_id' => $service->id, 'start_date' => $data['start_date']],
                [
                    'contract_number' => $contractNumber,
                    'employee_id' => $employee->id,
                    'contract_value' => $data['value'],
                    'currency' => 'USD',
                    'end_date' => $data['end_date'],
                    'status' => $data['status'],
                    'progress_percentage' => $data['progress'],
                    'notes' => $data['notes'],
                ]
            );

            // Seed Payments
            foreach ($data['payments'] as $paymentIndex => $amount) {
                Payment::updateOrCreate(
                    [
                        'contract_id' => $contract->id,
                        'amount' => $amount,
                        'payment_date' => $data['start_date'] ?? now()->format('Y-m-d'),
                    ],
                    [
                        'method' => 'cash',
                        'status' => 'paid',
                        'notes' => 'دفعة مسددة من خلال Seeder',
                    ]
                );
            }

            // Seed Mock Attachment
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
