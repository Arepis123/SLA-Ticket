<div class="grid gap-2 sm:grid-cols-2">
    @foreach ($attachments as $attachment)
        @php($url = route('tickets.attachments.show', [$ticket, $attachment]))

        <flux:file-item
            wire:key="attachment-{{ $attachment->id }}"
            :heading="$attachment->original_name"
            :image="$attachment->isImage() ? $url : null"
            :size="$attachment->size"
        >
            <x-slot name="actions">
                <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$url" target="_blank" :aria-label="__('Open :file', ['file' => $attachment->original_name])" />
            </x-slot>
        </flux:file-item>
    @endforeach
</div>
