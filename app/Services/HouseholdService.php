<?php

namespace App\Services;

use App\Models\Household;
use Illuminate\Support\Str;

class HouseholdService
{
    /**
     * Generate a unique 5-digit household number (10000–99999).
     */
    public function generateHouseholdNumber(): string
    {
        do {
            $number = (string) random_int(10000, 99999);
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
     * Create a household from validated request data.
     */
    public function create(array $data): Household
    {
        return Household::create([
            ...$data,
            'household_number' => $this->generateHouseholdNumber(),
            'qr_token'         => $this->generateQrToken(),
        ]);
    }

    /**
     * Update an existing household.
     */
    public function update(Household $household, array $data): Household
    {
        $household->update($data);
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
}
