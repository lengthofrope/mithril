<div class="space-y-3">
    @forelse ($activities as $activity)
        <x-tl.activity-item :activity="$activity" />
    @empty
        <p class="py-4 text-center text-sm text-gray-400 dark:text-gray-500">No activity yet.</p>
    @endforelse
</div>
