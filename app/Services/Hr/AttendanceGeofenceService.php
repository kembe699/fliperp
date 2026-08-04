<?php

namespace App\Services\Hr;

use App\Models\AttendanceGeofence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceGeofenceService
{
    protected const EARTH_RADIUS_METERS = 6371000;

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return AttendanceGeofence::query()
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): AttendanceGeofence
    {
        // Postgres inserts only RETURNING the id, so a DB-level column
        // default (is_active) never makes it back onto the in-memory model
        // unless set explicitly here — without this the create response
        // would show is_active: null even though the row is actually true.
        return AttendanceGeofence::create(['is_active' => true, ...$data]);
    }

    public function update(AttendanceGeofence $geofence, array $data): AttendanceGeofence
    {
        $geofence->update($data);

        return $geofence;
    }

    public function delete(AttendanceGeofence $geofence): void
    {
        $geofence->delete();
    }

    /**
     * Great-circle distance between two lat/lng points, in meters.
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Whether the given point falls within the radius of at least one
     * active geofence for the branch.
     */
    public function isWithinBranchGeofence(int $branchId, float $latitude, float $longitude): bool
    {
        $geofences = AttendanceGeofence::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        foreach ($geofences as $geofence) {
            $distance = self::distanceMeters($latitude, $longitude, (float) $geofence->latitude, (float) $geofence->longitude);

            if ($distance <= $geofence->radius_meters) {
                return true;
            }
        }

        return false;
    }
}
