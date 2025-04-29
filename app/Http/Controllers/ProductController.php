<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class ProductController extends Controller
{
    use ApiResponseTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $order = $request->get('order', 'asc');
        $field = $request->get('field', 'id');
        $search = $request->get('search', '');

        $query = Product::query()->where('user_id', Auth::id());

        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $filtered = $query->count();

        $products = $query->orderBy($field, $order)
            ->skip($start)
            ->take($length)
            ->get();

        $total = Product::where('user_id', Auth::id())->count();

        return $this->success([
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $products,
        ], 'Products retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return $this->error('Unauthorized user', 403);
        }

        if ($user->isPremium()  && $user->products()->count() >= 100) {
            return $this->error('Maximum 100 products allowed for premium user', 400);
        } elseif (!$user->isPremium()  && $user->products()->count() >= 10) {
            return $this->error('Maximum 10 products allowed for free user', 400);
        }

        if (isset($validated['image'])) {
            $uploadPath = public_path('storage/products');

            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }

            $imageName = time() . '.' . $validated['image']->extension();

            try {
                $validated['image']->move($uploadPath, $imageName);
            } catch (\Exception $e) {
                return $this->error($e->getMessage());
            }

            $host = $request->getSchemeAndHttpHost();
            $validated['product_image'] = $host . '/storage/products/' . $imageName;
        }

        $validated['user_id'] = $user->id;

        return $this->success(Product::create($validated), 'Product created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::find($id);
        if ($product->user_id != Auth::id()) {
            return $this->error('Unauthorized product', 403);
        }
        return $this->success($product, 'Product retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
        ]);
        $product = Product::find($id);
        if ($product->user_id != Auth::id()) {
            return $this->error('Unauthorized product', 403);
        }

        $product->update($validated);
        return $this->success($product, 'Product updated successfully');
    }

    public function updateImage(Request $request, string $id)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $product = Product::find($id);
        if ($product->user_id != Auth::id()) {
            return $this->error('Unauthorized product', 403);
        }

        if ($product->product_image) {
            $oldImagePath = public_path(parse_url($product->product_image, PHP_URL_PATH));
            if (File::exists($oldImagePath)) {
                File::delete($oldImagePath);
            }
        }

        $uploadPath = public_path('storage/products');
        $imageName = time() . '.' . $validated['image']->extension();
        try {
            $validated['image']->move($uploadPath, $imageName);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }

        $host = $request->getSchemeAndHttpHost();
        $product->product_image = $host . '/storage/products/' . $imageName;
        $product->save();

        return $this->success($product, 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);
        if ($product->user_id != Auth::id()) {
            return $this->error('Unauthorized product', 403);
        }
        $product->delete();
        return $this->success(null, 'Product deleted successfully');
    }
}
