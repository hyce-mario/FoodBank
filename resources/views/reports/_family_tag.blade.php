{{--
    Compact family-composition chip for table rows. Shows colored pills
    per category that has people: A=adults (navy), C=children (amber),
    S=seniors (gray). Total count beneath. Hides any 0-count category.

    Variables expected:
    - $hh: object/array with `household_size`, `children_count`,
           `adults_count`, `seniors_count` properties.
--}}
@php
    $size     = $hh->household_size  ?? 0;
    $children = $hh->children_count  ?? 0;
    $adults   = $hh->adults_count    ?? 0;
    $seniors  = $hh->seniors_count   ?? 0;
@endphp

<div class="flex items-center gap-1 flex-wrap">
    @if ($adults > 0)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums bg-navy-50 text-navy-700 border border-navy-100"
              title="{{ $adults }} {{ $adults === 1 ? 'adult' : 'adults' }}">
            {{ $adults }}A
        </span>
    @endif
    @if ($children > 0)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums bg-amber-100 text-amber-700 border border-amber-200"
              title="{{ $children }} {{ $children === 1 ? 'child' : 'children' }}">
            {{ $children }}C
        </span>
    @endif
    @if ($seniors > 0)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums bg-gray-100 text-gray-700 border border-gray-200"
              title="{{ $seniors }} {{ $seniors === 1 ? 'senior' : 'seniors' }}">
            {{ $seniors }}S
        </span>
    @endif
    @if ($adults === 0 && $children === 0 && $seniors === 0 && $size > 0)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums bg-gray-100 text-gray-500 border border-gray-200">
            {{ $size }}
        </span>
    @endif
</div>
@if ($size > 0)
    <p class="text-[11px] text-gray-400 mt-1 tabular-nums">{{ $size }} {{ $size === 1 ? 'person' : 'people' }}</p>
@endif
