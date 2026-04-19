<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRosterRequest;
use App\Http\Responses\ApiEnvelope;
use App\Models\RosterImport;
use App\Models\Term;
use App\Services\RosterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RosterImportController extends Controller
{
    public function __construct(
        private readonly RosterImportService $rosterImportService,
    ) {
    }

    public function store(ImportRosterRequest $request, Term $term): JsonResponse
    {
        $this->authorize('create', [RosterImport::class, $term->id]);

        $file   = $request->file('file');
        $import = $this->rosterImportService->import(
            $request->user(),
            $term,
            $file->getClientOriginalName(),
            $file->getRealPath(),
        );

        return ApiEnvelope::data($import, 201);
    }

    public function history(Request $request, Term $term): JsonResponse
    {
        $this->authorize('create', [RosterImport::class, $term->id]);

        $imports = RosterImport::where('term_id', $term->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiEnvelope::data($imports);
    }

    public function show(RosterImport $rosterImport): JsonResponse
    {
        $this->authorize('view', $rosterImport);

        return ApiEnvelope::data($rosterImport->load('errors'));
    }
}
