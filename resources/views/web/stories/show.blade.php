<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Story Viewer') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    {{-- Header Section --}}
                    <div class="mb-6 border-b pb-4">
                        <h1 class="text-3xl font-bold mb-2">{{ $story->name }}</h1>
                        <div class="flex items-center text-sm text-gray-500 space-x-4">
                            <span>👤 {{ $story->user->name }}</span>
                            <span>📅 {{ $story->created_at->format('F j, Y, g:i a') }}</span>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">ID: {{ $story->id }}</span>
                        </div>
                    </div>

                    {{-- Metadata / Prompt Section --}}
                    @if($story->prompt)
                        <div class="mb-6 bg-gray-50 p-4 rounded-md border border-gray-200">
                            <h4 class="text-xs font-uppercase font-bold text-gray-400 tracking-wider mb-1">ORIGINAL PROMPT</h4>
                            <p class="italic text-gray-600">"{{ $story->prompt }}"</p>
                        </div>
                    @endif

                    {{-- Story Content --}}
                    <div class="prose max-w-none">
                        {{-- nl2br allows line breaks from the DB to show as HTML breaks --}}
                        {!! nl2br(e($story->body)) !!}
                    </div>

                    {{-- Back Button --}}
                    <div class="mt-8 pt-4 border-t">
                        <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                            &larr; Back to Dashboard
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>