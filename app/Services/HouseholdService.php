<?php

namespace App\Services;

use App\Models\Household;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class HouseholdService
{
    /**
     * Phase 6.5.c: find potential duplicate households for the given input
     * data. Combines four strategies:
     *
     *  1. Exact case-insensitive first+last name match
     *  2. Email match (case-insensitive)
     *  3. Phone match (exact)
     *  4. Fuzzy name match via PHP soundex() — narrowed to households whose
     *     last name starts with the same letter (DB-side prefix filter) so
     *     we don't load the entire households table for every check
     *
     * Pass $excludeId to skip a specific household (used during update so a
     * household isn't flagged as a duplicate of itself).
     */
    public function findPotentialDuplicates(array $data, ?int $excludeId = null): Collection
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name']  ?? '');
        $email     = trim($data['email']      ?? '');
        $phone     = trim($data['phone']      ?? '');

        if ($firstName === '' && $lastName === '' && $email === '' && $phone === '') {
            return new Collection();
        }

        // Strategy 1-3: any-of-name+email+phone exact match
        $exact = Household::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($firstName, $lastName, $email, $phone) {
                if ($firstName !== '' && $lastName !== '') {
                    $q->orWhere(function ($qq) use ($firstName, $lastName) {
                        $qq->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                           ->whereRaw('LOWER(last_name) = ?',  [strtolower($lastName)]);
                    });
                }
                if ($email !== '') {
                    $q->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                }
                if ($phone !== '') {
                    $q->orWhere('phone', $phone);
                }
            })
            ->limit(20)
            ->get();

        // Strategy 4: fuzzy name match via Soundex
        $fuzzy = new Collection();
        if ($firstName !== '' && $lastName !== '') {
            $firstSoundex = soundex($firstName);
            $lastSoundex  = soundex($lastName);
            $lastPrefix   = strtolower(substr($lastName, 0, 1));

            $fuzzy = Household::query()
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->whereNotIn('id', $exact->pluck('id'))
                ->whereRaw('LOWER(SUBSTR(last_name, 1, 1)) = ?', [$lastPrefix])
                ->limit(200)
                ->get()
                ->filter(fn ($h) =>
                    soundex((string) $h->first_name) === $firstSoundex
                    && soundex((string) $h->last_name) === $lastSoundex
                )
                ->take(20)
                ->values();
        }

        return $exact->concat($fuzzy)->unique('id')->values();
    }

    /**
     * Generate a unique household number whose length is driven by the
     * 'households.household_number_length' setting (default 6).
     */
    public function generateHouseholdNumber(): string
    {
        $length = max(4, (int) SettingService::get('households.household_number_length', 6));
        $min    = (int) ('1' . str_repeat('0', $length - 1));
        $max    = (int) str_repeat('9', $length);

        do {
            $number = (string) random_int($min, $max);
        } while (Household::where('household_number', $number)->exists());

        return $number;
    }

    /**
     * Generate a unique QR token (UUID) used as the payload in the QR code.
     */
    public function generateQrToken(): string
    {
        do {
            $token = Str::uuid()->toString();
        } while (Household::where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Compute household_size from the three demographic counts.
     * Returns $data with household_size injected.
     */
    private function applyDemographics(array $data): array
    {
        $children = max(0, (int) ($data['children_count'] ?? 0));
        $adults   = max(0, (int) ($data['adults_count']   ?? 0));
        $seniors  = max(0, (int) ($data['seniors_count']  ?? 0));

        $data['children_count'] = $children;
        $data['adults_count']   = $adults;
        $data['seniors_count']  = $seniors;
        $data['household_size'] = $children + $adults + $seniors;

        // household_size must be at least 1
        if ($data['household_size'] < 1) {
            $data['household_size'] = 1;
        }

        return $data;
    }

    /**
     * Fields that are safe to mass-assign on a Household from form data.
     */
    private function householdFields(): array
    {
        return [
            'first_name', 'last_name', 'email', 'phone',
            'city', 'state', 'zip',
            'vehicle_make', 'vehicle_color',
            'children_count', 'adults_count', 'seniors_count', 'household_size',
            'notes',
        ];
    }

    /**
     * Create a primary household record plus any inline represented households.
     *
     * $data may contain:
     *   - Standard household fields
     *   - represented_households: array of sub-household data (each with first_name,
     *     last_name, children_count, adults_count, seniors_count, optional email/phone/notes)
     */
    public function create(array $data): Household
    {
        $representedData = $data['represented_households'] ?? [];
        unset($data['represented_households']);

        $data = $this->applyDemographics($data);

        $household = Household::create([
            ...array_intersect_key($data, array_flip([...$this->householdFields(), 'notes'])),
            'household_number' => $this->generateHouseholdNumber(),
            'qr_token'         => $this->generateQrToken(),
        ]);

        // Create each represented household and link it
        foreach ($representedData as $repData) {
            if (empty($repData['first_name'])) {
                continue;
            }
            $repData = $this->applyDemographics($repData);
            Household::create([
                ...array_intersect_key($repData, array_flip($this->householdFields())),
                'household_number'            => $this->generateHouseholdNumber(),
                'qr_token'                    => $this->generateQrToken(),
                'representative_household_id' => $household->id,
            ]);
        }

        return $household;
    }

    /**
     * Create a new household record immediately linked to a representative household.
     * Used during quick check-in when staff adds a new family on-the-fly.
     */
    public function createRepresented(Household $representative, array $data): Household
    {
        $data = $this->applyDemographics($data);

        return Household::create([
            ...array_intersect_key($data, array_flip($this->householdFields())),
            'household_number'            => $this->generateHouseholdNumber(),
            'qr_token'                    => $this->generateQrToken(),
            'representative_household_id' => $representative->id,
        ]);
    }

    /**
     * Update a household and synchronise its represented households.
     *
     * $data['represented_households'][*] entries:
     *   - With 'id': update existing represented household (must belong to this household)
     *   - With '_detach' => 1: detach (clear representative_household_id, keep record)
     *   - Without 'id': create new represented household linked to this one
     */
    public function update(Household $household, array $data): Household
    {
        $representedData = $data['represented_households'] ?? [];
        unset($data['represented_households']);

        $data = $this->applyDemographics($data);

        $household->update(
            array_intersect_key($data, array_flip($this->householdFields()))
        );

        foreach ($representedData as $repData) {
            $isDetach = !empty($repData['_detach']);

            if (isset($repData['id'])) {
                $rep = Household::where('id', $repData['id'])
                    ->where('representative_household_id', $household->id)
                    ->first();

                if (! $rep) {
                    continue;
                }

                if ($isDetach) {
                    $rep->update(['representative_household_id' => null]);
                } else {
                    $repData = $this->applyDemographics($repData);
                    $rep->update(
                        array_intersect_key($repData, array_flip($this->householdFields()))
                    );
                }
            } elseif (! $isDetach && ! empty($repData['first_name'])) {
                // New represented household
                $repData = $this->applyDemographics($repData);
                Household::create([
                    ...array_intersect_key($repData, array_flip($this->householdFields())),
                    'household_number'            => $this->generateHouseholdNumber(),
                    'qr_token'                    => $this->generateQrToken(),
                    'representative_household_id' => $household->id,
                ]);
            }
        }

        return $household->fresh();
    }

    /**
     * Regenerate the QR token for a household.
     */
    public function regenerateQrToken(Household $household): Household
    {
        $household->update(['qr_token' => $this->generateQrToken()]);
        return $household->fresh();
    }

    /**
     * Attach an existing household to a representative household.
     * A household cannot be its own representative, already have one,
     * or create a cycle in the representative chain (Phase 6.3).
     */
    public function attach(Household $representative, Household $represented): void
    {
        if ($representative->id === $represented->id) {
            throw new \RuntimeException('A household cannot represent itself.');
        }

        if ($represented->representative_household_id !== null) {
            throw new \RuntimeException(
                "\"{$represented->full_name}\" is already linked to a representative household."
            );
        }

        // Phase 6.3: cycle prevention. Walk up $representative's chain — if it
        // ever reaches $represented, this attach would close a loop.
        // The $visited guard also protects against pre-existing cycles in the
        // data so the loop can't run forever.
        $current = $representative;
        $visited = [];
        while ($current && $current->representative_household_id) {
            if (in_array($current->id, $visited, true)) {
                throw new \RuntimeException(
                    'Cannot attach: pre-existing cycle detected in representative chain.'
                );
            }
            $visited[] = $current->id;

            if ((int) $current->representative_household_id === (int) $represented->id) {
                throw new \RuntimeException(
                    "Cannot attach: \"{$represented->full_name}\" already appears further up \"{$representative->full_name}\"'s representative chain. This would create a circular link."
                );
            }
            $current = Household::find($current->representative_household_id);
        }

        $represented->update(['representative_household_id' => $representative->id]);
    }

    /**
     * Detach a represented household from its representative.
     */
    public function detach(Household $represented): void
    {
        $represented->update(['representative_household_id' => null]);
    }
}
