<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Placeholder stats — replaced by real queries in Phase 6
        $stats = [
            'food_bundles_served'  => 11120,
            'food_bundles_change'  => 48,       // percent change (positive = up)
            'households_served'    => 1468,
            'people_served'        => 7564,
            'volunteers'           => 234,
        ];

        return view('dashboard.index', compact('stats'));
    }
}
