<?php

namespace App\Policies;

use App\Models\AttendanceGeofence;
use App\Models\User;

class AttendanceGeofencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance-geofences.view');
    }

    public function view(User $user, AttendanceGeofence $geofence): bool
    {
        return $user->can('attendance-geofences.view') && $geofence->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('attendance-geofences.create');
    }

    public function update(User $user, AttendanceGeofence $geofence): bool
    {
        return $user->can('attendance-geofences.update') && $geofence->company_id === $user->company_id;
    }

    public function delete(User $user, AttendanceGeofence $geofence): bool
    {
        return $user->can('attendance-geofences.delete') && $geofence->company_id === $user->company_id;
    }
}
