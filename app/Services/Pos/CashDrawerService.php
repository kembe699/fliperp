<?php

namespace App\Services\Pos;

use App\Models\CashDrawerSession;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The choke point for cash drawer sessions: enforces the one-open-session-
 * per-user-per-branch rule and computes expected_closing/variance on close.
 */
class CashDrawerService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CashDrawerSession::query()->latest('opened_at')->paginate($perPage);
    }

    public function current(): ?CashDrawerSession
    {
        return CashDrawerSession::query()
            ->where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();
    }

    public function open(array $data): CashDrawerSession
    {
        $user = Auth::user();
        $branchId = $data['branch_id'] ?? $user->branch_id;

        return DB::transaction(function () use ($data, $user, $branchId) {
            $existing = CashDrawerSession::query()
                ->where('user_id', $user->id)
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'cash_drawer_session' => ['You already have an open cash drawer session for this branch. Close it before opening a new one.'],
                ]);
            }

            return CashDrawerSession::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'opening_float' => round((float) $data['opening_float'], 2),
                'opened_at' => now(),
                'status' => 'open',
            ]);
        });
    }

    public function close(CashDrawerSession $session, array $data): CashDrawerSession
    {
        if ($session->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only an open cash drawer session can be closed.'],
            ]);
        }

        return DB::transaction(function () use ($session, $data) {
            $session = CashDrawerSession::query()->lockForUpdate()->findOrFail($session->id);

            $saleIds = Sale::query()->where('cash_drawer_session_id', $session->id)->pluck('id');

            $cashCollected = (float) SalePayment::query()
                ->whereIn('sale_id', $saleIds)
                ->whereHas('paymentType', fn ($query) => $query->where('type', 'cash'))
                ->sum('amount');

            $expectedClosing = round((float) $session->opening_float + $cashCollected, 2);
            $closingFloat = round((float) $data['closing_float'], 2);
            $variance = round($closingFloat - $expectedClosing, 2);

            $session->update([
                'closing_float' => $closingFloat,
                'expected_closing' => $expectedClosing,
                'variance' => $variance,
                'closed_at' => now(),
                'status' => 'closed',
            ]);

            return $session->fresh();
        });
    }
}
