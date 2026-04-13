@props([
    'label'   => '',
    'value'   => '',
    'change'  => null,   // e.g. "+48%" — null hides it
    'up'      => true,   // true = green arrow up, false = red arrow down
    'icon'    => 'gift', // gift | home | people | volunteer
    'variant' => 'white', // white | orange | navy | light
])

@php
$variants = [
    'white'  => 'bg-white text-gray-900',
    'orange' => 'bg-brand-500 text-white',
    'navy'   => 'bg-navy-700 text-white',
    'light'  => 'bg-gray-50 border border-gray-200 text-gray-900',
];

$cardClass    = $variants[$variant] ?? $variants['white'];
$labelClass   = in_array($variant, ['orange','navy']) ? 'text-white/70' : 'text-gray-500';
$iconBgClass  = match($variant) {
    'orange' => 'bg-white/20',
    'navy'   => 'bg-white/20',
    default  => 'bg-brand-50',
};
$iconColor = in_array($variant, ['orange','navy']) ? 'text-white' : 'text-brand-500';
@endphp

<div class="stat-card {{ $cardClass }} shadow-sm">
    {{-- Top row: label + icon --}}
    <div class="flex items-start justify-between gap-2">
        <p class="text-sm font-medium {{ $labelClass }} leading-snug">{{ $label }}</p>
        <div class="flex-shrink-0 flex items-center gap-2">
            {{-- Refresh icon --}}
            <button class="opacity-50 hover:opacity-80 transition-opacity">
                <svg class="w-4 h-4 {{ in_array($variant, ['orange','navy']) ? 'text-white' : 'text-gray-400' }}"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
            </button>
            {{-- Icon --}}
            <div class="w-9 h-9 rounded-xl {{ $iconBgClass }} flex items-center justify-center flex-shrink-0">
                @if ($icon === 'gift')
                    <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                    </svg>
                @elseif ($icon === 'home')
                    <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                @elseif ($icon === 'people')
                    <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                @elseif ($icon === 'volunteer')
                    <svg class="w-5 h-5 {{ $iconColor }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                @endif
            </div>
        </div>
    </div>

    {{-- Value --}}
    <div>
        <p class="text-3xl font-bold tracking-tight">{{ $value }}</p>
    </div>

    {{-- Change badge --}}
    @if ($change !== null)
        <div class="flex items-center gap-1.5">
            @if ($up)
                <span class="flex items-center gap-0.5 text-xs font-semibold
                             {{ in_array($variant, ['orange','navy']) ? 'text-green-300' : 'text-green-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
                    </svg>
                    {{ $change }}
                </span>
            @else
                <span class="flex items-center gap-0.5 text-xs font-semibold
                             {{ in_array($variant, ['orange','navy']) ? 'text-red-300' : 'text-red-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                    </svg>
                    {{ $change }}
                </span>
            @endif
        </div>
    @endif
</div>
