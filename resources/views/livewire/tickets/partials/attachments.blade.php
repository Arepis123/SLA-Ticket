<div class="flex flex-wrap gap-2">
    @foreach ($attachments as $attachment)
        @php($url = route('tickets.attachments.show', [$ticket, $attachment]))
        @if ($attachment->isImage())
            <a href="{{ $url }}" target="_blank" class="block overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" title="{{ $attachment->original_name }}">
                <img src="{{ $url }}" alt="{{ $attachment->original_name }}" class="h-24 w-32 object-cover" loading="lazy">
            </a>
        @else
            <a href="{{ $url }}" target="_blank" class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-white/5">
                <flux:icon name="document" variant="micro" class="text-zinc-400" />
                <span class="max-w-48 truncate">{{ $attachment->original_name }}</span>
                <span class="text-xs text-zinc-500">{{ $attachment->humanSize() }}</span>
            </a>
        @endif
    @endforeach
</div>
