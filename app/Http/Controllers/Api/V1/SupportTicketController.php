<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketReplyRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketRequest;
use App\Http\Resources\SupportTicketReplyResource;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Repositories\Contracts\SupportTicketRepositoryInterface;
use App\Services\SupportTicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Every route-model-bound action carries **both** the `can:` middleware and an
 * `authorize()` call. The middleware asks whether you may do this kind of thing
 * at all; the policy asks whether you may do it to this row. The repository's
 * store rule protects only the listing.
 */
class SupportTicketController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SupportTicketService $tickets,
        private readonly SupportTicketRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            SupportTicketResource::collection(
                $this->repository->paginate($request->input('per_page', 15)),
            ),
        );
    }

    public function store(StoreSupportTicketRequest $request)
    {
        return $this->successResponse(
            new SupportTicketResource($this->tickets->create($request->validated())),
            'Ticket raised',
            201,
        );
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorize('view', $ticket);

        return $this->successResponse(
            new SupportTicketResource(
                $ticket->load(['reporter', 'store', 'replies.author'])->loadCount('replies'),
            ),
        );
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);

        return $this->successResponse(
            new SupportTicketResource($this->tickets->updateStatus($ticket, $request->validated())),
            'Ticket updated',
        );
    }

    public function reply(StoreSupportTicketReplyRequest $request, SupportTicket $ticket)
    {
        $this->authorize('reply', $ticket);

        return $this->successResponse(
            new SupportTicketReplyResource(
                $this->tickets->reply($ticket, $request->validated()['body']),
            ),
            'Reply sent',
            201,
        );
    }

    public function attach(Request $request, SupportTicket $ticket)
    {
        $this->authorize('reply', $ticket);

        $request->validate([
            'attachment' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        return $this->successResponse(
            new SupportTicketResource($this->tickets->attach($ticket, $request->file('attachment'))),
            'Attachment uploaded',
        );
    }
}
