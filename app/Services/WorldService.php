<?php

namespace App\Services;

use App\Models\Country;
use App\Models\State;
use App\Models\City;

class WorldService
{
    /**
     * Get country details by ID
     *
     * @param int $id The country ID
     * @return Country|null The country model with states relationship, or null if not found
     */
    public function getCountryById(int $id): ?Country
    {
        try {
            return Country::with('states')->find($id);
        } catch (\Exception $e) {
            \Log::error('WorldService: Failed to get country by ID', [
                'error' => $e->getMessage(),
                'country_id' => $id
            ]);
            return null;
        }
    }

    /**
     * Get state details by ID
     *
     * @param int $id The state ID
     * @return State|null The state model with country and cities relationships, or null if not found
     */
    public function getStateById(int $id): ?State
    {
        try {
            return State::with(['country', 'cities'])->find($id);
        } catch (\Exception $e) {
            \Log::error('WorldService: Failed to get state by ID', [
                'error' => $e->getMessage(),
                'state_id' => $id
            ]);
            return null;
        }
    }

    /**
     * Get city details by ID
     *
     * @param int $id The city ID
     * @return City|null The city model with state relationship, or null if not found
     */
    public function getCityById(int $id): ?City
    {
        try {
            return City::with('state')->find($id);
        } catch (\Exception $e) {
            \Log::error('WorldService: Failed to get city by ID', [
                'error' => $e->getMessage(),
                'city_id' => $id
            ]);
            return null;
        }
    }
}
