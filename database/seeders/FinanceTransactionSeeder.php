<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FinanceTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::first()?->id;
        $events  = Event::orderBy('date')->get();

        // Map category names to IDs for convenient lookup
        $cat = FinanceCategory::pluck('id', 'name');

        $transactions = [

            // ════════════════════════════════════════════════════════
            // INCOME — Grants & Donations
            // ════════════════════════════════════════════════════════
            [
                'transaction_type' => 'income',
                'title'            => 'USDA Emergency Food Assistance Grant',
                'category_id'      => $cat['Government Grant'] ?? null,
                'amount'           => 12500.00,
                'transaction_date' => Carbon::now()->subMonths(11)->startOfMonth()->toDateString(),
                'source_or_payee'  => 'USDA Food & Nutrition Service',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'USDA-2026-0041',
                'status'           => 'completed',
                'notes'            => 'Annual emergency food assistance program grant.',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Community Foundation Spring Grant',
                'category_id'      => $cat['Foundation Grant'] ?? null,
                'amount'           => 5000.00,
                'transaction_date' => Carbon::now()->subMonths(9)->startOfMonth()->addDays(4)->toDateString(),
                'source_or_payee'  => 'Lancaster Community Foundation',
                'payment_method'   => 'Check',
                'reference_number' => 'LCF-2026-109',
                'status'           => 'completed',
                'notes'            => 'Spring cycle grant for food distribution operations.',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Walmart Community Grant',
                'category_id'      => $cat['Corporate Donation'] ?? null,
                'amount'           => 2000.00,
                'transaction_date' => Carbon::now()->subMonths(8)->startOfMonth()->addDays(10)->toDateString(),
                'source_or_payee'  => 'Walmart Foundation',
                'payment_method'   => 'Check',
                'reference_number' => 'WMT-GNT-0882',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Holiday Giving Campaign Donations',
                'category_id'      => $cat['Individual Donation'] ?? null,
                'amount'           => 3750.00,
                'transaction_date' => Carbon::now()->subMonths(7)->startOfMonth()->addDays(20)->toDateString(),
                'source_or_payee'  => 'Multiple Donors (Holiday Campaign)',
                'payment_method'   => 'Online',
                'status'           => 'completed',
                'notes'            => 'Aggregated online donations from December giving campaign.',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'St. John\'s Church Food Drive Proceeds',
                'category_id'      => $cat['Individual Donation'] ?? null,
                'amount'           => 875.00,
                'transaction_date' => Carbon::now()->subMonths(6)->startOfMonth()->addDays(7)->toDateString(),
                'source_or_payee'  => 'St. John\'s Lutheran Church',
                'payment_method'   => 'Cash',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Spring Fundraising Gala',
                'category_id'      => $cat['Fundraising Event'] ?? null,
                'amount'           => 6200.00,
                'transaction_date' => Carbon::now()->subMonths(5)->startOfMonth()->addDays(14)->toDateString(),
                'source_or_payee'  => 'Gala Ticket Sales & Pledges',
                'payment_method'   => 'Online',
                'reference_number' => 'GALA-2026-001',
                'status'           => 'completed',
                'notes'            => 'Annual fundraising gala — 62 attendees, net revenue after venue costs.',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'PA Dept of Agriculture — Hunger Relief',
                'category_id'      => $cat['Government Grant'] ?? null,
                'amount'           => 8000.00,
                'transaction_date' => Carbon::now()->subMonths(4)->startOfMonth()->addDays(1)->toDateString(),
                'source_or_payee'  => 'PA Department of Agriculture',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'PA-AGR-HR-2026-07',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Amazon Smile Quarterly Payout',
                'category_id'      => $cat['Corporate Donation'] ?? null,
                'amount'           => 312.45,
                'transaction_date' => Carbon::now()->subMonths(3)->startOfMonth()->addDays(5)->toDateString(),
                'source_or_payee'  => 'Amazon Smile Program',
                'payment_method'   => 'Bank Transfer',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Anonymous Major Donor',
                'category_id'      => $cat['Individual Donation'] ?? null,
                'amount'           => 5000.00,
                'transaction_date' => Carbon::now()->subMonths(2)->startOfMonth()->addDays(12)->toDateString(),
                'source_or_payee'  => 'Anonymous',
                'payment_method'   => 'Check',
                'status'           => 'completed',
                'notes'            => 'Received with instruction: "For community food distribution."',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Rotary Club Annual Donation',
                'category_id'      => $cat['Individual Donation'] ?? null,
                'amount'           => 1500.00,
                'transaction_date' => Carbon::now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
                'source_or_payee'  => 'Lancaster Rotary Club',
                'payment_method'   => 'Check',
                'reference_number' => 'ROT-2026-0044',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Online Giving Tuesday Campaign',
                'category_id'      => $cat['Individual Donation'] ?? null,
                'amount'           => 2280.00,
                'transaction_date' => Carbon::now()->subDays(18)->toDateString(),
                'source_or_payee'  => 'Multiple Donors (Giving Tuesday)',
                'payment_method'   => 'Online',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'income',
                'title'            => 'Pending Corporate Matching Gift',
                'category_id'      => $cat['Corporate Donation'] ?? null,
                'amount'           => 1000.00,
                'transaction_date' => Carbon::now()->subDays(5)->toDateString(),
                'source_or_payee'  => 'PNC Bank Foundation',
                'payment_method'   => 'Bank Transfer',
                'status'           => 'pending',
                'notes'            => 'Corporate match for employee donation — awaiting processing.',
            ],

            // ════════════════════════════════════════════════════════
            // EXPENSES — Operations
            // ════════════════════════════════════════════════════════
            [
                'transaction_type' => 'expense',
                'title'            => 'Monthly Warehouse Rent — January',
                'category_id'      => $cat['Venue & Facilities'] ?? null,
                'amount'           => 850.00,
                'transaction_date' => Carbon::now()->subMonths(10)->startOfMonth()->toDateString(),
                'source_or_payee'  => 'Park Street Properties LLC',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'INV-PSP-2026-01',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Food Bags — Bulk Order (500 units)',
                'category_id'      => $cat['Food & Supplies'] ?? null,
                'amount'           => 640.00,
                'transaction_date' => Carbon::now()->subMonths(9)->startOfMonth()->addDays(3)->toDateString(),
                'source_or_payee'  => 'Uline Supplies',
                'payment_method'   => 'Check',
                'reference_number' => 'ULINE-ORD-88241',
                'status'           => 'completed',
                'notes'            => '500 reinforced grocery bags, 8"x10"x16".',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Delivery Truck Fuel — Q1',
                'category_id'      => $cat['Transportation'] ?? null,
                'amount'           => 310.00,
                'transaction_date' => Carbon::now()->subMonths(9)->startOfMonth()->addDays(15)->toDateString(),
                'source_or_payee'  => 'Shell Gas Station',
                'payment_method'   => 'Cash',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Volunteer Appreciation Lunch',
                'category_id'      => $cat['Staff & Volunteer'] ?? null,
                'amount'           => 245.00,
                'transaction_date' => Carbon::now()->subMonths(7)->startOfMonth()->addDays(10)->toDateString(),
                'source_or_payee'  => 'Panera Bread',
                'payment_method'   => 'Cash',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Liability Insurance Annual Premium',
                'category_id'      => $cat['Insurance'] ?? null,
                'amount'           => 1200.00,
                'transaction_date' => Carbon::now()->subMonths(6)->startOfMonth()->toDateString(),
                'source_or_payee'  => 'State Farm Commercial',
                'payment_method'   => 'Check',
                'reference_number' => 'SF-POL-2026-0091',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Promotional Flyers — Spring Distribution',
                'category_id'      => $cat['Marketing & Outreach'] ?? null,
                'amount'           => 185.00,
                'transaction_date' => Carbon::now()->subMonths(5)->startOfMonth()->addDays(2)->toDateString(),
                'source_or_payee'  => 'FedEx Print & Ship',
                'payment_method'   => 'Online',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Label Printer & Barcode Scanner',
                'category_id'      => $cat['Equipment & Technology'] ?? null,
                'amount'           => 398.00,
                'transaction_date' => Carbon::now()->subMonths(4)->startOfMonth()->addDays(8)->toDateString(),
                'source_or_payee'  => 'Amazon Business',
                'payment_method'   => 'Online',
                'reference_number' => 'AMZ-ORD-114-5502',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Monthly Warehouse Rent — May',
                'category_id'      => $cat['Venue & Facilities'] ?? null,
                'amount'           => 850.00,
                'transaction_date' => Carbon::now()->subMonths(3)->startOfMonth()->toDateString(),
                'source_or_payee'  => 'Park Street Properties LLC',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'INV-PSP-2026-05',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Perishable Food Restocking',
                'category_id'      => $cat['Food & Supplies'] ?? null,
                'amount'           => 1145.00,
                'transaction_date' => Carbon::now()->subMonths(3)->startOfMonth()->addDays(6)->toDateString(),
                'source_or_payee'  => 'Regional Food Bank Wholesale',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'RFB-WHL-20260601',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Online Donation Platform Fees',
                'category_id'      => $cat['Administrative'] ?? null,
                'amount'           => 96.00,
                'transaction_date' => Carbon::now()->subMonths(2)->startOfMonth()->addDays(1)->toDateString(),
                'source_or_payee'  => 'Stripe / DonorBox',
                'payment_method'   => 'Online',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Monthly Warehouse Rent — This Month',
                'category_id'      => $cat['Venue & Facilities'] ?? null,
                'amount'           => 850.00,
                'transaction_date' => Carbon::now()->startOfMonth()->toDateString(),
                'source_or_payee'  => 'Park Street Properties LLC',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'INV-PSP-2026-07',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Volunteer T-Shirts (24 units)',
                'category_id'      => $cat['Staff & Volunteer'] ?? null,
                'amount'           => 312.00,
                'transaction_date' => Carbon::now()->subDays(12)->toDateString(),
                'source_or_payee'  => 'Custom Ink',
                'payment_method'   => 'Online',
                'status'           => 'completed',
            ],
            [
                'transaction_type' => 'expense',
                'title'            => 'Pending Invoice — Refrigerated Truck Hire',
                'category_id'      => $cat['Transportation'] ?? null,
                'amount'           => 480.00,
                'transaction_date' => Carbon::now()->subDays(4)->toDateString(),
                'source_or_payee'  => 'Cool Carry Logistics',
                'payment_method'   => 'Bank Transfer',
                'reference_number' => 'CCL-INV-2026-044',
                'status'           => 'pending',
                'notes'            => 'Cold chain delivery for perishable food run — invoice pending payment.',
            ],
        ];

        // ── Attach some transactions to events ─────────────────────────────────
        $eventExpenseCategories = [
            $cat['Food & Supplies']      ?? null,
            $cat['Venue & Facilities']   ?? null,
            $cat['Transportation']       ?? null,
            $cat['Staff & Volunteer']    ?? null,
        ];
        $eventIncomeCategory = $cat['Fundraising Event'] ?? null;

        if ($events->count() > 0) {
            foreach ($events->take(4) as $i => $event) {
                // Expense: event supplies
                $transactions[] = [
                    'transaction_type' => 'expense',
                    'title'            => "Event Supplies — {$event->name}",
                    'category_id'      => $eventExpenseCategories[$i % count($eventExpenseCategories)],
                    'amount'           => round(150 + ($i * 75) + mt_rand(0, 50), 2),
                    'transaction_date' => $event->date->toDateString(),
                    'source_or_payee'  => 'Event Operations Budget',
                    'payment_method'   => 'Cash',
                    'event_id'         => $event->id,
                    'status'           => $event->status === 'upcoming' ? 'pending' : 'completed',
                ];
                // Income: event donations bucket
                if ($i < 3) {
                    $transactions[] = [
                        'transaction_type' => 'income',
                        'title'            => "On-Site Donations — {$event->name}",
                        'category_id'      => $eventIncomeCategory,
                        'amount'           => round(200 + ($i * 120) + mt_rand(0, 80), 2),
                        'transaction_date' => $event->date->toDateString(),
                        'source_or_payee'  => 'Event Attendees',
                        'payment_method'   => 'Cash',
                        'event_id'         => $event->id,
                        'status'           => 'completed',
                    ];
                }
            }
        }

        $adminId = $adminId ?? 1;

        foreach ($transactions as $txData) {
            if (empty($txData['category_id'])) {
                continue; // skip if category wasn't found
            }
            FinanceTransaction::create(array_merge($txData, ['created_by' => $adminId]));
        }

        $this->command->info('Finance data seeded: ' . FinanceTransaction::count() . ' transactions across ' . FinanceCategory::count() . ' categories.');
    }
}
