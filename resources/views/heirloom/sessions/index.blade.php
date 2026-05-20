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

        @if(session('status'))
        <div class="mb-4 px-4 py-3 bg-green-50 text-green-700 rounded-lg text-sm">
            {{ session('status') }}
        </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm">
            <ul class="divide-y divide-gray-100">
                @forelse($sessions as $session)
                <li class="px-6 py-4 flex items-center justify-between gap-4 hover:bg-gray-50 transition-colors">
                    <a href="{{ route('heirloom.sessions.show', $session) }}" class="flex-1 min-w-0 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $session->subject?->name ?? 'Unknown subject' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $session->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($session->narratives_count)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">
                                {{ $session->narratives_count }} {{ Str::plural('narrative', $session->narratives_count) }}
                            </span>
                            @endif
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
                    <form method="POST"
                          action="{{ route('heirloom.sessions.destroy', $session) }}"
                          onsubmit="return confirm('Delete this session and all its data?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50 transition-colors shrink-0">
                            Delete
                        </button>
                    </form>
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
