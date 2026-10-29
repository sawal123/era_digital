<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class UpdateController extends Controller
{
    public function index()
    {
        return Inertia::render('Updates/Index', [
            'latestUpdate' => config('updates.latest'),
            'updates' => config('updates.history', []),
        ]);
    }
}
