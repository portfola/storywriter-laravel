@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex items-start justify-between">
            <div>
                <a href="{{ route('heirloom.sessions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Sessions</a>
                <h1 class="text-2xl font-semibold text-gray-900 mt-2">{{ $session->subject?->name ?? 'Unknown subject' }}</h1>
                <p class="text-sm text-gray-500 mt-1">Session #{{ $session->id }} · {{ $session->created_at->format('j M Y, g:ia') }}</p>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <span class="text-xs px-2 py-0.5 rounded-full {{ $session->status === 'transcribed' ? 'bg-green-50 text-green-600' : 'bg-yellow-50 text-yellow-600' }}">
                    {{ $session->status }}
                </span>
                <form method="POST"
                      action="{{ route('heirloom.sessions.destroy', $session) }}"
                      x-data
                      @submit="confirm('Delete this session and all its transcripts and narratives?') || $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 rounded border border-red-200 hover:bg-red-50 transition-colors">
                        Delete session
                    </button>
                </form>
            </div>
        </div>

        @if(session('status'))
        <div class="mb-4 px-4 py-3 bg-green-50 text-green-700 rounded-lg text-sm">
            {{ session('status') }}
        </div>
        @endif

        {{-- Transcript --}}
        @if($session->transcript)
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Transcript</h2>
                <div class="flex items-center gap-2">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $session->transcript->source === 'audio' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                        {{ $session->transcript->source }}
                    </span>
                    <a href="{{ route('heirloom.transcripts.show', $session->transcript) }}" class="text-xs text-gray-500 hover:text-gray-700">View full →</a>
                </div>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ Str::limit($session->transcript->transcript_text, 600) }}</p>
                @if(strlen($session->transcript->transcript_text) > 600)
                <a href="{{ route('heirloom.transcripts.show', $session->transcript) }}" class="text-sm text-gray-500 hover:text-gray-700 mt-2 inline-block">Read full transcript →</a>
                @endif
            </div>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-sm mb-6 px-6 py-4">
            <p class="text-sm text-gray-400">No transcript yet.</p>
        </div>
        @endif

        {{-- Narratives --}}
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                    Narratives ({{ $session->narratives->count() }})
                </h2>
            </div>

            @if($session->narratives->count())
            <ul class="divide-y divide-gray-100">
                @foreach($session->narratives as $narrative)
                <li class="px-6 py-4 flex items-center justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 uppercase tracking-wide font-medium">
                                {{ $narrative->format }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $narrative->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-gray-600 truncate">{{ Str::limit($narrative->narrative_text, 120) }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('heirloom.narratives.show', $narrative) }}"
                           class="text-xs text-indigo-600 hover:text-indigo-800 px-2 py-1 rounded hover:bg-indigo-50 transition-colors">
                            View
                        </a>
                        <form method="POST"
                              action="{{ route('heirloom.narratives.destroy', $narrative) }}"
                              x-data
                              @submit="confirm('Delete this narrative?') || $event.preventDefault()">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                Delete
                            </button>
                        </form>
                    </div>
                </li>
                @endforeach
            </ul>
            @else
            <div class="px-6 py-4">
                <p class="text-sm text-gray-400">No narratives generated yet.</p>
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
