<?php

namespace App\Services;

use App\Enums\SupportTicketStatusEnum;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Repositories\Contracts\SupportTicketRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\Optional;

class SupportTicketService extends BaseService
{
    public function __construct(
        protected readonly SupportTicketRepositoryInterface $tickets,
    ) {
        parent::__construct($tickets);
    }

    public function create(array $values): SupportTicket
    {
        return $this->tickets->create([
            ...$values,
            'user_id' => Auth::id(),
        ])->load(['reporter', 'store']);
    }

    /**
     * A reply from either side.
     *
     * Answering an open ticket moves it to `in_progress` — the operator has
     * picked it up, and leaving it `open` would make the queue meaningless. A
     * ticket already resolved is not reopened by a further reply; that is a
     * deliberate non-decision, recorded in the plan's open questions.
     */
    public function reply(SupportTicket $ticket, string $body): SupportTicketReply
    {
        return DB::transaction(function () use ($ticket, $body) {
            $reply = $ticket->replies()->create([
                'user_id' => Auth::id(),
                'body' => $body,
            ]);

            $ticket->forceFill(['last_replied_at' => now()]);

            if ($ticket->status === SupportTicketStatusEnum::OPEN) {
                $ticket->forceFill(['status' => SupportTicketStatusEnum::IN_PROGRESS]);
            }

            $ticket->save();

            return $reply->load('author');
        });
    }

    /**
     * Named `updateStatus`, not `update`: `BaseService::update(Model, array)`
     * is inherited, and a narrowed signature is an incompatible override that
     * fatals at load time. The same trap `BaseService::paginate()` documents.
     *
     * `resolved_at` tracks the status rather than being set independently, so
     * reopening a ticket clears it instead of leaving a resolution date on
     * something still being worked.
     */
    public function updateStatus(SupportTicket $ticket, array $values): SupportTicket
    {
        $changes = array_filter(
            $values,
            fn ($value) => ! $value instanceof Optional,
        );

        if (isset($changes['status'])) {
            $status = $changes['status'] instanceof SupportTicketStatusEnum
                ? $changes['status']
                : SupportTicketStatusEnum::from($changes['status']);

            $changes['resolved_at'] = $status === SupportTicketStatusEnum::RESOLVED ? now() : null;
        }

        return $this->tickets->update($ticket, $changes)->load(['reporter', 'store']);
    }

    /** Copies ProductService's shape: store the path, delete the previous one. */
    public function attach(SupportTicket $ticket, $file): SupportTicket
    {
        $previous = $ticket->attachment_path;
        $path = $file->store('support', 'public');

        $ticket = $this->tickets->update($ticket, ['attachment_path' => $path]);

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return $ticket;
    }
}
