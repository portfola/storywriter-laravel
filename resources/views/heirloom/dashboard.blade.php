@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-gray-900">Heirloom</h1>
            <p class="text-sm text-gray-500 mt-1">Admin overview</p>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            @foreach([
                ['label' => 'Subjects',  'value' => $stats['subjects']],
                ['label' => 'Sessions',  'value' => $stats['sessions']],
                ['label' => 'Transcripts', 'value' => $stats['transcripts']],
                ['label' => 'Narratives', 'value' => $stats['narratives']],
                ['label' => 'Audio',     'value' => $stats['audio_sessions']],
                ['label' => 'Manual',    'value' => $stats['manual_sessions']],
            ] as $stat)
            <div class="bg-white rounded-lg shadow-sm p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
                <p class="text-3xl font-semibold text-gray-900 mt-1">{{ $stat['value'] }}</p>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Subjects --}}
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Subjects</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($subjects as $subject)
                    <li class="px-6 py-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $subject->name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Added {{ $subject->created_at->diffForHumans() }}</p>
                        </div>
                        <span class="text-xs text-gray-500">
                            {{ $subject->sessions_count }} {{ Str::plural('session', $subject->sessions_count) }}
                        </span>
                    </li>
                    @empty
                    <li class="px-6 py-4 text-sm text-gray-400">No subjects yet.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Recent Activity --}}
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Recent Activity</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($recentActivity as $session)
                    <li class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $session->subject?->name ?? 'Unknown subject' }}
                            </p>
                            <div class="flex items-center gap-2">
                                @if($session->transcript)
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ $session->transcript->source === 'audio' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                                        {{ $session->transcript->source }}
                                    </span>
                                @endif
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $session->status === 'transcribed' ? 'bg-green-50 text-green-600' :
                                       ($session->status === 'pending' ? 'bg-yellow-50 text-yellow-600' : 'bg-gray-50 text-gray-500') }}">
                                    {{ $session->status }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-xs text-gray-400">{{ $session->created_at->diffForHumans() }}</p>
                            @if($session->narratives->first())
                                <p class="text-xs text-gray-400">
                                    Narrative: {{ $session->narratives->first()->format }}
                                </p>
                            @endif
                        </div>
                    </li>
                    @empty
                    <li class="px-6 py-4 text-sm text-gray-400">No activity yet.</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </div>
</div>
@endsection