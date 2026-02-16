@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">ElevenLabs Usage & Costs</h1>
        <a href="{{ route('dashboard') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            Back to Dashboard
        </a>
    </div>

    {{-- Today's Usage --}}
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Today's Usage</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Total Requests</h3>
                <p class="text-3xl font-bold mt-2">{{ number_format($data['stats']['today']['requests']) }}</p>
                <p class="text-xs text-gray-400 mt-1">TTS & Conversation</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Characters Processed</h3>
                <p class="text-3xl font-bold mt-2">{{ number_format($data['stats']['today']['characters']) }}</p>
                <p class="text-xs text-gray-400 mt-1">Total text converted</p>
            </div>
            <div class="bg-blue-50 rounded-lg shadow p-6 border-2 border-blue-200">
                <h3 class="text-blue-700 text-sm font-medium">Estimated Cost</h3>
                <p class="text-3xl font-bold mt-2 text-blue-900">${{ number_format($data['stats']['today']['cost'], 2) }}</p>
                <p class="text-xs text-blue-600 mt-1">Today's spending</p>
            </div>
        </div>
    </div>

    {{-- Weekly Usage --}}
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Last 7 Days</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Total Requests</h3>
                <p class="text-3xl font-bold mt-2">{{ number_format($data['stats']['week']['requests']) }}</p>
                <p class="text-xs text-gray-400 mt-1">TTS & Conversation</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Characters Processed</h3>
                <p class="text-3xl font-bold mt-2">{{ number_format($data['stats']['week']['characters']) }}</p>
                <p class="text-xs text-gray-400 mt-1">Total text converted</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Estimated Cost</h3>
                <p class="text-3xl font-bold mt-2">${{ number_format($data['stats']['week']['cost'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">Week's spending</p>
            </div>
        </div>
    </div>

    {{-- Monthly Usage --}}
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4">Last 30 Days</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Total Requests</h3>
                <p class="text-3xl font-bold mt-2">{{ number_format($data['stats']['month']['requests']) }}</p>
                <p class="text-xs text-gray-400 mt-1">TTS & Conversation</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Characters Processed</h3>
                <p class="text-3xl font-bold mt-2">{{ number_format($data['stats']['month']['characters']) }}</p>
                <p class="text-xs text-gray-400 mt-1">Total text converted</p>
            </div>
            <div class="bg-green-50 rounded-lg shadow p-6 border-2 border-green-200">
                <h3 class="text-green-700 text-sm font-medium">Estimated Cost</h3>
                <p class="text-3xl font-bold mt-2 text-green-900">${{ number_format($data['stats']['month']['cost'], 2) }}</p>
                <p class="text-xs text-green-600 mt-1">Month's spending</p>
            </div>
        </div>
    </div>

    {{-- Cost Breakdown by Model --}}
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold">Cost Breakdown by Model (Last 30 Days)</h2>
        </div>
        <div class="overflow-x-auto">
            @if($data['cost_by_model']->count() > 0)
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requests</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Characters</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($data['cost_by_model'] as $model)
                    <tr>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 font-medium">
                                {{ $model->model_id }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">{{ number_format($model->request_count) }}</td>
                        <td class="px-6 py-4 text-sm">{{ number_format($model->total_characters) }}</td>
                        <td class="px-6 py-4 text-sm font-semibold">${{ number_format($model->total_cost, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="p-8 text-center text-gray-500">
                <p>No usage data available for the last 30 days.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Top Users by Usage --}}
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold">Top Users by Usage (Last 30 Days)</h2>
        </div>
        <div class="overflow-x-auto">
            @if($data['top_users']->count() > 0)
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requests</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Characters</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($data['top_users'] as $usage)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium">{{ $usage->user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $usage->user->email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm">{{ number_format($usage->request_count) }}</td>
                        <td class="px-6 py-4 text-sm">{{ number_format($usage->total_characters) }}</td>
                        <td class="px-6 py-4 text-sm font-semibold">${{ number_format($usage->total_cost, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="p-8 text-center text-gray-500">
                <p>No usage data available for the last 30 days.</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
