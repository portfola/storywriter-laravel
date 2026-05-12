<?php

namespace App\Http\Controllers\Heirloom;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Session;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with(['subject', 'transcript'])
            ->latest()
            ->paginate(20);

        return view('heirloom.sessions.index', compact('sessions'));
    }

    public function show(Session $session)
    {
        $session->load(['subject', 'transcript', 'narratives']);
        return view('heirloom.sessions.show', compact('session'));
    }
}