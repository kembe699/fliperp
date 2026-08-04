<?php

namespace App\Services\Hr;

use App\Models\AttendanceQrToken;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AttendanceQrTokenService
{
    public function currentToken(Branch $branch): ?AttendanceQrToken
    {
        return AttendanceQrToken::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->latest('created_at')
            ->first();
    }

    public function regenerate(Branch $branch): AttendanceQrToken
    {
        return DB::transaction(function () use ($branch) {
            AttendanceQrToken::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return AttendanceQrToken::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'token' => Str::random(48),
                'is_active' => true,
                'created_at' => now(),
            ]);
        });
    }

    public function isValidToken(int $branchId, string $token): bool
    {
        return AttendanceQrToken::query()
            ->where('branch_id', $branchId)
            ->where('token', $token)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Renders the QR code as SVG markup encoding the given URL. SVG avoids
     * an Imagick dependency (which the PNG backend requires) and prints
     * crisply at any size.
     */
    public function svgFor(string $url): string
    {
        return (string) QrCode::format('svg')->size(320)->margin(1)->generate($url);
    }
}
