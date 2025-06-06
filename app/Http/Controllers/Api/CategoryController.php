<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Auth::user()->categories()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
        ]);

        return Auth::user()->categories()->create($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): Category
    {
        $this->authorizeAccess($category);
        return $category;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): Category
    {
        $this->authorizeAccess($category);

        $data = $request->validate([
            'name' => 'string|max:255',
            'type' => 'in:income,expense',
        ]);

        $category->update($data);
        return $category;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): \Illuminate\Http\Response
    {
        $this->authorizeAccess($category);
        $category->delete();
        return response()->noContent();
    }

    private function authorizeAccess(Category $category): void
    {
        if ($category->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }
}
