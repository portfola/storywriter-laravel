@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Sessions</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $sessions->total() }} total</p>
            </div>
            <a href="{{ route('heirloom.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Dashboard</a>
        </div>

        <div class="bg-white rounded-lg shadow-sm">
            <ul class="divide-y divide-gray-100">
                @forelse($sessions as $session)
                <li>
                    <a href="{{ route('heirloom.sessions.show', $session) }}" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $session->subject?->name ?? 'Unknown subject' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $session->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($session->transcript)
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $session->transcript->source === 'audio' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">
                                {{ $session->transcript->source }}
                            </span>
                            @endif
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $session->status === 'transcribed' ? 'bg-green-50 text-green-600' : 'bg-yellow-50 text-yellow-600' }}">
                                {{ $session->status }}
                            </span>
                        </div>
                    </a>
                </li>
                @empty
                <li class="px-6 py-4 text-sm text-gray-400">No sessions yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="mt-4">
            {{ $sessions->links() }}
        </div>

    </div>
</div>
@endsection