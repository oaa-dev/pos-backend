<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreExpense extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'branch_id',
        'expense_category_id',
        'amount',
        'description',
        'paid_from',
        'cash_drawer_session_id',
        'supplier_id',
        'receipt_path',
        'incurred_at',
        'recorded_by',
        'approved_by',
    ];

    protected $attributes = ['paid_from' => 'drawer'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_at' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashDrawerSession::class, 'cash_drawer_session_id');
    }

    /** Only drawer-paid expenses affect the shift count. */
    public function affectsDrawer(): bool
    {
        return $this->paid_from === 'drawer';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_by');
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'branch_id',
            'expense_category_id',
            'amount',
            'description',
            'paid_from',
            'approved_by',
        ];
    }

    /**
     * Through the branch.
     */
    public function activityStoreId(): ?int
    {
        return $this->loadMissing('branch')->branch?->store_id;
    }
}
