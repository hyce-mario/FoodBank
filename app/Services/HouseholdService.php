<?php

namespace App\Services;

use App\Models\Household;
use Illuminate\Support\Str;

class HouseholdService
{
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
     * A household cannot be its own representative or already have one.
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
