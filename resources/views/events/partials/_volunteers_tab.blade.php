{{--
    Event Volunteers Tab
    Variables available:
      $event — the Event model (has volunteerCheckIns and assignedVolunteers loaded)
--}}

@php
    $checkIns       = $event->volunteerCheckIns;
    $assigned       = $event->assignedVolunteers->keyBy('id');
    $checkedInIds   = $checkIns->pluck('volunteer_id')->flip();

    // Volunteers assigned but not yet checked in
    $notCheckedIn   = $assigned->filter(fn ($v) => ! $checkedInIds->has($v->id));
    // Open (still active) check-ins — eligible for bulk checkout.
    $activeCheckIns = $checkIns->whereNull('checked_out_at');
@endphp

{{-- ── KPI strip ──────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 text-center">
        <p class="text-2xl font-bold text-gray-900">{{ $checkIns->count() }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Checked In</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 text-center">
        <p class="text-2xl font-bold text-gray-900">{{ $assigned->count() }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Pre-Assigned</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 text-center">
        <p class="text-2xl font-bold text-gray-900">
            {{ $checkIns->where('is_first_timer', true)->count() }}
        </p>
        <p class="text-xs text-gray-400 mt-0.5">First-Timers</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 text-center">
        <p class="text-2xl font-bold text-gray-900">
            {{ $checkIns->whereIn('source', ['walk_in', 'new_volunteer'])->count() }}
        </p>
        <p class="text-xs text-gray-400 mt-0.5">Walk-Ins / New</p>
    </div>
</div>

{{-- ── Public check-in link (only for current events) ─────────────────────── --}}
@if ($event->isCurrent())
<div class="flex items-center justify-between bg-brand-50 border border-brand-200 rounded-xl px-4 py-3 mb-4">
    <div class="flex items-center gap-2.5">
        <svg class="w-5 h-5 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-brand-700">Public Volunteer Check-In</p>
            <p class="text-xs text-brand-600/80">
                Share <strong>{{ route('volunteer-checkin.index') }}</strong> — page activates automatically while this event is running
            </p>
        </div>
    </div>
    <a href="{{ route('volunteer-checkin.index') }}" target="_blank"
       class="flex-shrink-0 ml-3 text-xs font-semibold text-brand-700 border border-brand-300 bg-white
              hover:bg-brand-50 rounded-lg px-3 py-1.5 transition-colors">
        Open Page
    </a>
</div>
@endif

{{-- ── Checked-in volunteers ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-800">
            Checked In
            @if ($checkIns->isNotEmpty())
                <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $checkIns->count() }})</span>
            @endif
        </h3>
        @if ($activeCheckIns->isNotEmpty())
            <button type="button"
                    @click="openVolBulkCheckout({{ $activeCheckIns->count() }})"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                Check Out All
            </button>
        @endif
    </div>

    @if ($checkIns->isEmpty())
        <div class="px-5 py-10 text-center">
            <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
            </svg>
            <p class="text-sm text-gray-400">No volunteers checked in yet.</p>
        </div>
    @else
        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Volunteer</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">In</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Out</th>
                        <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Source</th>
                        <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">&nbsp;</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($checkIns->sortBy('checked_in_at') as $ci)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('volunteers.show', $ci->volunteer) }}"
                                   class="font-semibold text-gray-900 hover:text-brand-600 transition-colors">
                                    {{ $ci->volunteer->full_name }}
                                </a>
                                @if ($ci->is_first_timer)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                        First Timer
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $ci->role ?: '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $ci->checked_in_at?->format('g:i A') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            @if ($ci->checked_out_at)
                                <div>
                                    <span>{{ $ci->checked_out_at->format('g:i A') }}</span>
                                    @if ($ci->hours_served !== null)
                                        <span class="ml-1 text-xs text-gray-400">({{ rtrim(rtrim(number_format($ci->hours_served, 2), '0'), '.') }} hrs)</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400">Active</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $ci->sourceBadgeClasses() }}">
                                {{ $ci->sourceLabel() }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if (! $ci->checked_out_at)
                                <button type="button"
                                        @click="openVolCheckout({{ $ci->id }}, @js($ci->volunteer->full_name), @js($ci->checked_in_at?->format('g:i A')))"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                                    Check Out
                                </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @foreach ($checkIns->sortBy('checked_in_at') as $ci)
            <div class="px-4 py-3.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('volunteers.show', $ci->volunteer) }}"
                               class="font-semibold text-gray-900">
                                {{ $ci->volunteer->full_name }}
                            </a>
                            @if ($ci->is_first_timer)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 0 0 .95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 0 0-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 0 0-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 0 0-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 0 0 .951-.69l1.07-3.292Z"/></svg>
                                    First Timer
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 mt-1 flex-wrap">
                            @if ($ci->role)
                                <span class="text-xs text-gray-500">{{ $ci->role }}</span>
                            @endif
                            <span class="text-xs text-gray-400">In: {{ $ci->checked_in_at?->format('g:i A') ?? '—' }}</span>
                            @if ($ci->checked_out_at)
                                <span class="text-xs text-gray-400">Out: {{ $ci->checked_out_at->format('g:i A') }}</span>
                            @endif
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $ci->sourceBadgeClasses() }} flex-shrink-0">
                        {{ $ci->sourceLabel() }}
                    </span>
                </div>
                @if (! $ci->checked_out_at)
                    <button type="button"
                            @click="openVolCheckout({{ $ci->id }}, @js($ci->volunteer->full_name), @js($ci->checked_in_at?->format('g:i A')))"
                            class="mt-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                        Check Out
                    </button>
                @endif
            </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Not yet checked in (pre-assigned only) ─────────────────────────────── --}}
