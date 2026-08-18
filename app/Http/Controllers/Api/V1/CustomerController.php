<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\CustomerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(protected CustomerService $customerService) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            CustomerResource::collection($this->customerService->paginate($request->input('per_page', 25))),
        );
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return $this->successResponse(new CustomerResource($customer->load('branch')));
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = $this->customerService->store(CustomerData::from($request->validated()));

        return $this->successResponse(new CustomerResource($customer), 'Customer created successfully', 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer = $this->customerService->updateCustomer($customer, CustomerData::from($request->validated()));

        return $this->successResponse(new CustomerResource($customer), 'Customer updated successfully');
    }
}
