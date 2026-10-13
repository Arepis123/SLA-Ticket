<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __invoke(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        Gate::authorize('downloadAttachment', [$ticket, $attachment]);

        $disposition = $attachment->isImage() || $attachment->mime_type === 'application/pdf' ? 'inline' : 'attachment';

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            disposition: $disposition,
        );
    }
}
