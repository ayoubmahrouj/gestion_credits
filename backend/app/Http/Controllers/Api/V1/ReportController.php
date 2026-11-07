<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Services\ReportService;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function summary(ReportFilterRequest $request)
    {
        return response()->json($this->reports->summary($request->filters()));
    }
}
