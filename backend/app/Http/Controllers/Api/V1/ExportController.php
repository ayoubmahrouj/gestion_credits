<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Services\ExportService;

class ExportController extends Controller
{
    public function __construct(private readonly ExportService $exports) {}

    public function excel(ReportFilterRequest $request)
    {
        $filters = $request->filters();
        $writer = $this->exports->workbook($filters);

        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'export-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.xlsx';

        $writer->save($path);

        $name = 'gestion-credits-'.$filters['scope'].'-'.$filters['from'].'_'.$filters['to'].'.xlsx';

        return response()
            ->download($path, $name, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }
}
