<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\Finance\SystemAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Which account each automatic posting uses, for the signed-in company. */
class SystemAccountController extends Controller
{
    public function __construct(protected SystemAccountService $systemAccounts) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        return $this->success($this->systemAccounts->all($request->user()->company_id));
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', ChartOfAccount::class);
        $data = $request->validate([
            'accounts' => ['required', 'array'],
            'accounts.*' => ['required', 'integer'],
        ]);

        $this->systemAccounts->update($request->user()->company_id, $data['accounts']);

        return $this->success($this->systemAccounts->all($request->user()->company_id), 'System accounts updated');
    }
}
