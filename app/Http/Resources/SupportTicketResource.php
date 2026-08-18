<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'subject' => $this->subject,
            'body' => $this->body,
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'attachment_url' => $this->attachment_path
                ? asset('storage/'.$this->attachment_path)
                : null,
            'last_replied_at' => $this->last_replied_at?->format('Y-m-d h:i:s a'),
            'resolved_at' => $this->resolved_at?->format('Y-m-d h:i:s a'),
            'replies_count' => $this->whenCounted('replies'),
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
                'email' => $this->reporter?->email,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ]),
            'replies' => $this->whenLoaded('replies', fn () => SupportTicketReplyResource::collection($this->replies)),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
