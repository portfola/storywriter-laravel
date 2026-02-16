@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Admin Dashboard</h1>
        <div class="flex gap-2">
            <a href="{{ route('dashboard.analytics') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                View Analytics
            </a>
            <a href="{{ route('dashboard.elevenlabs-usage') }}" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                ElevenLabs Usage
            </a>
        </div>
    </div>

    {{-- Recent Users --}}
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold">Recent Users</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stories</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($users as $user)
                    <tr>
                        <td class="px-6 py-4">{{ $user->name }}</td>
                        <td class="px-6 py-4">{{ $user->email }}</td>
                        <td class="px-6 py-4">{{ $user->stories_count }}</td>
                        <td class="px-6 py-4">{{ $user->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $users->links() }}
        </div>
    </div>
     {{-- Recent Stories --}}
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold">Recent Stories</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($stories as $story)
                    <tr>
                        <td class="px-6 py-4">
                        <a href="{{ route('dashboard.story', $story->slug) }}" class="text-blue-600 hover:text-blue-800">
                            {{ Str::limit($story->name, 50) }}
                        </a>   
                        </td>
                        <td class="px-6 py-4">{{ $story->user->name }}</td>
                        <td class="px-6 py-4">{{ $story->created_at->diffForHumans() }}</td>
                        <td class="px-6 py-4">
                
                    </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">
            {{ $stories->links() }}
        </div>
    </div>
     {{-- Quick Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Total Users</h3>
            <p class="text-3xl font-bold mt-2">{{ $quickStats['total_users'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Total Stories</h3>
            <p class="text-3xl font-bold mt-2">{{ $quickStats['total_stories'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Stories Today</h3>
            <p class="text-3xl font-bold mt-2">{{ $quickStats['stories_today'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Total Generations</h3>
            <p class="text-3xl font-bold mt-2">{{ $quickStats['total_generations'] }}</p>
        </div>
    </div>
    <div class="bg-blue-50 rounded-lg shadow p-6 border-2 border-blue-200">
        <h3 class="text-blue-700 text-sm font-medium">Total Generations</h3>
        <p class="text-3xl font-bold mt-2 text-blue-900">{{ $quickStats['total_generations'] }}</p>
        <a href="{{ route('dashboard.analytics') }}" class="text-blue-600 hover:text-blue-800 text-sm mt-3 inline-flex items-center font-medium">
            See Full Analytics →
        </a>
    </div>
</div>
@endsection