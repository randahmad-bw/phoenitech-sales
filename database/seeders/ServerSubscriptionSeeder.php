<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Seeds server/email/domain/VPS/SSL subscriptions as contracts.
 *
 * Data source: https://reports.bw-businessworld.net/subs.html
 *
 * These are infrastructure subscriptions (hosting, domains, emails, VPS, SSL)
 * managed by OnoCode for various clients. Each subscription is stored as a
 * contract with category='hosting' and product='onocode'.
 *
 * Companies seeded here:
 *   - BW Business World          (internal — 9 email subs + 2 domains + 1 VPS)
 *   - Smart Kids Montessori      (3 domains + 1 VPS + 1 hosting)
 *   - Al Reem Smart Kids         (1 hosting + 1 domain)
 *   - Capriani Gelato            (2 hostings + 2 domains + 1 SSL)
 *   - The Travel Tent            (1 domain)
 *   - Karam Safadi               (3 personal domains)
 *   - Cadeau Boutique            (1 domain)
 */
class ServerSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure 'Hosting' service exists (created by ServiceSeeder)
        $hostingService = Service::where('name_en', 'Hosting')->first();
        if (!$hostingService) {
            $hostingService = Service::create([
                'name_ar' => 'استضافة',
                'name_en' => 'Hosting',
            ]);
        }

        // Find admin employee for assignment
        $admin = Employee::where('name', 'like', 'الإدارة%')->first();
        $adminId = $admin?->id;

        // ── Company mapping ────────────────────────────────────────
        // Group subscriptions by owner company based on domain names.
        $companiesMap = [
            'BW Business World' => [
                'activity' => 'خدمات أعمال',
            ],
            'Smart Kids Montessori' => [
                'activity' => 'تعليم',
            ],
            'Al Reem Smart Kids' => [
                'activity' => 'تعليم',
            ],
            'Capriani Gelato' => [
                'activity' => 'مطعم آيسكريم',
            ],
            'The Travel Tent' => [
                'activity' => 'سياحة وسفر',
            ],
            'Karam Safadi' => [
                'activity' => 'شخصي',
            ],
            'Cadeau Boutique' => [
                'activity' => 'متجر هدايا',
            ],
        ];

        $companies = [];
        foreach ($companiesMap as $name => $data) {
            $companies[$name] = Company::updateOrCreate(
                ['name' => $name],
                [
                    'activity'    => $data['activity'],
                    'employee_id' => $adminId,
                ]
            );
        }

        // ── Subscriptions data from the dashboard ──────────────────
        // Each entry maps to a contract. 'company' key links to the
        // company created above. 'notes' contains the service name + domain/email.
        $subscriptions = [
            // ─── VPS ───────────────────────────────────────────────
            [
                'company'    => 'BW Business World',
                'name'       => 'Managed Linux VPS L',
                'type'       => 'vps',
                'domain'     => 'server.bw-businessworld.net',
                'end_date'   => '2026-06-06',
            ],
            [
                'company'    => 'Smart Kids Montessori',
                'name'       => 'Soar 32',
                'type'       => 'vps',
                'domain'     => 'server.smartkidsmontessori.com',
                'end_date'   => '2025-12-21',
            ],

            // ─── Hosting ───────────────────────────────────────────
            [
                'company'    => 'Smart Kids Montessori',
                'name'       => 'Turbo Boost Web Hosting',
                'type'       => 'hosting',
                'domain'     => 'smartkidsmontessori.net',
                'end_date'   => '2026-09-24',
            ],
            [
                'company'    => 'Al Reem Smart Kids',
                'name'       => 'Web Hosting Plus (AutoSSL)',
                'type'       => 'hosting',
                'domain'     => 'alreemsmartkids.com',
                'end_date'   => '2025-11-04',
            ],
            [
                'company'    => 'Capriani Gelato',
                'name'       => 'Web Hosting Deluxe',
                'type'       => 'hosting',
                'domain'     => 'caprianigelato.com',
                'end_date'   => '2026-06-15',
            ],
            [
                'company'    => 'Capriani Gelato',
                'name'       => 'Web Hosting Economy',
                'type'       => 'hosting',
                'domain'     => 'thecaprigelato.com',
                'end_date'   => '2026-03-20',
            ],

            // ─── Domains ──────────────────────────────────────────
            [
                'company'    => 'BW Business World',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'bw-businessworld.net',
                'end_date'   => '2026-06-06',
            ],
            [
                'company'    => 'Smart Kids Montessori',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'smartkidsmontessori.net',
                'end_date'   => '2025-12-11',
            ],
            [
                'company'    => 'Smart Kids Montessori',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'smartkidsmontessori.com',
                'end_date'   => '2026-09-14',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'bw-businessworld.com',
                'end_date'   => '2025-12-28',
            ],
            [
                'company'    => 'Al Reem Smart Kids',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'alreemsmartkids.com',
                'end_date'   => '2025-11-05',
            ],
            [
                'company'    => 'Smart Kids Montessori',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'montessorismartkids.com',
                'end_date'   => '2025-11-05',
            ],
            [
                'company'    => 'Capriani Gelato',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'thecaprigelato.com',
                'end_date'   => '2026-05-15',
            ],
            [
                'company'    => 'Capriani Gelato',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'caprianigelato.com',
                'end_date'   => '2026-01-22',
            ],
            [
                'company'    => 'The Travel Tent',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'thetraveltent.net',
                'end_date'   => '2025-12-14',
            ],
            [
                'company'    => 'Karam Safadi',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'karamsafadi.com',
                'end_date'   => '2025-11-19',
            ],
            [
                'company'    => 'Karam Safadi',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'safadiamjad.com',
                'end_date'   => '2025-11-19',
            ],
            [
                'company'    => 'Karam Safadi',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'amjadsafadi.com',
                'end_date'   => '2025-11-19',
            ],
            [
                'company'    => 'Cadeau Boutique',
                'name'       => 'Domain Registration',
                'type'       => 'domain',
                'domain'     => 'cadeauboutique.com',
                'end_date'   => '2025-11-30',
            ],

            // ─── SSL ──────────────────────────────────────────────
            [
                'company'    => 'Capriani Gelato',
                'name'       => 'Standard SSL',
                'type'       => 'ssl',
                'domain'     => 'caprianigelato.com',
                'end_date'   => '2026-01-22',
            ],

            // ─── Email (Microsoft 365) ────────────────────────────
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials with Security',
                'type'       => 'email',
                'domain'     => 'damac@bw-businessworld.com',
                'end_date'   => '2026-02-04',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'amjad@bw-businessworld.com',
                'end_date'   => '2026-02-20',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'antoine.haddad@bw-businessworld.com',
                'end_date'   => '2026-02-20',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'info@bw-businessworld.com',
                'end_date'   => '2026-02-20',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'eyad@bw-businessworld.com',
                'end_date'   => '2026-02-20',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'mohd.safadi@bw-businessworld.com',
                'end_date'   => '2026-02-22',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'saher@bw-businessworld.com',
                'end_date'   => '2026-02-22',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'hr@bw-businessworld.com',
                'end_date'   => '2026-02-22',
            ],
            [
                'company'    => 'BW Business World',
                'name'       => 'Microsoft 365 Email Essentials',
                'type'       => 'email',
                'domain'     => 'adnan.shammout@bw-businessworld.com',
                'end_date'   => '2026-09-04',
            ],
        ];

        $year = now()->year;
        // Start numbering after existing contracts to avoid conflicts
        $lastContract = Contract::orderByDesc('id')->first();
        $counter = $lastContract ? $lastContract->id + 100 : 100;

        foreach ($subscriptions as $sub) {
            $company = $companies[$sub['company']] ?? null;
            if (!$company) {
                continue;
            }

            $counter++;
            $contractNumber = sprintf('SRV-%d-%04d', $year, $counter);

            // Calculate start_date: 1 year before end_date (annual subs)
            $endDate = \Carbon\Carbon::parse($sub['end_date']);
            $startDate = $endDate->copy()->subYear();

            // Build descriptive notes: "Service Type | Service Name | domain/email"
            $typeLabels = [
                'vps'     => 'VPS',
                'hosting' => 'استضافة',
                'domain'  => 'دومين',
                'email'   => 'بريد إلكتروني',
                'ssl'     => 'SSL',
            ];
            $typeLabel = $typeLabels[$sub['type']] ?? $sub['type'];
            $notes = "{$typeLabel} | {$sub['name']} | {$sub['domain']}";

            Contract::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'category'   => $sub['type'],
                    'notes'      => $notes,
                ],
                [
                    'contract_number'     => $contractNumber,
                    'employee_id'         => $adminId,
                    'service_id'          => $hostingService->id,
                    'contract_value'      => 0,
                    'currency'            => 'USD',
                    'start_date'          => $startDate->format('Y-m-d'),
                    'end_date'            => $sub['end_date'],
                    'status'              => $endDate->isPast() ? 'completed' : 'active',
                    'progress_percentage' => 0,
                    'product'             => 'onocode',
                ]
            );
        }
    }
}
