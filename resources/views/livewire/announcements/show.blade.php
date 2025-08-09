<x-layouts.app>
    <div class="w-full space-y-6">
        <div class="bg-red-900/30 border-l-4 border-red-500 text-red-300 px-4 py-3 shadow-sm rounded">
            <strong class="font-semibold text-red-200">Warning:</strong>
            You are about to delete this announcement.<br>
            <span class="text-sm text-red-200">Use the buttons on the right-hand side to edit or confirm deletion, or use the back button to go back.</span>
        </div>
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ $announcement->title }}</flux:heading>
            <div>
                @can('update', $announcement)
                    <a href="{{ route('admin.announcements.edit', $announcement->id) }}">
                        <flux:button size="xs" icon="pencil-square"></flux:button>
                    </a>
                @endcan
                @can('delete', $announcement)
                    <form action="{{ route('admin.announcements.delete', $announcement->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" size="xs" icon="trash" variant="danger"></flux:button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="text-sm text-gray-500">
            By {{ $announcement->author->name ?? 'Unknown' }} &middot;
            {{ $announcement->created_at->format('M d, Y') }}
        </div>

        <div id="editor_content" class="prose max-w-none">
            {!! $announcement->content !!}
        </div>

        <a href="{{ url()->previous() }}">
            <flux:button size="sm" icon="arrow-left"></flux:button>
        </a>
    </div>
</x-layouts.app>
