<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    /**
     * Reference data, not company-scoped and not sensitive — deliberately
     * unauthenticated (see routes/api.php) so the public, no-login receipt
     * verification page can also render amounts with the right symbol.
     */
    public function index(): JsonResponse
    {
        return $this->success(CurrencyResource::collection(Currency::orderBy('code')->get()));
    }
}
