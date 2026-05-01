@extends('layouts.app')
@section('title', 'Reports — Volunteers')

@section('content')

<div class="bg-white rounded-2xl px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border border-gray-100">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Reports</h1>
        <p class="text-xs text-gray-400 mt-0.5">Volunteer participation &amp; service frequency</p>
    </div>
    <a href="{{ route('reports.download', array_merge(request()->only(['preset','date_from','date_to']), ['type' => 'volunteers'])) }}"
       class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-200 rounded-xl px-3 py-2 bg-white hover:bg-gray-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Export CSV
    </a>
</div>

@include('reports._nav')
@include('reports._filter', ['formAction' => route('reports.volunteers')])

{{-- ═══ KPI Cards ══════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="bg-navy-700 text-white rounded-2xl px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-white/60 mb-1">Total Volunteers</p>
        <p class="text-2xl font-bold">{{ number_format($totalVolunteers) }}</p>
        <p class="text-xs text-white/60 mt-0.5">in system</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Checked In</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($checkedInPeriod) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">actually served</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">First-Timers</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($firstTimersInPeriod) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">in period</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Events Served</p>
        <p class="text-2xl font-bold text-gray-900">{{ $eventParticipation->count() }}</p>
        <p class="text-xs text-gray-400 mt-0.5">with check-ins</p>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl px-5 py-4 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Hours Served</p>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalHoursInPeriod, 1) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">in period</p>
    </div>
</div>

{{-- ═══ Charts ══════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

    {{-- Checked-in volunteers per event --}}
    @if($eventParticipation->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Check-Ins per Event</h3>
        <p class="text-xs text-gray-400 mb-4">Volunteers who actually checked in per event</p>
        <div class="h-52">
            <canvas id="eventVolChart"></canvas>
        </div>
    </div>
    @endif

    {{-- Group sizes --}}
    @if($groups->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Volunteers per Group</h3>
        <p class="text-xs text-gray-400 mb-4">Total members in each volunteer group</p>
        <div class="h-52">
            <canvas id="groupChart"></canvas>
        </div>
    </div>
    @endif

</div>

{{-- ═══ Top Volunteers ══════════════════════════════════════════════ --}}
@if($topVolunteers->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Top Volunteers (All Time)</h3>
        <p class="text-xs text-gray-400 mt-0.5">Volunteers with the most events served across all time</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">#</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Volunteer</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">Role</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Total Events</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Total Hours</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Last Service</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topVolunteers as $i => $vol)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold
                            {{ $i === 0 ? 'bg-yellow-100 text-yellow-700' : ($i === 1 ? 'bg-gray-100 text-gray-600' : 'bg-gray-50 text-gray-400') }}">
                            {{ $i + 1 }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-800">{{ $vol['name'] }}</span>
                            @if ($vol['is_first_timer'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                    First Timer
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">
                        @if($vol['role'] !== '—')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                {{ $vol['role'] }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="font-bold text-navy-700 tabular-nums">{{ $vol['total_events'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-right hidden md:table-cell">
                        @if($vol['total_hours'] > 0)
                            <span class="font-semibold text-gray-700 tabular-nums">{{ number_format($vol['total_hours'], 1) }}h</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">
                        {{ $vol['last_service'] ? \Carbon\Carbon::parse($vol['last_service'])->format('M j, Y') : '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ Group Participation in Period ══════════════════════════════ --}}
@if($groupParticipation->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Group Participation in Period</h3>
        <p class="text-xs text-gray-400 mt-0.5">Groups with volunteers who checked in during the selected period</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Group</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Volunteers Served</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Events</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groupParticipation as $gp)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-3 font-semibold text-gray-800">{{ $gp->name }}</td>
                    <td class="px-4 py-3 text-right font-bold text-navy-700 tabular-nums">{{ $gp->vol_count }}</td>
                    <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ $gp->event_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ Event Check-In Summary ════════════════════════════════════ --}}
@if($eventParticipation->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-800">Event Check-In Summary</h3>
        <p class="text-xs text-gray-400 mt-0.5">Volunteers who checked in per event in the selected period</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Event</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Date</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">Checked In</th>
                </tr>
            </thead>
            <tbody>
                @foreach($eventParticipation as $ep)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-5 py-3 font-semibold text-gray-800">{{ $ep->name }}</td>
                    <td class="px-4 py-3 text-gray-500">
                        {{ \Illuminate\Support\Carbon::parse($ep->date)->format('M j, Y') }}
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums">{{ $ep->volunteer_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ═══ All Volunteers — Service Frequency ═════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm"
     x-data="{ search: '', showAll: false }">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <h3 class="text-sm font-bold text-gray-800 flex-1">All Volunteers — Service Frequency</h3>
        <input x-model="search"
               placeholder="Search volunteers…"
               class="text-sm border border-gray-200 rounded-xl px-3 py-2 w-48 focus:outline-none focus:ring-2 focus:ring-navy-600">
    </div>

    @if($allVolunteers->isEmpty())
    <div class="py-12 text-center text-sm text-gray-400">No volunteers found.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-5 py-3">Name</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden sm:table-cell">Role</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">In Period</th>
                    <th class="text-right text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3">All Time</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">First Service</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden md:table-cell">Last Service</th>
                    <th class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 px-4 py-3 hidden lg:table-cell">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allVolunteers as $i => $vol)
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                    x-show="search === '' || '{{ strtolower($vol['name'] . ' ' . $vol['role'] . ' ' . $vol['groups']) }}'.includes(search.toLowerCase())">
                    <td class="px-5 py-3 font-semibold text-gray-800">{{ $vol['name'] }}</td>
                    <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">
                        @if($vol['role'] !== '—')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">{{ $vol['role'] }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        @if($vol['events_in_period'] > 0)
                            <span class="font-bold text-navy-700">{{ $vol['events_in_period'] }}</span>
                        @else
                            <span class="text-gray-300">0</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        @if($vol['total_events'] > 0)
                            <span class="font-bold text-gray-900">{{ $vol['total_events'] }}</span>
                        @else
                            <span class="text-gray-300">0</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">
                        {{ $vol['first_service'] ? \Carbon\Carbon::parse($vol['first_service'])->format('M j, Y') : '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">
                        {{ $vol['last_service'] ? \Carbon\Carbon::parse($vol['last_service'])->format('M j, Y') : '—' }}
                    </td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        @if ($vol['is_first_timer'])
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                First Timer
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Returning</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 px-5 py-3">{{ $allVolunteers->count() }} volunteers total &bull; sorted by most events served</p>
    @endif
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const EVENT_PART = @json($eventParticipation);
    const GROUPS     = @json($groups);
    const navy       = '#1e3a5f';
    const orange     = '#f97316';
    const grid       = '#F3F4F6';
    const textC      = '#6B7280';

    const baseOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#1F2937', padding: 8, cornerRadius: 8, titleFont: { size: 12 }, bodyFont: { size: 12 } },
        },
        scales: {
            x: { grid: { color: grid }, ticks: { color: textC, font: { size: 10 }, maxRotation: 35 } },
            y: { grid: { color: grid }, ticks: { color: textC, font: { size: 11 }, precision: 0 }, beginAtZero: true },
        },
    };

    const evEl = document.getElementById('eventVolChart');
    if (evEl && EVENT_PART.length) {
        new Chart(evEl, {
            type: 'bar',
            data: {
                labels: [...EVENT_PART].reverse().map(e => e.name.length > 16 ? e.name.substring(0, 16) + '…' : e.name),
                datasets: [{ data: [...EVENT_PART].reverse().map(e => e.volunteer_count), backgroundColor: navy, borderRadius: 5, borderSkipped: false }],
            },
            options: baseOpts,
        });
    }

    const grEl = document.getElementById('groupChart');
    if (grEl && GROUPS.length) {
        new Chart(grEl, {
            type: 'bar',
            data: {
                labels: GROUPS.map(g => g.name.length > 16 ? g.name.substring(0, 16) + '…' : g.name),
                datasets: [{ data: GROUPS.map(g => g.volunteers_count), backgroundColor: orange, borderRadius: 5, borderSkipped: false }],
            },
            options: baseOpts,
        });
    }
});
</script>
@endpush
