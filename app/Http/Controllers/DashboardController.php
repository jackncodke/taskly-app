<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with the authenticated user's projects.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'projects' => $request->user()
                ->projects()
                ->latest()
                ->get(['id', 'description']),
        ]);
    }
}
