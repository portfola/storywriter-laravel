<?php

namespace App\Http\Controllers\Api\Heirloom\V1;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Session;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $sessions = Session::where('user_id', $request->user()->id)
            ->with('subject')
            ->latest()
            ->paginate(20);

        return response()->json($sessions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:heirloom_subjects,id',
            'title' => 'nullable|string|max:255',
            'duration_seconds' => 'nullable|integer',
        ]);

        $session = Session::create(array_merge($validated, [
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]));

        return response()->json($session->load('subject'), 201);
    }

    public function show(Request $request, Session $session)
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($session->load('subject'));
    }
}