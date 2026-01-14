@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Story Analytics</h1>
        <a href="{{ route('dashboard') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            Back to Dashboard
        </a>
    </div>

    {{-- Overview Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Total Requests</h3>
            <p class="text-3xl font-bold mt-2">{{ number_format($data['overview']['total_requests']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Success Rate</h3>
            <p class="text-3xl font-bold mt-2">{{ $data['overview']['success_rate'] }}%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Avg Generation Time</h3>
            <p class="text-3xl font-bold mt-2">{{ number_format($data['overview']['avg_generation_time']) }}ms</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Avg Story Length</h3>
            <p class="text-3xl font-bold mt-2">{{ number_format($data['avg_story_length']) }} chars</p>
        </div>
    </div>

    {{-- Device Breakdown --}}
    <div class="bg-white rounded-lg shadow mb-8 p-6">
        <h2 class="text-xl font-bold mb-4">Device Breakdown</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($data['overview']['device_breakdown'] as $device)
            <div class="border rounded p-4">
                <p class="text-gray-600 text-sm">{{ ucfirst($device['device']) }}</p>
                <p class="text-2xl font-bold">{{ $device['count'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold">Recent Generation Activity</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transcript</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Story</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($data['recent_activity'] as $activity)
                    <tr>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium">{{ $activity->user_name }}</div>
                            <div class="text-xs text-gray-500">{{ $activity->user_email }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm">{{ ucfirst($activity->device_type ?? 'unknown') }}</td>
                        <td class="px-6 py-4 text-sm">{{ number_format($activity->transcript_length ?? 0) }} chars</td>
                        <td class="px-6 py-4 text-sm">{{ number_format($activity->story_length ?? 0) }} chars</td>
                        <td class="px-6 py-4 text-sm">{{ number_format($activity->generation_time_ms) }}ms</td>
                        <td class="px-6 py-4">
                            @if($activity->successful)
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Success</span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection