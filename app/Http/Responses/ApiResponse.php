<?php

namespace App\Http\Responses;

use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

trait ApiResponse
{
    protected function pdfResponse(DomPdf $pdf, string $filename, bool $download = false): Response
    {
        $disposition = $download ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    protected function success(mixed $data = null, string $message = '', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => null,
        ], $code);
    }

    protected function error(string $message, mixed $errors = null, int $code = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function paginated(ResourceCollection $collection, string $message = ''): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json([
            'success' => true,
            'data' => $collection->collection,
            'message' => $message,
            'errors' => null,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 200);
    }
}
