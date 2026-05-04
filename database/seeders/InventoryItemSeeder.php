<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    /**
     * Demo inventory items for a food bank.
     *
     * Stock levels are deliberately varied:
     *   - Most items are "in stock" (healthy quantity)
     *   - A few are "low stock" (at or below reorder level)
     *   - A couple are "out of stock" (quantity = 0)
     *
     * Stock is added via InventoryService::addStock() so movement records
     * are created and the audit trail is populated.
     */
    public function run(InventoryService $inventory): void
    {
        // Map category name → id
        $cats = InventoryCategory::pluck('id', 'name');

        $items = [
            // ── Grains ──────────────────────────────────────────────────────
            [
                'name'            => 'White Rice (2 kg bag)',
                'sku'             => 'GRN-RICE-2KG',
                'category'        => 'Grains',
                'unit_type'       => 'bags',
                'reorder_level'   => 30,
                'quantity'        => 180,
                'description'     =>'Long-grain white rice. Donated by local supermarkets.',
            ],
            [
                'name'            => 'Pasta (Penne, 500 g)',
                'sku'             => 'GRN-PASTA-PEN',
                'category'        => 'Grains',
                'unit_type'       => 'packets',
                'reorder_level'   => 40,
                'quantity'        => 210,
                'description'     =>'Penne pasta 500 g packets.',
            ],
            [
                'name'            => 'Rolled Oats (1 kg)',
                'sku'             => 'GRN-OATS-1KG',
                'category'        => 'Grains',
                'unit_type'       => 'bags',
                'reorder_level'   => 20,
                'quantity'        => 22,          // LOW — just above reorder
                'description'     =>'Quick-cook rolled oats.',
            ],
            [
                'name'            => 'Plain Flour (1.5 kg)',
                'sku'             => 'GRN-FLOUR-1.5',
                'category'        => 'Grains',
                'unit_type'       => 'bags',
                'reorder_level'   => 25,
                'quantity'        => 0,            // OUT OF STOCK
                'description'     =>'All-purpose plain flour.',
            ],
            [
                'name'            => 'Sliced Bread Loaf',
                'sku'             => 'GRN-BREAD-SLC',
                'category'        => 'Grains',
                'unit_type'       => 'loaves',
                'reorder_level'   => 15,
                'quantity'        => 48,
                'description'     =>'Standard 700 g sliced white bread.',
            ],

            // ── Canned Goods ─────────────────────────────────────────────────
            [
                'name'            => 'Canned Diced Tomatoes (400 g)',
                'sku'             => 'CAN-TOM-400G',
                'category'        => 'Canned Goods',
                'unit_type'       => 'cans',
                'reorder_level'   => 50,
                'quantity'        => 340,
                'description'     =>'Diced tomatoes in juice. Versatile cooking staple.',
            ],
            [
                'name'            => 'Canned Chickpeas (400 g)',
                'sku'             => 'CAN-CHICK-400',
                'category'        => 'Canned Goods',
                'unit_type'       => 'cans',
                'reorder_level'   => 40,
                'quantity'        => 195,
                'description'     =>'Ready-to-eat chickpeas in brine.',
            ],
            [
                'name'            => 'Canned Tuna in Spring Water',
                'sku'             => 'CAN-TUNA-185G',
                'category'        => 'Canned Goods',
                'unit_type'       => 'cans',
                'reorder_level'   => 30,
                'quantity'        => 88,
                'description'     =>'185 g cans. Good source of protein.',
            ],
            [
                'name'            => 'Chicken Noodle Soup (400 g)',
                'sku'             => 'CAN-SOUP-CHK',
                'category'        => 'Canned Goods',
                'unit_type'       => 'cans',
                'reorder_level'   => 25,
                'quantity'        => 18,           // LOW
                'description'     =>'Ready-to-eat soup. No preparation needed.',
            ],
            [
                'name'            => 'Canned Baked Beans (420 g)',
                'sku'             => 'CAN-BEAN-420G',
                'category'        => 'Canned Goods',
                'unit_type'       => 'cans',
                'reorder_level'   => 40,
                'quantity'        => 260,
                'description'     =>'Baked beans in tomato sauce.',
            ],
            [
                'name'            => 'Canned Corn Kernels (420 g)',
                'sku'             => 'CAN-CORN-420G',
                'category'        => 'Canned Goods',
                'unit_type'       => 'cans',
                'reorder_level'   => 30,
                'quantity'        => 0,            // OUT OF STOCK
                'description'     =>'Drained weight approx 280 g.',
            ],

            // ── Beverages ────────────────────────────────────────────────────
            [
                'name'            => 'Bottled Water (600 ml)',
                'sku'             => 'BEV-WATER-600',
                'category'        => 'Beverages',
                'unit_type'       => 'bottles',
                'reorder_level'   => 100,
                'quantity'        => 480,
                'description'     =>'Individual 600 ml water bottles.',
            ],
            [
                'name'            => 'Apple Juice Carton (1 L)',
                'sku'             => 'BEV-AJUICE-1L',
                'category'        => 'Beverages',
                'unit_type'       => 'cartons',
                'reorder_level'   => 20,
                'quantity'        => 65,
                'description'     =>'100% apple juice, no added sugar.',
            ],
            [
                'name'            => 'Powdered Milk (1 kg)',
                'sku'             => 'BEV-PWDMILK-1',
                'category'        => 'Beverages',
                'unit_type'       => 'tins',
                'reorder_level'   => 15,
                'quantity'        => 12,           // LOW
                'description'     =>'Full-cream powdered milk. Makes approx 8 L.',
            ],
            [
                'name'            => 'Instant Coffee (100 g)',
                'sku'             => 'BEV-COFFEE-100',
                'category'        => 'Beverages',
                'unit_type'       => 'jars',
                'reorder_level'   => 10,
                'quantity'        => 35,
                'description'     =>'Standard instant coffee.',
            ],

            // ── Produce ──────────────────────────────────────────────────────
            [
                'name'            => 'Apples (per kg)',
                'sku'             => 'PRD-APPLE-KG',
                'category'        => 'Produce',
                'unit_type'       => 'kg',
                'reorder_level'   => 10,
                'quantity'        => 42,
                'description'     =>'Mixed variety fresh apples. Sourced from local farms.',
            ],
            [
                'name'            => 'Carrots (per kg)',
                'sku'             => 'PRD-CARR-KG',
                'category'        => 'Produce',
                'unit_type'       => 'kg',
                'reorder_level'   => 10,
                'quantity'        => 55,
                'description'     =>'Fresh whole carrots.',
            ],
            [
                'name'            => 'Potatoes (per kg)',
                'sku'             => 'PRD-POT-KG',
                'category'        => 'Produce',
                'unit_type'       => 'kg',
                'reorder_level'   => 15,
                'quantity'        => 8,            // LOW
                'description'     =>'Washed potatoes.',
            ],
            [
                'name'            => 'Bananas (per bunch)',
                'sku'             => 'PRD-BAN-BCH',
                'category'        => 'Produce',
                'unit_type'       => 'bunches',
                'reorder_level'   => 5,
                'quantity'        => 20,
                'description'     =>'Ripe bananas — prioritise early distribution.',
            ],

            // ── Hygiene ──────────────────────────────────────────────────────
            [
                'name'            => 'Soap Bar (pack of 4)',
                'sku'             => 'HYG-SOAP-4PK',
                'category'        => 'Hygiene',
                'unit_type'       => 'packs',
                'reorder_level'   => 20,
                'quantity'        => 95,
                'description'     =>'Fragrance-free antibacterial soap.',
            ],
            [
                'name'            => 'Shampoo (400 ml)',
                'sku'             => 'HYG-SHAM-400',
                'category'        => 'Hygiene',
                'unit_type'       => 'bottles',
                'reorder_level'   => 15,
                'quantity'        => 48,
                'description'     =>'Generic shampoo for all hair types.',
            ],
            [
                'name'            => 'Toothpaste (110 g)',
                'sku'             => 'HYG-TPASTE-110',
                'category'        => 'Hygiene',
                'unit_type'       => 'tubes',
                'reorder_level'   => 20,
                'quantity'        => 17,           // LOW
                'description'     =>'Fluoride toothpaste.',
            ],
            [
                'name'            => 'Toilet Paper (4-roll pack)',
                'sku'             => 'HYG-TP-4PK',
                'category'        => 'Hygiene',
                'unit_type'       => 'packs',
                'reorder_level'   => 25,
                'quantity'        => 120,
                'description'     =>'2-ply toilet paper.',
            ],
            [
                'name'            => 'Disposable Nappies (size 2, 24 pk)',
                'sku'             => 'HYG-NAP-SZ2',
                'category'        => 'Hygiene',
                'unit_type'       => 'packs',
                'reorder_level'   => 8,
                'quantity'        => 0,            // OUT OF STOCK
                'description'     =>'Size 2 (3–6 kg). High demand — order regularly.',
            ],
            [
                'name'            => 'Sanitary Pads (regular, 16 pk)',
                'sku'             => 'HYG-PAD-16PK',
                'category'        => 'Hygiene',
                'unit_type'       => 'packs',
                'reorder_level'   => 10,
                'quantity'        => 32,
                'description'     =>'Regular flow pads.',
            ],
        ];

        foreach ($items as $data) {
            $categoryId = $cats[$data['category']] ?? null;
            $qty        = $data['quantity'];

            // Create the item at 0 stock first
            $item = InventoryItem::firstOrCreate(
                ['sku' => $data['sku']],
                [
                    'name'             => $data['name'],
                    'category_id'      => $categoryId,
                    'unit_type'        => $data['unit_type'],
                    'reorder_level'    => $data['reorder_level'],
                    'quantity_on_hand' => 0,
                    'description'      => $data['description'] ?? null,
                    'is_active'        => true,
                ]
            );

            // Add opening stock via InventoryService so movements are recorded
            if ($item->wasRecentlyCreated && $qty > 0) {
                $inventory->addStock(
                    item:     $item,
                    quantity: $qty,
                    notes:    'Opening stock — demo seed',
                );
            }
        }
    }
}
