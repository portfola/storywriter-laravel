@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6">
            <a href="{{ route('heirloom.sessions.show', $narrative->session_id) }}"
               class="text-sm text-gray-500 hover:text-gray-700">← Back to session</a>

            <div class="flex items-start justify-between mt-3">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        {{ $narrative->session->subject?->name ?? 'Unknown subject' }}
                    </h1>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 uppercase tracking-wide font-medium">
                            {{ $narrative->format }}
                        </span>
                        <span class="text-xs text-gray-400">
                            Generated {{ $narrative->created_at->format('j M Y, g:ia') }}
                        </span>
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('heirloom.narratives.destroy', $narrative) }}"
                      onsubmit="return confirm('Delete this narrative?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 rounded border border-red-200 hover:bg-red-50 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm px-8 py-8">
            <div class="prose max-w-none text-gray-800 leading-relaxed whitespace-pre-wrap text-sm">{{ $narrative->narrative_text }}</div>
        </div>

    </div>
</div>
@endsection