@if ($notCheckedIn->isNotEmpty())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-800">
            Pre-Assigned / Not Yet Checked In
            <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $notCheckedIn->count() }})</span>
        </h3>
        <button type="button"
                @click="openVolBulk({{ $notCheckedIn->count() }})"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-brand-500 hover:bg-brand-600 text-white transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75 10.5 18.75 19.5 5.25"/></svg>
            Check In All
        </button>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach ($notCheckedIn as $vol)
        <div class="flex items-center justify-between px-5 py-3 gap-3">
            <div class="min-w-0 flex-1">
                <a href="{{ route('volunteers.show', $vol) }}"
                   class="text-sm font-semibold text-gray-700 hover:text-brand-600 transition-colors">
                    {{ $vol->full_name }}
                </a>
                @if ($vol->role)
                    <span class="ml-2 text-xs text-gray-400">{{ $vol->role }}</span>
                @endif
            </div>
            <button type="button"
                    @click="openVolCheckIn({{ $vol->id }}, @js($vol->full_name), @js($vol->role))"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100 transition-colors flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Check In
            </button>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Volunteer admin modals ───────────────────────────────────────────────
     All three live inside the eventShow() Alpine scope (the parent
     show.blade.php wraps the whole page in x-data="eventShow()"), so the
     volCheckIn / volCheckout / volBulk state is reactive here. --}}

