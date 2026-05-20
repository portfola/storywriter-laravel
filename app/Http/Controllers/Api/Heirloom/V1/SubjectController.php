<?php

namespace App\Http\Controllers\Api\Heirloom\V1;

use App\Http\Controllers\Controller;
use App\Models\Heirloom\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $subjects = Subject::where('user_id', $request->user()->id)->paginate(20);
        return response()->json($subjects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'birth_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'places_lived' => 'nullable|string',
            'education_profession' => 'nullable|string',
            'family_structure' => 'nullable|string',
            'life_chapters' => 'nullable|string',
            'interests' => 'nullable|string',
        ]);

        $subject = Subject::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return response()->json($subject, 201);
    }

    public function show(Request $request, Subject $subject)
    {
        if ($subject->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($subject);
    }

    public function update(Request $request, Subject $subject)
    {
        if ($subject->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'birth_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'places_lived' => 'nullable|string',
            'education_profession' => 'nullable|string',
            'family_structure' => 'nullable|string',
            'life_chapters' => 'nullable|string',
            'interests' => 'nullable|string',
        ]);

        $subject->update($validated);

        return response()->json($subject);
    }

    public function destroy(Request $request, Subject $subject)
    {
        if ($subject->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $subject->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }
}