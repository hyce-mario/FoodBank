@php
    /** @var array $data */
    $maxBucket = max(1, max($data['distribution']));
@endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Reviews</h2>
    <p class="text-sm text-gray-500 mb-5">Public feedback about this event. Top 5 positive and top 5 negative shown.</p>

    @if ($data['total'] === 0)
        <div class="bg-gray-50 rounded-xl p-8 text-center">
            <p class="text-sm text-gray-500">No reviews submitted for this event.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            <div class="rounded-xl bg-yellow-50 p-4 sm:col-span-1">
                <p class="text-3xl font-black text-yellow-600 tabular-nums">{{ $data['avg_rating'] }}<span class="text-lg">/5</span></p>
                <p class="text-xs font-semibold text-yellow-700 mt-1">Average rating</p>
                <p class="text-[10px] text-yellow-600 mt-0.5">{{ $data['total'] }} review{{ $data['total'] === 1 ? '' : 's' }}</p>
            </div>
            <div class="sm:col-span-2 bg-gray-50 rounded-xl p-4">
                @foreach ([5, 4, 3, 2, 1] as $stars)
                    @php
                        $count = $data['distribution'][$stars];
                        $pct   = $maxBucket > 0 ? round($count / $maxBucket * 100) : 0;
                    @endphp
                    <div class="flex items-center gap-3 mb-1.5 last:mb-0 text-xs">
                        <span class="w-5 text-right font-medium text-gray-600">{{ $stars }}★</span>
                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full bg-yellow-400 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-8 text-right tabular-nums font-semibold">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@if ($data['total'] > 0)
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
    {{-- Good reviews --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-emerald-700">Top Positive (4–5★)</h3>
            <span class="text-xs text-gray-400">{{ $data['good_reviews']->count() }}</span>
        </div>
        @if ($data['good_reviews']->isEmpty())
            <p class="text-sm text-gray-400">No 4★ or 5★ reviews.</p>
        @else
            <div class="space-y-3">
                @foreach ($data['good_reviews'] as $r)
                    <div class="border-l-2 border-emerald-300 pl-4 py-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-gray-700">{{ $r->reviewer_name ?: 'Anonymous' }}</span>
                            <span class="text-yellow-500 text-sm">{{ str_repeat('★', (int) $r->rating) }}<span class="text-gray-200">{{ str_repeat('★', 5 - (int) $r->rating) }}</span></span>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $r->review_text }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">{{ $r->created_at?->format('M j, Y') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Bad reviews --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-rose-700">Top Concerns (1–2★)</h3>
            <span class="text-xs text-gray-400">{{ $data['bad_reviews']->count() }}</span>
        </div>
        @if ($data['bad_reviews']->isEmpty())
            <p class="text-sm text-gray-400">No 1★ or 2★ reviews — none to flag.</p>
        @else
            <div class="space-y-3">
                @foreach ($data['bad_reviews'] as $r)
                    <div class="border-l-2 border-rose-300 pl-4 py-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-gray-700">{{ $r->reviewer_name ?: 'Anonymous' }}</span>
                            <span class="text-yellow-500 text-sm">{{ str_repeat('★', (int) $r->rating) }}<span class="text-gray-200">{{ str_repeat('★', 5 - (int) $r->rating) }}</span></span>
                        </div>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $r->review_text }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">{{ $r->created_at?->format('M j, Y') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endif
