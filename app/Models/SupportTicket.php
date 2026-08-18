<?php

namespace App\Models;

use App\Enums\SupportCategoryEnum;
use App\Enums\SupportPriorityEnum;
use App\Enums\SupportTicketStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A problem an owner reported, and the conversation about it.
 *
 * Store-owned, with no tenant scope — `store_id` arrives from the request, and
 * `SupportTicketRepository` narrows the listing to the actor's own store unless
 * they hold `stores.view`. Single-record routes are guarded by
 * `SupportTicketPolicy`, because route-model binding never reaches the
 * repository.
 */
class SupportTicket extends Model
{
    protected $fillable = [
        'store_id',
        'user_id',
        'subject',
        'body',
        'category',
        'priority',
        'status',
        'attachment_path',
        'last_replied_at',
        'resolved_at',
    ];

    /**
     * Mirrors the column defaults.
     *
     * Without this the insert takes the database's value but the in-memory
     * instance never learns it, so the object `create()` returns carries a null
     * status and the controller serialises that to the screen. The same trap
     * `StockTransfer` and `Product` document.
     */
    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
        'category' => 'bug',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatusEnum::class,
            'priority' => SupportPriorityEnum::class,
            'category' => SupportCategoryEnum::class,
            'last_replied_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Null once the account is deleted; the ticket stays answerable. */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->oldest();
    }
}
