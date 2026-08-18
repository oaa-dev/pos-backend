<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetApprovalPinRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Traits\ApiResponse;

class ApprovalPinController extends Controller
{
    use ApiResponse;

    public function __construct(protected ApprovalService $approvalService) {}

    public function update(SetApprovalPinRequest $request, User $user)
    {
        $this->approvalService->setPin($user, $request->validated('approval_pin'));

        // The PIN is never echoed back, not even to the person who set it.
        return $this->successResponse(null, 'Approval PIN updated successfully');
    }
}