{{-- Modal 1 — single check-in with time picker --}}
<div x-show="volCheckIn.show" x-cloak style="display:none"
     @keydown.escape.window="closeVolCheckIn()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div @click.away="closeVolCheckIn()"
         role="dialog" aria-modal="true" aria-labelledby="volCheckInTitle"
         class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="volCheckInTitle" class="text-base font-bold text-gray-900">Check in volunteer</h3>
                <p class="text-sm text-gray-500 mt-0.5" x-text="volCheckIn.volunteerName"></p>
            </div>
        </div>

        <form method="POST" :action="@js(route('events.volunteer-checkins.store', $event))" class="space-y-3">
            @csrf
            <input type="hidden" name="volunteer_id" :value="volCheckIn.volunteerId">
            <input type="hidden" name="role" :value="volCheckIn.defaultRole">
            <input type="hidden" name="source" value="pre_assigned">

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Check-in time</label>
                <input type="datetime-local"
                       name="checked_in_at"
                       x-model="volCheckIn.time"
                       required
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                <p class="text-xs text-gray-400 mt-1">Defaults to now. Adjust if recording a past arrival.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="closeVolCheckIn()"
                        class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg">
                    Check In
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2 — checkout with time picker --}}
<div x-show="volCheckout.show" x-cloak style="display:none"
     @keydown.escape.window="closeVolCheckout()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div @click.away="closeVolCheckout()"
         role="dialog" aria-modal="true" aria-labelledby="volCheckoutTitle"
         class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="volCheckoutTitle" class="text-base font-bold text-gray-900">Check out volunteer</h3>
                <p class="text-sm text-gray-500 mt-0.5">
                    <span x-text="volCheckout.volunteerName"></span>
                    <span class="text-gray-400" x-show="volCheckout.checkedInAt">
                        · checked in at <span x-text="volCheckout.checkedInAt"></span>
                    </span>
                </p>
            </div>
        </div>

        <form method="POST"
              :action="`{{ url('events/' . $event->id . '/volunteer-checkins') }}/${volCheckout.checkInId}/checkout`"
              class="space-y-3">
            @csrf @method('PATCH')

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Check-out time</label>
                <input type="datetime-local"
                       name="checked_out_at"
                       x-model="volCheckout.time"
                       required
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500/20">
                <p class="text-xs text-gray-400 mt-1">Hours served are computed automatically from the in/out times.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="closeVolCheckout()"
                        class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Check Out
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 3 — bulk check-in confirmation --}}
<div x-show="volBulk.show" x-cloak style="display:none"
     @keydown.escape.window="closeVolBulk()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div @click.away="closeVolBulk()"
         role="dialog" aria-modal="true" aria-labelledby="volBulkTitle"
         class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="volBulkTitle" class="text-base font-bold text-gray-900">Check in all assigned volunteers?</h3>
                <p class="text-sm text-gray-500 mt-1">
                    This will check in <strong class="text-gray-700"><span x-text="volBulk.count"></span></strong>
                    volunteer<span x-show="volBulk.count !== 1">s</span> at the current time. Volunteers already
                    checked in are skipped.
                </p>
            </div>
        </div>

        <form method="POST" :action="@js(route('events.volunteer-checkins.bulk', $event))" class="flex items-center justify-end gap-2 pt-2">
            @csrf
            <button type="button" @click="closeVolBulk()"
                    class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
            <button type="submit"
                    class="px-4 py-2 text-sm font-semibold bg-brand-500 hover:bg-brand-600 text-white rounded-lg">
                Check In All
            </button>
        </form>
    </div>
</div>

{{-- Modal 4 — bulk checkout confirmation. Closes every active check-in
     for this event at the current time and computes hours_served per row. --}}
<div x-show="volBulkCheckout.show" x-cloak style="display:none"
     @keydown.escape.window="closeVolBulkCheckout()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div @click.away="closeVolBulkCheckout()"
         role="dialog" aria-modal="true" aria-labelledby="volBulkCheckoutTitle"
         class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="volBulkCheckoutTitle" class="text-base font-bold text-gray-900">Check out all active volunteers?</h3>
                <p class="text-sm text-gray-500 mt-1">
                    This will close <strong class="text-gray-700"><span x-text="volBulkCheckout.count"></span></strong>
                    open check-in<span x-show="volBulkCheckout.count !== 1">s</span> at the current time.
                    Hours served are computed automatically per volunteer from their individual in/out times.
                </p>
            </div>
        </div>

        <form method="POST" :action="@js(route('events.volunteer-checkins.bulk-checkout', $event))" class="flex items-center justify-end gap-2 pt-2">
            @csrf
            <button type="button" @click="closeVolBulkCheckout()"
                    class="px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
            <button type="submit"
                    class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg">
                Check Out All
            </button>
        </form>
    </div>
</div>
