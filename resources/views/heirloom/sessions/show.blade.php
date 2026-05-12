@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('heirloom.sessions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Sessions</a>
                <h1 class="text-2xl font-semibold text-gray-900 mt-2">{{ $session->subject?->name ?? 'Unknown subject' }}</h1>
                <p class="text-sm text-gray-500 mt-1">Session #{{ $session->id }} · {{ $session->created_at->format('j M Y, g:ia') }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $session->status === 'transcribed' ? 'bg-green-50 text-green-600' : 'bg-yellow-50 text-yellow-600' }}">
                {{ $session->status }}
            </span>
        </div>

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
        @if($session->narratives->count())
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Narratives</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($session->narratives as $narrative)
                <li class="px-6 py-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">{{ $narrative->format }}</span>
                        <span class="text-xs text-gray-400">{{ $narrative->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="px-6 py-6 space-y-6">
                        <h3>Transcribed Session: </h3>
                    @php
                        $text = $transcript->transcript_text;
                        preg_match_all('/([QA]):\s*(.*?)(?=[QA]:|$)/s', $text, $matches, PREG_SET_ORDER);
                    @endphp

                    @if(count($matches))
                        @foreach($matches as $match)
                            @if(trim($match[2]))
                            <div class="flex gap-4">
                                <span class="shrink-0 w-6 h-6 rounded-full text-xs font-semibold flex items-center justify-center mt-0.5
                                    {{ $match[1] === 'Q' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $match[1] }}
                                </span>
                                <p class="text-sm text-gray-700 leading-relaxed">{{ trim($match[2]) }}</p>
                            </div>
                            @endif
                        @endforeach
                    @else
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $transcript->transcript_text }}</p>
                    @endif
                </div>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

    </div>
</div>
@endsection