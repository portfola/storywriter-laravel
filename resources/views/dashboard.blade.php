<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3>App Users</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Stories Created</th> <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                
                                <td style="text-align: center; font-weight: bold;">
                                    {{ $user->stories_count }} 
                                </td>
                                
                                <td>{{ $user->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $users->appends(['stories_page' => $stories->currentPage()])->links() }}
                </div>
                    <h3>Recent Stories</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Story Title</th>
                            <th>Author</th> <th>Prompt Used</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stories as $story)
                       
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.story', $story->id) }}" class="text-blue-600 hover:underline">
    {{ $story->name }}
</a>
                            
                            <td>
                                @if($story->user)
                                    <span class="badge">{{ $story->user->name }}</span>
                                    <br>
                                    <small>{{ $story->user->email }}</small>
                                @else
                                    <span style="color: red;">Unknown (User Deleted)</span>
                                @endif
                            </td>

                            <td>
                                {{ Str::limit($story->prompt, 50) }}
                            </td>
                            <td>{{ $story->created_at->format('M d, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $stories->appends(['users_page' => $users->currentPage()])->links() }}
            </div>
        </div>
    </div>
</x-app-layout>