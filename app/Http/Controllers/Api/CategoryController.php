<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories (public).
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        return response()->json($categories);
    }
}
