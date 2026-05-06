@php /** @var array $data */ @endphp
<div class="bg-white border border-gray-200 rounded-2xl p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">Evaluation</h2>
    <p class="text-sm text-gray-500 mb-5">Heuristic insights derived from the data above. Useful as a starting point — staff judgement should override.</p>

    @if (empty($data))
        <div class="bg-gray-50 rounded-xl p-8 text-center">
            <p class="text-sm text-gray-500">Not enough data to evaluate this event.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($data as $insight)
                @php
                    $kindStyles = match($insight['kind']) {
                        'positive'   => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'iconBg' => 'bg-emerald-100', 'iconColor' => 'text-emerald-600'],
                        'concerning' => ['bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'text' => 'text-rose-800',    'iconBg' => 'bg-rose-100',    'iconColor' => 'text-rose-600'],
                        default      => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'text' => 'text-blue-800',    'iconBg' => 'bg-blue-100',    'iconColor' => 'text-blue-600'],
                    };
                    $iconPath = match($insight['kind']) {
                        'positive'   => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                        'concerning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                        default      => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
                    };
                @endphp
                <div class="flex items-start gap-3 {{ $kindStyles['bg'] }} border {{ $kindStyles['border'] }} rounded-xl p-4">
                    <div class="w-9 h-9 rounded-full {{ $kindStyles['iconBg'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 {{ $kindStyles['iconColor'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide {{ $kindStyles['text'] }} opacity-75">{{ $insight['category'] }}</p>
                        <p class="text-sm {{ $kindStyles['text'] }} mt-0.5 leading-relaxed">{{ $insight['message'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
