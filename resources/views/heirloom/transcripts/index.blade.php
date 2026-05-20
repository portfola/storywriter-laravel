@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Transcripts</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $transcripts->total() }} total</p>
            </div>
            <a href="{{ route('heirloom.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Dashboard</a>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <ul class="divide-y divide-gray-100">
                @forelse($transcripts as $transcript)
                <li>
                    <a href="{{ route('heirloom.transcripts.show', $transcript) }}" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0 mr-4">
                            <p class="text-sm font-medium text-gray-900">{{ $transcript->session?->subject?->name ?? 'Unknown subject' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ Str::limit($transcript->transcript_text, 100) }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $transcript->source === 'audio' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                                {{ $transcript->source }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $transcript->created_at->diffForHumans() }}</span>
                        </div>
                    </a>
                </li>
                @empty
                <li class="px-6 py-4 text-sm text-gray-400">No transcripts yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="mt-4">
            {{ $transcripts->links() }}
        </div>

    </div>
</div>
@endsection