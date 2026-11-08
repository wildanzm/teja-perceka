<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\CategoryPriceHistory;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $revenueAccount = Account::where('code', '4-2000')->first();
        $accountId = $revenueAccount ? $revenueAccount->id : null;

        $categoriesByUnit = [
            'SB' => [
                [
                    'name' => 'Tiket dewasa',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 10000,
                ],
                [
                    'name' => 'Tiket Anak',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 5000,
                ],
                [
                    'name' => 'Parkir Mobil',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 5000,
                ],
                [
                    'name' => 'Parkir Motor',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 2000,
                ],
                [
                    'name' => 'Sewa Fasilitas',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 10000,
                ],
                [
                    'name' => 'Sewa Kios',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 150000,
                ],
                [
                    'name' => 'Sewa Ruko',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 500000,
                ],
                [
                    'name' => 'Warung BUMDes',
                    'type' => CategoryType::Custom,
                    'direction' => TransactionType::Income,
                    'price' => 0,
                ],
                [
                    'name' => 'Budidaya Ikan',
                    'type' => CategoryType::Custom,
                    'direction' => TransactionType::Income,
                    'price' => 0,
                ],
            ],
            'SC' => [
                [
                    'name' => 'Tiket Dewasa',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 10000,
                ],
                [
                    'name' => 'Tiket Anak',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 5000,
                ],
                [
                    'name' => 'Parkir Mobil',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 5000,
                ],
                [
                    'name' => 'Parkir Motor',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 3000,
                ],
                [
                    'name' => 'Sewa Ban',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 10000,
                ],
                [
                    'name' => 'Sewa Pelampung',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 10000,
                ],
                [
                    'name' => 'Sewa Bebek Gowes',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 10000,
                ],
                [
                    'name' => 'Sewa Kios 3x2',
                    'type' => CategoryType::Yearly,
                    'direction' => TransactionType::Income,
                    'price' => 2000000,
                ],
                [
                    'name' => 'Sewa Kios 1,5x1',
                    'type' => CategoryType::Yearly,
                    'direction' => TransactionType::Income,
                    'price' => 1000000,
                ],
            ],
            'BS' => [
                [
                    'name' => 'Tiket Masuk',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 15000,
                ],
            ],
            'BC' => [
                [
                    'name' => 'Tiket Masuk',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 20000,
                ],
                [
                    'name' => 'Sewa Kios Buper',
                    'type' => CategoryType::Yearly,
                    'direction' => TransactionType::Income,
                    'price' => 1000000,
                ],
            ],
            'TPS' => [
                [
                    'name' => 'Retribusi Sampah',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 15000,
                ],
                [
                    'name' => 'Retribusi Kegiatan',
                    'type' => CategoryType::PriceTimesQuantity,
                    'direction' => TransactionType::Income,
                    'price' => 100000,
                ],
                [
                    'name' => 'Retribusi Sampah Wisata',
                    'type' => CategoryType::Custom,
                    'direction' => TransactionType::Income,
                    'price' => 0,
                ],
            ],
        ];

        foreach ($categoriesByUnit as $unitCode => $categories) {
            $unit = BusinessUnit::where('code', $unitCode)->first();

            if (! $unit) {
                continue;
            }

            foreach ($categories as $catData) {
                $category = TransactionCategory::firstOrCreate(
                    [
                        'business_unit_id' => $unit->id,
                        'name' => $catData['name'],
                    ],
                    [
                        'account_id' => $accountId,
                        'type' => $catData['type'],
                        'direction' => $catData['direction'],
                    ]
                );

                // Create initial price history if not already exists
                CategoryPriceHistory::firstOrCreate(
                    [
                        'transaction_category_id' => $category->id,
                        'effective_from' => '2026-01-01',
                    ],
                    [
                        'price' => $catData['price'],
                    ]
                );
            }
        }
    }
}
