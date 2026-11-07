<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(Request $request)
    {
        $this->guard($request);

        return response()->json(['data' => $this->backups->all()]);
    }

    public function store(Request $request)
    {
        $this->guard($request);

        try {
            $backup = $this->backups->create();
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->backups->prune();

        return response()->json(['data' => $backup], 201);
    }

    public function download(Request $request, string $file)
    {
        $this->guard($request);

        try {
            $path = $this->backups->path($file);
        } catch (Throwable $e) {
            abort(404, $e->getMessage());
        }

        return response()->download($path);
    }

    public function destroy(Request $request, string $file)
    {
        $this->guard($request);

        try {
            $this->backups->delete($file);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => __('Sauvegarde supprimée.')]);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, __('Action réservée aux administrateurs.'));
    }
}
