<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CollectCreditRequest;
use App\Http\Requests\WriteOffCreditRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Services\ApprovalService;
use App\Services\CashDrawerService;
use App\Services\CreditService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreditService $credit,
        protected CashDrawerService $drawer,
        protected ApprovalService $approvalService,
        protected CreditTransactionRepositoryInterface $transactions,
    ) {}

    /** The ledger, filterable — every charge, payment, writeoff. */
    public function index(Request $request)
    {
        return $this->paginatedResponse(
            CreditTransactionResource::collection(
                $this->transactions->paginate($request->input('per_page', 25)),
            ),
        );
    }

    /**
     * Everything needed to print or share a suki's statement: the running
     * ledger, the aging breakdown, and what is overdue.
     */
    public function statement(Customer $customer)
    {
        $this->authorize('view', $customer);

        return $this->successResponse([
            'customer' => new CustomerResource($customer),
            'aging' => $this->credit->aging($customer),
            'overdue' => CreditTransactionResource::collection($this->credit->overdue($customer)),
            'transactions' => CreditTransactionResource::collection(
                $this->transactions->statementFor($customer->id),
            ),
        ]);
    }

    public function collect(CollectCreditRequest $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        // Tie the payment to the open shift when there is one, so the cash
        // reconciles at close. Without a shift it still records.
        $session = null;

        if ($request->validated('branch_id')) {
            $session = $this->drawer->findOpenForBranch((int) $request->validated('branch_id'));
        }

        $transaction = $this->credit->collect(
            customer: $customer,
            amount: $request->validated('amount'),
            session: $session,
            note: $request->validated('note'),
        );

        return $this->successResponse(
            new CreditTransactionResource($transaction->load('customer')),
            'Payment recorded successfully',
            201,
        );
    }

    public function writeOff(WriteOffCreditRequest $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $approver = $this->approvalService->resolveApprover(
            $request->validated('approval_pin'),
            'credit.writeoff',
            $request->ip(),
        );

        $transaction = $this->credit->writeOff(
            customer: $customer,
            amount: $request->validated('amount'),
            approvedBy: $approver->id,
            note: $request->validated('note'),
        );

        return $this->successResponse(
            new CreditTransactionResource($transaction->load('customer')),
            'Balance written off successfully',
            201,
        );
    }
}
