<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse|View
    {
        Gate::authorize('downloadAttachment', [$ticket, $attachment]);

        // Browsers title a bare image tab "name.jpeg 1,280×720 pixels"; wrapping it
        // in a page of our own makes the tab show just the file name.
        if ($attachment->isImage() && $request->boolean('view')) {
            return view('attachments.image', [
                'name' => $attachment->original_name,
                'src' => route('tickets.attachments.show', [$ticket, $attachment, $attachment->original_name]),
            ]);
        }

        $disposition = $attachment->isImage() || $attachment->mime_type === 'application/pdf' ? 'inline' : 'attachment';

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            disposition: $disposition,
        );
    }
}
