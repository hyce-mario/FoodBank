<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $eventId = $request->get('event_id');
        $rating  = $request->get('rating');
        $search  = $request->get('search');
        $from    = $request->get('from');
        $to      = $request->get('to');

        $events = Event::query()
            ->whereDate('date', '<=', today())
            ->whereHas('reviews')
            ->with(['reviews' => function ($q) use ($rating, $search) {
                $q->latest();
                if ($rating) {
                    $q->where('rating', $rating);
                }
                if ($search) {
                    $q->where(function ($s) use ($search) {
                        $s->where('review_text', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('reviewer_name', 'like', "%{$search}%");
                    });
                }
            }])
            ->when($eventId, fn($q) => $q->where('id', $eventId))
            ->when($from, fn($q) => $q->where('date', '>=', $from))
            ->when($to, fn($q) => $q->where('date', '<=', $to))
            ->orderByDesc('date')
            ->get();

        // For the event filter dropdown: events that have any review
        $allEvents = Event::query()
            ->whereDate('date', '<=', today())
            ->whereHas('reviews')
            ->orderByDesc('date')
            ->get(['id', 'name', 'date']);

        return view('reviews.index', compact('events', 'allEvents'));
    }

    public function toggleVisibility(EventReview $review): RedirectResponse
    {
        $review->is_visible = ! $review->is_visible;
        $review->save();

        return back()->with('success', $review->is_visible
            ? 'Review is now visible.'
            : 'Review has been hidden.');
    }
}
