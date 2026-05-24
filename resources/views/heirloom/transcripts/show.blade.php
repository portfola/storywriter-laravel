@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('heirloom.transcripts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Transcripts</a>
                <h1 class="text-2xl font-semibold text-gray-900 mt-2">{{ $transcript->session?->subject?->name ?? 'Unknown subject' }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Transcript #{{ $transcript->id }} ·
                    <a href="{{ route('heirloom.sessions.show', $transcript->session) }}" class="hover:text-gray-700">Session #{{ $transcript->session_id }}</a> ·
                    {{ $transcript->created_at->format('j M Y, g:ia') }}
                </p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $transcript->source === 'audio' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                {{ $transcript->source }}
            </span>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Full Transcript</h2>
            </div>
            <div class="px-6 py-6 space-y-6">
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
        </div>

    </div>
</div>
@endsection