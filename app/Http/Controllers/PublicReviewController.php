<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Event;
use App\Models\EventReview;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicReviewController extends Controller
{
    public function create(): View
    {
        abort_unless(SettingService::get('reviews.enable_reviews', true), 404);

        $today = now()->toDateString();

        $todayEvents = Event::whereDate('date', $today)
            ->orderBy('name')
            ->get(['id', 'name', 'date']);

        // Restrict to past/today events when setting is on
        $pastQuery = Event::where('date', '<', $today)->orderByDesc('date')->limit(100);

        if (SettingService::get('reviews.restrict_to_recent_events', true)) {
            $pastQuery->where('date', '>=', now()->subMonths(6)->toDateString());
        }

        $pastEvents = $pastQuery->get(['id', 'name', 'date']);

        return view('public.reviews.create', compact('todayEvents', 'pastEvents'));
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        abort_unless(SettingService::get('reviews.enable_reviews', true), 404);

        // is_visible is not in $fillable — set it explicitly after construction
        // so a public submitter cannot influence visibility via POST body.
        $requireModeration = SettingService::get('reviews.require_moderation', false);
        $defaultVisibility = SettingService::get('reviews.default_visibility', 'visible');

        $review = new EventReview($request->validated());
        $review->is_visible = ! $requireModeration && $defaultVisibility === 'visible';
        $review->save();

        $thankyou = SettingService::get('reviews.thankyou_message', 'Thank you for your feedback!');

        return redirect()
            ->route('public.reviews.create')
            ->with('reviewed', true)
            ->with('thankyou_message', $thankyou);
    }
}
