<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportSessionsRequest;
use App\Models\User;
use App\Services\Export\SessionExportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionExportController extends Controller
{
    public function __invoke(ExportSessionsRequest $request, SessionExportService $exportService): Response|StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $exportService->exportSessions(
            $user,
            $request->periodFrom(),
            $request->periodTo(),
            $request->weaponIds(),
            $request->exportFormat(),
        );
    }
}
