@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Narratives</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $narratives->count() }} total</p>
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
                @forelse($narratives as $narrative)
                <li class="px-6 py-4 flex items-center justify-between gap-4 hover:bg-gray-50 transition-colors">
                    <a href="{{ route('heirloom.narratives.show', $narrative) }}" class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $narrative->subject?->name ?? 'Unknown subject' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ ucfirst($narrative->format) }} · {{ $narrative->created_at->diffForHumans() }}</p>
                    </a>
                    <form method="POST"
                          action="{{ route('heirloom.narratives.destroy', $narrative) }}"
                          onsubmit="return confirm('Delete this narrative?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600 px-2 py-1 rounded hover:bg-red-50 transition-colors shrink-0">
                            Delete
                        </button>
                    </form>
                </li>
                @empty
                <li class="px-6 py-4 text-sm text-gray-400">No narratives yet.</li>
                @endforelse
            </ul>
        </div>

    </div>
</div>
@endsection