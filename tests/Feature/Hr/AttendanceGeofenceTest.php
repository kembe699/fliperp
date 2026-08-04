<?php

use App\Services\Hr\AttendanceGeofenceService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->company, $this->branch] = createCompanyWithMainBranch();
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);

    Sanctum::actingAs($this->admin, ['*']);
});

it('computes zero distance for identical coordinates', function () {
    $distance = AttendanceGeofenceService::distanceMeters(0.3476, 32.5825, 0.3476, 32.5825);

    expect($distance)->toBeLessThan(0.01);
});

it('computes a known real-world distance accurately (~1 degree of latitude at the equator)', function () {
    // 1 degree of latitude is ~111,320 meters everywhere on Earth.
    $distance = AttendanceGeofenceService::distanceMeters(0.0, 32.5825, 1.0, 32.5825);

    expect($distance)->toBeGreaterThan(110000)->toBeLessThan(112000);
});

it('accepts a point inside the radius and rejects one just outside it', function () {
    $response = $this->postJson('/api/v1/attendance-geofences', [
        'branch_id' => $this->branch->id,
        'name' => 'Main Office',
        'latitude' => 0.3476,
        'longitude' => 32.5825,
        'radius_meters' => 100,
    ]);
    $response->assertCreated();

    $service = app(AttendanceGeofenceService::class);

    // Same point: well within radius.
    expect($service->isWithinBranchGeofence($this->branch->id, 0.3476, 32.5825))->toBeTrue();

    // ~30m north (0.00027 degrees lat ~= 30m): still within 100m radius.
    expect($service->isWithinBranchGeofence($this->branch->id, 0.34787, 32.5825))->toBeTrue();

    // ~500m north: well outside the 100m radius.
    expect($service->isWithinBranchGeofence($this->branch->id, 0.3521, 32.5825))->toBeFalse();
});

it('ignores an inactive geofence when checking whether a point is within range', function () {
    $this->postJson('/api/v1/attendance-geofences', [
        'branch_id' => $this->branch->id,
        'name' => 'Old Office',
        'latitude' => 0.3476,
        'longitude' => 32.5825,
        'radius_meters' => 100,
        'is_active' => false,
    ])->assertCreated();

    $service = app(AttendanceGeofenceService::class);

    expect($service->isWithinBranchGeofence($this->branch->id, 0.3476, 32.5825))->toBeFalse();
});

it('performs CRUD on geofences scoped to the company', function () {
    $created = $this->postJson('/api/v1/attendance-geofences', [
        'branch_id' => $this->branch->id,
        'name' => 'Warehouse Gate',
        'latitude' => 0.35,
        'longitude' => 32.6,
        'radius_meters' => 150,
    ])->assertCreated()->json('data');

    $this->getJson('/api/v1/attendance-geofences?branch_id='.$this->branch->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->putJson("/api/v1/attendance-geofences/{$created['id']}", ['radius_meters' => 200])
        ->assertOk()
        ->assertJsonPath('data.radius_meters', 200);

    $this->deleteJson("/api/v1/attendance-geofences/{$created['id']}")->assertOk();
    $this->getJson("/api/v1/attendance-geofences/{$created['id']}")->assertStatus(404);
});

it('denies access to a geofence belonging to another company', function () {
    [$otherCompany, $otherBranch] = createCompanyWithMainBranch();
    $otherAdmin = createUserWithRole('company_admin', $otherCompany, $otherBranch);
    Sanctum::actingAs($otherAdmin, ['*']);

    $otherGeofenceId = $this->postJson('/api/v1/attendance-geofences', [
        'branch_id' => $otherBranch->id,
        'name' => 'Other Co Office',
        'latitude' => 0.3,
        'longitude' => 32.5,
        'radius_meters' => 100,
    ])->json('data.id');

    Sanctum::actingAs($this->admin, ['*']);

    // AttendanceGeofence is a TenantModel, so route-model-binding is
    // globally scoped to the current company and the record simply isn't
    // found — a 404, not a 403.
    $this->getJson("/api/v1/attendance-geofences/{$otherGeofenceId}")->assertStatus(404);
});

it('regenerates the branch QR token, invalidating the previous one', function () {
    $first = $this->postJson("/api/v1/attendance-geofences/{$this->branch->id}/regenerate-qr-token")
        ->assertOk()
        ->json('data.token');

    $second = $this->postJson("/api/v1/attendance-geofences/{$this->branch->id}/regenerate-qr-token")
        ->assertOk()
        ->json('data.token');

    expect($first)->not->toBe($second);

    $service = app(App\Services\Hr\AttendanceQrTokenService::class);
    expect($service->isValidToken($this->branch->id, $first))->toBeFalse();
    expect($service->isValidToken($this->branch->id, $second))->toBeTrue();
});

it('returns an SVG QR code image for a branch', function () {
    $response = $this->get("/api/v1/attendance-geofences/{$this->branch->id}/qr-code");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml');
    expect($response->getContent())->toContain('<svg');
});
