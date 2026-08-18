<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\ProductData;
use App\Data\ProductImageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductImageRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(protected ProductService $productService) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            ProductResource::collection($this->productService->paginate($request->input('per_page', 25))),
        );
    }

    public function show(Product $product)
    {
        return $this->successResponse(
            new ProductResource($product->load(['category', 'baseUnit', 'units.unit'])),
        );
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->store(ProductData::from($request->validated()));

        return $this->successResponse(new ProductResource($product), 'Product created successfully', 201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->productService->updateProduct($product, ProductData::from($request->validated()));

        return $this->successResponse(new ProductResource($product), 'Product updated successfully');
    }

    public function updateImage(UpdateProductImageRequest $request, Product $product)
    {
        $product = $this->productService->updateImage(
            $product,
            ProductImageData::from(['image' => $request->file('image')]),
        );

        return $this->successResponse(new ProductResource($product), 'Product image updated successfully');
    }

    public function destroyImage(Product $product)
    {
        return $this->successResponse(
            new ProductResource($this->productService->deleteImage($product)),
            'Product image removed successfully',
        );
    }

    /**
     * Scanner lookup. Returns 404 rather than an empty list so the terminal
     * can distinguish "unknown barcode" from "found nothing to sell".
     */
    public function findByBarcode(string $barcode)
    {
        $match = $this->productService->resolveBarcode($barcode);

        if ($match === null) {
            return $this->errorResponse('No product matches that barcode.', 404);
        }

        // The resolved unit travels with the product: a box barcode adds a
        // box. There is no fallback — a barcode lives on a unit, so a found
        // code always names exactly one thing to sell.
        return $this->successResponse([
            'product' => new ProductResource($match['product']),
            'product_unit_id' => $match['product_unit_id'],
        ]);
    }

    /**
     * The POS quick-button grid — yelo, itlog, load, softdrinks, sigarilyo.
     */
    public function favorites()
    {
        return $this->successResponse(
            ProductResource::collection($this->productService->favorites()),
        );
    }
}
