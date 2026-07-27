@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Admin Dashboard</a>
                <h1 class="text-2xl font-semibold text-gray-900 mt-2">{{ $story->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    By {{ $story->user->name }} · {{ $story->created_at->format('j M Y, g:ia') }}
                </p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600">
                {{ $story->pages->count() }} pages
            </span>
        </div>

        @if($story->prompt)
        <div class="bg-white rounded-lg shadow-sm mb-6 px-6 py-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Prompt</p>
            <p class="text-sm text-gray-700 italic">"{{ $story->prompt }}"</p>
        </div>
        @endif

        @if($story->characters_description)
        <div class="bg-white rounded-lg shadow-sm mb-6 px-6 py-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Characters</p>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $story->characters_description }}</p>
        </div>
        @endif

        @foreach($story->pages as $page)
        <div class="bg-white rounded-lg shadow-sm mb-4">
            <div class="px-6 py-3 border-b border-gray-100 flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Page {{ $page->page_number }}</span>
                {{-- image_url is a path on a private bucket, so link the signed URL --}}
                @if($page->signed_image_url)
                <a href="{{ $page->signed_image_url }}" target="_blank" class="text-xs text-gray-400 hover:text-gray-600">View image →</a>
                @endif
            </div>

            <div class="flex gap-6 p-6">
                @if($page->signed_image_url)
                <div class="shrink-0">
                    <img src="{{ $page->signed_image_url }}" alt="Page {{ $page->page_number }} illustration"
                         class="w-32 h-32 object-cover rounded-lg">
                </div>
                @endif

                <div class="flex-1">
                    <p class="text-sm text-gray-800 leading-relaxed">{{ $page->content }}</p>

                    @if($page->illustration_prompt)
                    <p class="mt-3 text-xs text-gray-400 italic">Illustration: {{ $page->illustration_prompt }}</p>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        @if($story->pages->isEmpty())
        <div class="bg-white rounded-lg shadow-sm px-6 py-8 text-center">
            <p class="text-sm text-gray-400">No pages found for this story.</p>
            @if($story->body)
            <div class="mt-4 text-left">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Raw Body</p>
                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $story->body }}</p>
            </div>
            @endif
        </div>
        @endif

    </div>
</div>
@endsection
