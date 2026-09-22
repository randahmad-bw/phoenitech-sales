<?php

namespace Database\Seeders;

use App\Models\ServerSubscription;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds server/email/domain/VPS/SSL infrastructure subscriptions.
 *
 * Data source: https://reports.bw-businessworld.net/subs.html
 *
 * NOTE: This is a completely isolated table ('server_subscriptions').
 * It has NO relation, foreign keys, or interaction with the 'contracts',
 * 'companies', or 'clients' tables.
 */
class ServerSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $subscriptions = [
            // ─── VPS ───────────────────────────────────────────────
            [
                'company' => 'BW Business World',
                'name' => 'Managed Linux VPS L',
                'type' => 'vps',
                'domain' => 'server.bw-businessworld.net',
                'provider' => 'GoDaddy',
                'end_date' => '2026-06-06',
            ],
            [
                'company' => 'Smart Kids Montessori',
                'name' => 'Soar 32',
                'type' => 'vps',
                'domain' => 'server.smartkidsmontessori.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-12-21',
            ],

            // ─── Hosting ───────────────────────────────────────────
            [
                'company' => 'Smart Kids Montessori',
                'name' => 'Turbo Boost Web Hosting',
                'type' => 'hosting',
                'domain' => 'smartkidsmontessori.net',
                'provider' => 'GoDaddy',
                'end_date' => '2026-09-24',
            ],
            [
                'company' => 'Al Reem Smart Kids',
                'name' => 'Web Hosting Plus (AutoSSL)',
                'type' => 'hosting',
                'domain' => 'alreemsmartkids.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-04',
            ],
            [
                'company' => 'Capriani Gelato',
                'name' => 'Web Hosting Deluxe',
                'type' => 'hosting',
                'domain' => 'caprianigelato.com',
                'provider' => 'GoDaddy',
                'end_date' => '2026-06-15',
            ],
            [
                'company' => 'Capriani Gelato',
                'name' => 'Web Hosting Economy',
                'type' => 'hosting',
                'domain' => 'thecaprigelato.com',
                'provider' => 'GoDaddy',
                'end_date' => '2026-03-20',
            ],

            // ─── Domains ──────────────────────────────────────────
            [
                'company' => 'BW Business World',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'bw-businessworld.net',
                'provider' => 'GoDaddy',
                'end_date' => '2026-06-06',
            ],
            [
                'company' => 'Smart Kids Montessori',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'smartkidsmontessori.net',
                'provider' => 'GoDaddy',
                'end_date' => '2025-12-11',
            ],
            [
                'company' => 'Smart Kids Montessori',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'smartkidsmontessori.com',
                'provider' => 'GoDaddy',
                'end_date' => '2026-09-14',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'bw-businessworld.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-12-28',
            ],
            [
                'company' => 'Al Reem Smart Kids',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'alreemsmartkids.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-05',
            ],
            [
                'company' => 'Smart Kids Montessori',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'montessorismartkids.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-05',
            ],
            [
                'company' => 'Capriani Gelato',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'thecaprigelato.com',
                'provider' => 'GoDaddy',
                'end_date' => '2026-05-15',
            ],
            [
                'company' => 'Capriani Gelato',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'caprianigelato.com',
                'provider' => 'GoDaddy',
                'end_date' => '2026-01-22',
            ],
            [
                'company' => 'The Travel Tent',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'thetraveltent.net',
                'provider' => 'GoDaddy',
                'end_date' => '2025-12-14',
            ],
            [
                'company' => 'Karam Safadi',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'karamsafadi.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-19',
            ],
            [
                'company' => 'Karam Safadi',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'safadiamjad.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-19',
            ],
            [
                'company' => 'Karam Safadi',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'amjadsafadi.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-19',
            ],
            [
                'company' => 'Cadeau Boutique',
                'name' => 'Domain Registration',
                'type' => 'domain',
                'domain' => 'cadeauboutique.com',
                'provider' => 'GoDaddy',
                'end_date' => '2025-11-30',
            ],

            // ─── SSL ──────────────────────────────────────────────
            [
                'company' => 'Capriani Gelato',
                'name' => 'Standard SSL',
                'type' => 'ssl',
                'domain' => 'caprianigelato.com',
                'provider' => 'GoDaddy',
                'end_date' => '2026-01-22',
            ],

            // ─── Email (Microsoft 365) ────────────────────────────
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials with Security',
                'type' => 'email',
                'domain' => 'damac@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-04',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'amjad@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-20',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'antoine.haddad@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-20',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'info@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-20',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'eyad@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-20',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'mohd.safadi@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-22',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'saher@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-22',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'hr@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-02-22',
            ],
            [
                'company' => 'BW Business World',
                'name' => 'Microsoft 365 Email Essentials',
                'type' => 'email',
                'domain' => 'adnan.shammout@bw-businessworld.com',
                'provider' => 'Microsoft 365 / GoDaddy',
                'end_date' => '2026-09-04',
            ],
        ];

        foreach ($subscriptions as $sub) {
            $endDate = Carbon::parse($sub['end_date']);
            $startDate = $endDate->copy()->subYear();

            $status = 'active';
            if ($endDate->isPast()) {
                $status = 'expired';
            } elseif ($endDate->diffInDays(Carbon::today()) <= 30) {
                $status = 'expiring_soon';
            }

            ServerSubscription::updateOrCreate(
                [
                    'name' => $sub['name'],
                    'domain' => $sub['domain'],
                    'company_name' => $sub['company'],
                ],
                [
                    'type' => $sub['type'],
                    'provider' => $sub['provider'] ?? 'GoDaddy',
                    'cost' => 0.00,
                    'currency' => 'USD',
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $sub['end_date'],
                    'status' => $status,
                    'notes' => null,
                ]
            );
        }
    }
}
