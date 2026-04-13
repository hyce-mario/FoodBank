@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ═══════════════════════════════════════════════════════
     GREETING HEADER
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex items-center justify-between shadow-sm border border-gray-100">
    <div class="flex items-center gap-2.5">
        <span class="text-2xl">👋</span>
        <div>
            <h1 class="text-lg font-bold text-gray-900">Hi {{ auth()->user()->name }}</h1>
            <p class="text-xs text-gray-400 mt-0.5">Welcome back to your dashboard</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
            <span class="font-medium text-gray-700">{{ now()->format('d M Y') }}</span>
        </div>
        <button class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
            </svg>
        </button>
        <button class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     STAT CARDS
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">

    {{-- 1. Total food bundles served --}}
    <x-stat-card
        label="Total food bundle Served"
        value="{{ number_format($stats['food_bundles_served']) }}"
        change="{{ $stats['food_bundles_change'] }}%"
        :up="true"
        icon="gift"
        variant="white"
    />

    {{-- 2. Households served --}}
    <x-stat-card
        label="Household Served"
        value="{{ number_format($stats['households_served']) }}"
        icon="home"
        variant="orange"
    />

    {{-- 3. People served --}}
    <x-stat-card
        label="People Served"
        value="{{ number_format($stats['people_served']) }}+"
        icon="people"
        variant="navy"
    />

    {{-- 4. Volunteers --}}
    <x-stat-card
        label="Volunteers"
        value="{{ number_format($stats['volunteers']) }}"
        icon="volunteer"
        variant="light"
    />
</div>

{{-- ═══════════════════════════════════════════════════════
     CHARTS ROW
═══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-5">

    {{-- Monthly Distribution (area chart) ──────────────── --}}
    <div class="xl:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Monthly Distribution</h2>
            <div class="flex items-center gap-1.5 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                <span class="font-medium">{{ now()->year }}</span>
            </div>
        </div>
        <div class="h-56">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    {{-- Household size distribution (donut chart) ───────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Household size distribution</h2>
                <p class="text-xs text-gray-400 mt-0.5">Served this month</p>
            </div>
            <button class="flex items-center gap-1 text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-100">
                Nov
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
        </div>

        <div class="flex justify-center mb-4">
            <div class="relative h-44 w-44">
                <canvas id="householdChart"></canvas>
                {{-- Center label --}}
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-bold text-gray-900">{{ number_format($stats['households_served']) }}</span>
                    <span class="text-xs text-gray-400">Total</span>
                </div>
            </div>
        </div>

        {{-- Legend --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                    <span class="text-gray-600 text-xs">1-2 People</span>
                </div>
                <span class="font-semibold text-gray-900 text-xs">16 %</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-navy-700"></div>
                    <span class="text-gray-600 text-xs">3-4 People</span>
                </div>
                <span class="font-semibold text-gray-900 text-xs">60 %</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-brand-500"></div>
                    <span class="text-gray-600 text-xs">5-6 People</span>
                </div>
                <span class="font-semibold text-gray-900 text-xs">24 %</span>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     QUICK ACTIONS
═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

        <button class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Create Event</span>
        </button>

        <button class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Add Household</span>
        </button>

        <button class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">QR Codes</span>
        </button>

        <button class="quick-action group">
            <div class="w-12 h-12 rounded-full bg-navy-700 group-hover:bg-navy-800 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </div>
            <span class="text-sm font-medium text-gray-700 text-center">Print Report</span>
        </button>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Monthly Distribution (Area Chart) ───────────────────
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
                datasets: [{
                    label: 'Food Bundles Distributed',
                    data: [148, 220, 275, 340, 480, 415, 378, 255, 185],
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.08)',
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#f97316',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1b2b4b',
                        titleColor: '#fff',
                        bodyColor: '#d1d5db',
                        borderColor: '#1b2b4b',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y.toLocaleString()} bundles`
                        }
                    }
                },
                scales: {
                    y: {
                        min: 100,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11 },
                            maxTicksLimit: 6,
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11 },
                        },
                        border: { display: false }
                    }
                }
            }
        });
    }

    // ── Household Size Distribution (Donut Chart) ────────────
    const householdCtx = document.getElementById('householdChart');
    if (householdCtx) {
        new Chart(householdCtx, {
            type: 'doughnut',
            data: {
                labels: ['1-2 People', '3-4 People', '5-6 People'],
                datasets: [{
                    data: [16, 60, 24],
                    backgroundColor: ['#e5e7eb', '#1b2b4b', '#f97316'],
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1b2b4b',
                        titleColor: '#fff',
                        bodyColor: '#d1d5db',
                        padding: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed}%`
                        }
                    }
                }
            }
        });
    }

});
</script>
@endpush
