<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicEventController extends Controller
{
    private const US_STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];

    // ─── Upcoming events list ─────────────────────────────────────────────────

    public function index(): View
    {
        $events = Event::upcoming()->orderBy('date')->get();
        return view('public.events.index', compact('events'));
    }

    // ─── Registration form ────────────────────────────────────────────────────

    public function register(Event $event): View
    {
        // Compare day-to-day, not datetime-to-datetime. The events table
        // stores `date` as a DATE column (midnight on the day), so
        // `isPast()` would treat an event happening TODAY as past once
        // the clock crossed midnight at the start of the day. The intent
        // here is "the event has already happened" → only reject when the
        // event date is strictly BEFORE today.
        abort_if($event->date->lt(today()), 404);

        return view('public.events.register', [
            'event'  => $event,
            'states' => self::US_STATES,
        ]);
    }

    // ─── Handle submission ────────────────────────────────────────────────────

    public function submit(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->date->lt(today()), 404);

        $data = $request->validate([
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['required', 'email', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:50'],
            'zipcode'        => ['nullable', 'string', 'max:20'],
            'children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'adults_count'   => ['required', 'integer', 'min:0', 'max:50'],
            'seniors_count'  => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $children  = (int) $data['children_count'];
        $adults    = (int) $data['adults_count'];
        $seniors   = (int) $data['seniors_count'];
        $totalSize = max(1, $children + $adults + $seniors);

        $emailLower     = strtolower($data['email']);
        $firstNameLower = strtolower($data['first_name']);
        $lastNameLower  = strtolower($data['last_name']);

        // Phase 6.5.a: prevent same-event duplicate pre-registration.
        // Match by full name OR email — either signal indicates the same person
        // submitted the form twice for this event.
        $existingPreReg = EventPreRegistration::where('event_id', $event->id)
            ->where(function ($q) use ($firstNameLower, $lastNameLower, $emailLower) {
                $q->where(function ($qq) use ($firstNameLower, $lastNameLower) {
                    $qq->whereRaw('LOWER(first_name) = ?', [$firstNameLower])
                       ->whereRaw('LOWER(last_name) = ?',  [$lastNameLower]);
                })->orWhereRaw('LOWER(email) = ?', [$emailLower]);
            })
            ->first();

        if ($existingPreReg) {
            return redirect()
                ->route('public.success', $event)
                ->with('already_registered', true)
                ->with('attendee_number', $existingPreReg->attendee_number);
        }

        // Auto-generate display ID
        $attendeeNumber = EventPreRegistration::generateAttendeeNumber();

        // Phase 6.5.a: look for an existing household by full name OR email.
        // The email check catches name typos / married-name changes that the
        // case-insensitive name match alone would miss.
        $existingHousehold = Household::where(function ($q) use ($firstNameLower, $lastNameLower, $emailLower) {
                $q->where(function ($qq) use ($firstNameLower, $lastNameLower) {
                    $qq->whereRaw('LOWER(first_name) = ?', [$firstNameLower])
                       ->whereRaw('LOWER(last_name) = ?',  [$lastNameLower]);
                })->orWhereRaw('LOWER(email) = ?', [$emailLower]);
            })
            ->first();

        if ($existingHousehold) {
            // Returning household — flag as potential match for admin to confirm
            $attendee = EventPreRegistration::create([
                'event_id'              => $event->id,
                'attendee_number'       => $attendeeNumber,
                'first_name'            => $data['first_name'],
                'last_name'             => $data['last_name'],
                'email'                 => $data['email'],
                'city'                  => $data['city'] ?? null,
                'state'                 => $data['state'] ?? null,
                'zipcode'               => $data['zipcode'] ?? null,
                'children_count'        => $children,
                'adults_count'          => $adults,
                'seniors_count'         => $seniors,
                'household_size'        => $totalSize,
                'potential_household_id'=> $existingHousehold->id,
                'match_status'          => 'potential_match',
            ]);
        } else {
            // New household — auto-create in the households table
            $household = Household::create([
                'household_number' => $attendeeNumber,
                'first_name'       => $data['first_name'],
                'last_name'        => $data['last_name'],
                'email'            => $data['email'],
                'city'             => $data['city'] ?? null,
                'state'            => $data['state'] ?? null,
                'zip'              => $data['zipcode'] ?? null,
                'children_count'   => $children,
                'adults_count'     => $adults,
                'seniors_count'    => $seniors,
                'household_size'   => $totalSize,
                'qr_token'         => Str::random(32),
            ]);

            $attendee = EventPreRegistration::create([
                'event_id'       => $event->id,
                'attendee_number'=> $attendeeNumber,
                'first_name'     => $data['first_name'],
                'last_name'      => $data['last_name'],
                'email'          => $data['email'],
                'city'           => $data['city'] ?? null,
                'state'          => $data['state'] ?? null,
                'zipcode'        => $data['zipcode'] ?? null,
                'children_count' => $children,
                'adults_count'   => $adults,
                'seniors_count'  => $seniors,
                'household_size' => $totalSize,
                'household_id'   => $household->id,
                // Phase 6.5.a: auto-created household IS linked from the start —
                // mark as matched so the admin UI doesn't surface a redundant
                // "Register as Household" action that would otherwise confuse staff.
                'match_status'   => 'matched',
            ]);
        }

        return redirect()->route('public.success', $event);
    }

    // ─── Success page ─────────────────────────────────────────────────────────

    public function success(Event $event): View
    {
        return view('public.events.success', compact('event'));
    }
}
