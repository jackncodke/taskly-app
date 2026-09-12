<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\TaskStatus;
use Illuminate\Http\JsonResponse;

class TaskStatusController extends Controller
{
    /**
     * List the statuses a task may hold, in board order.
     *
     * Built from the enum itself, so a case cannot be added without showing up
     * here — which spares every client from keeping its own copy of the list
     * and the Portuguese labels.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => TaskStatus::options()]);
    }
}
