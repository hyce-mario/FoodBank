<?php

namespace App\Http\Resources;

use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phase 6.8 — Visit JSON shape for the event-day endpoints (loader, scanner,
 * intake, exit). Centralises per-role field exposure: loader and exit roles
 * never receive household full names, regardless of which controller calls
 * the resource. Replaces the in-controller unset() / array_diff_key dance.
 *
 * Use with:
 *   VisitResource::collection($visits)->forRole('loader')->toArray($request)
 * or set the role on a single instance:
 *   (new VisitResource($visit))->forRole('intake')
 *
 * Default role 'intake' shows the most fields; the toArray() body is the
 * single source of truth for what each role sees.
 */
class VisitResource extends JsonResource
{
    private string $role            = 'intake';
    private ?\App\Models\AllocationRuleset $ruleset = null;
    private array  $bagComposition  = [];

    /** @var array<int,string> */
    private const ROLES = ['intake', 'scanner', 'loader', 'exit'];

    /** @var array<int,string> Roles that must NOT receive household full names. */
    private const NAME_HIDDEN_ROLES = ['loader', 'exit'];

    public function forRole(string $role): self
    {
        if (! in_array($role, self::ROLES, true)) {
            $role = 'intake';
        }
        $this->role = $role;
        return $this;
    }

    public function withRuleset(?\App\Models\AllocationRuleset $ruleset): self
    {
        $this->ruleset = $ruleset;
        return $this;
    }

    public function withBagComposition(array $composition): self
    {
        $this->bagComposition = $composition;
        return $this;
    }

    /**
     * Build the row for the event-day card grid. Mirrors the shape that
     * `EventDayController::data` was hand-building before this refactor —
     * no front-end changes required, but the field-stripping is now
     * declarative and unit-testable.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Visit $visit */
        $visit = $this->resource;

        $households = $visit->households;
        $primary    = $households->firstWhere('representative_household_id', null) ?? $households->first();
        $represented = $households
            ->filter(fn ($h) => $primary && $h->id !== $primary->id)
            ->values();

        $ruleset    = $this->ruleset;
        $totalBags  = $ruleset
            ? $households->sum(fn ($h) => $ruleset->getBagsFor($h->household_size))
            : 0;
        $totalPeople   = $households->sum('household_size');
        $hidesNames    = in_array($this->role, self::NAME_HIDDEN_ROLES, true);

        $primaryPayload = [
            'household_number' => $primary?->household_number,
            'vehicle_label'    => $primary?->vehicle_label,
            'household_size'   => $primary?->household_size,
            // Demographic breakdown — fuels the family-tag tooltip on intake
            // and scanner cards. Counts are not PII so they ship to all roles.
            'children_count'   => $primary?->children_count,
            'adults_count'     => $primary?->adults_count,
            'seniors_count'    => $primary?->seniors_count,
        ];
        if (! $hidesNames) {
            $primaryPayload['full_name'] = $primary?->full_name;
        }

        $representedPayload = $represented
            ->map(fn ($r) => array_filter([
                'full_name'      => $hidesNames ? null : $r->full_name,
                'household_size' => $r->household_size,
                'bags_needed'    => $ruleset ? $ruleset->getBagsFor($r->household_size) : null,
            ], fn ($v) => $v !== null))
            ->all();

        return [
            'id'                       => $visit->id,
            'lane'                     => $visit->lane,
            'queue_position'           => $visit->queue_position,
            'visit_status'             => $visit->visit_status,
            'updated_at'               => $visit->updated_at?->toIso8601String(),
            'start_time'               => $visit->start_time?->format('g:i A'),
            'waited_min'               => $visit->start_time
                ? (int) now()->diffInMinutes($visit->start_time)
                : 0,
            'bags_needed'              => $totalBags,
            'bag_composition'          => $this->bagComposition,
            'total_people'             => $totalPeople,
            'is_representative_pickup' => $represented->isNotEmpty(),
            'household'                => $primaryPayload,
            'represented_households'   => $representedPayload,
        ];
    }
}
