<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * List all products with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($products);
    }

    /**
     * Store a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['boolean'],
            'is_homepage_featured' => ['nullable', 'boolean'],
            'switch_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'switch_type' => ['nullable', 'string', Rule::in(Product::SWITCH_TYPES)],
            'switch_sounds' => ['nullable', 'array', 'max:3'],
            'switch_sounds.*' => ['file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/aac', 'max:10240'],
            'keycap_texture_uv' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'keyboard_texture_uv' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Check slug uniqueness before any storage side effects.
        if (Product::where('slug', $validated['slug'])->exists()) {
            return response()->json([
                'message' => 'A product with a similar name already exists.',
                'errors' => ['name' => ['A product with a similar name already exists.']],
            ], 422);
        }

        $categoryId = (int) $validated['category_id'];
        $categorySlug = $this->resolveCategorySlug($categoryId);
        $newlyStoredPaths = [];
        $pathsToDeleteAfterSave = [];
        $switchSoundPathsForValidation = $this->resolveSwitchSoundValidationPaths(
            $request,
            $categorySlug,
            [],
        );

        $validated['is_homepage_featured'] = $request->boolean('is_homepage_featured');
        $validated['switch_color'] = $this->normalizeSwitchColor(
            $categorySlug,
            $validated['switch_color'] ?? null,
        );
        $validated['switch_type'] = $this->normalizeSwitchType(
            $categorySlug,
            $validated['switch_type'] ?? null,
        );
        $this->validateSwitchRequirements(
            $categorySlug,
            $validated['switch_color'],
            $validated['switch_type'],
            $switchSoundPathsForValidation,
        );
        $this->validateHomepageFeature(
            $categorySlug,
            $validated['is_homepage_featured'],
            $validated['switch_color'],
            $validated['switch_type'],
            $switchSoundPathsForValidation,
        );

        $validated['keycap_texture_uv'] = $this->resolveTextureUpload(
            $request,
            'keycap_texture_uv',
            null,
            'products/keycap-textures',
            $this->isKeycapCategory($categorySlug),
            $newlyStoredPaths,
            $pathsToDeleteAfterSave,
        );
        $validated['keyboard_texture_uv'] = $this->resolveTextureUpload(
            $request,
            'keyboard_texture_uv',
            null,
            'products/keyboard-textures',
            $this->isKeyboardCategory($categorySlug),
            $newlyStoredPaths,
            $pathsToDeleteAfterSave,
        );

        $validated['switch_sound_paths'] = $this->resolveSwitchSoundPaths(
            $request,
            $categorySlug,
            [],
            $newlyStoredPaths,
            $pathsToDeleteAfterSave,
        );

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
            $newlyStoredPaths[] = $validated['image'];
        }

        if (empty($validated['switch_sound_paths'])) {
            $validated['switch_sound_paths'] = null;
        }

        try {
            $product = Product::create($validated);
        } catch (\Throwable $exception) {
            $this->deleteStoredFiles($newlyStoredPaths);
            throw $exception;
        }

        $product->load('category');

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    /**
     * Show a single product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load('category');

        return response()->json($product);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['boolean'],
            'is_homepage_featured' => ['nullable', 'boolean'],
            'switch_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'switch_type' => ['nullable', 'string', Rule::in(Product::SWITCH_TYPES)],
            'switch_sounds' => ['nullable', 'array', 'max:3'],
            'switch_sounds.*' => ['file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/aac', 'max:10240'],
            'keycap_texture_uv' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'keyboard_texture_uv' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $newSlug = Str::slug($validated['name']);

        if (Product::where('slug', $newSlug)->where('id', '!=', $product->id)->exists()) {
            return response()->json([
                'message' => 'A product with a similar name already exists.',
                'errors' => ['name' => ['A product with a similar name already exists.']],
            ], 422);
        }

        $categoryId = (int) $validated['category_id'];
        $categorySlug = $this->resolveCategorySlug($categoryId);
        $newlyStoredPaths = [];
        $pathsToDeleteAfterSave = [];
        $existingSwitchSoundPaths = $this->normalizeSoundPathArray($product->switch_sound_paths);
        $switchSoundPathsForValidation = $this->resolveSwitchSoundValidationPaths(
            $request,
            $categorySlug,
            $existingSwitchSoundPaths,
        );
        $validated['is_homepage_featured'] = $request->boolean('is_homepage_featured');
        $validated['switch_color'] = $this->normalizeSwitchColor(
            $categorySlug,
            $validated['switch_color'] ?? $product->switch_color,
        );
        $validated['switch_type'] = $this->normalizeSwitchType(
            $categorySlug,
            $validated['switch_type'] ?? $product->switch_type,
        );
        $this->validateSwitchRequirements(
            $categorySlug,
            $validated['switch_color'],
            $validated['switch_type'],
            $switchSoundPathsForValidation,
        );
        $this->validateHomepageFeature(
            $categorySlug,
            $validated['is_homepage_featured'],
            $validated['switch_color'],
            $validated['switch_type'],
            $switchSoundPathsForValidation,
            $product->id,
        );

        $validated['keycap_texture_uv'] = $this->resolveTextureUpload(
            $request,
            'keycap_texture_uv',
            $product->keycap_texture_uv,
            'products/keycap-textures',
            $this->isKeycapCategory($categorySlug),
            $newlyStoredPaths,
            $pathsToDeleteAfterSave,
        );
        $validated['keyboard_texture_uv'] = $this->resolveTextureUpload(
            $request,
            'keyboard_texture_uv',
            $product->keyboard_texture_uv,
            'products/keyboard-textures',
            $this->isKeyboardCategory($categorySlug),
            $newlyStoredPaths,
            $pathsToDeleteAfterSave,
        );

        $validated['switch_sound_paths'] = $this->resolveSwitchSoundPaths(
            $request,
            $categorySlug,
            $existingSwitchSoundPaths,
            $newlyStoredPaths,
            $pathsToDeleteAfterSave,
        );

        $validated['slug'] = $newSlug;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
            $newlyStoredPaths[] = $validated['image'];
            if ($product->image) {
                $pathsToDeleteAfterSave[] = $product->image;
            }
        }

        if (empty($validated['switch_sound_paths'])) {
            $validated['switch_sound_paths'] = null;
        }

        try {
            $product->update($validated);
        } catch (\Throwable $exception) {
            $this->deleteStoredFiles($newlyStoredPaths);
            throw $exception;
        }

        $this->deleteStoredFiles($this->uniquePaths($pathsToDeleteAfterSave));
        $product->load('category');

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('category'),
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check if product has order items
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product that has been ordered.',
            ], 422);
        }

        $this->deleteStoredFile($product->image);
        $this->deleteStoredFile($product->keycap_texture_uv);
        $this->deleteStoredFile($product->keyboard_texture_uv);
        $this->deleteStoredFiles($this->normalizeSoundPathArray($product->switch_sound_paths));

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Featured homepage slots are reserved for up to 4 switch products.
     */
    protected function validateHomepageFeature(
        ?string $categorySlug,
        bool $isHomepageFeatured,
        ?string $switchColor,
        ?string $switchType,
        array $switchSoundPaths,
        ?int $ignoreProductId = null,
    ): void
    {
        $isSwitchCategory = $this->isSwitchCategory($categorySlug);

        if (!$isSwitchCategory) {
            if (!$isHomepageFeatured) {
                return;
            }

            throw ValidationException::withMessages([
                'is_homepage_featured' => ['Only switch products can appear in Craft Your Click.'],
            ]);
        }

        if (
            $isHomepageFeatured
            && (!$switchColor || !$switchType || count($switchSoundPaths) === 0)
        ) {
            throw ValidationException::withMessages([
                'is_homepage_featured' => ['Featured switch products require color, type, and at least one sound file.'],
            ]);
        }

        if (!$isHomepageFeatured) {
            return;
        }

        $featuredCount = Product::query()
            ->where('is_homepage_featured', true)
            ->whereHas('category', function ($query) {
                $query->where('slug', 'switches');
            })
            ->when($ignoreProductId, function ($query, $ignoreProductId) {
                $query->where('id', '!=', $ignoreProductId);
            })
            ->count();

        if ($featuredCount >= 4) {
            throw ValidationException::withMessages([
                'is_homepage_featured' => ['Only 4 switch products can be featured on the homepage at a time.'],
            ]);
        }
    }

    protected function validateSwitchRequirements(
        ?string $categorySlug,
        ?string $switchColor,
        ?string $switchType,
        array $switchSoundPaths,
    ): void
    {
        if (!$this->isSwitchCategory($categorySlug)) {
            return;
        }

        $errors = [];

        if (!$switchColor) {
            $errors['switch_color'] = ['Switch color is required for switch products.'];
        }

        if (!$switchType) {
            $errors['switch_type'] = ['Switch type is required for switch products.'];
        }

        if (count($switchSoundPaths) === 0) {
            $errors['switch_sounds'] = ['Upload at least one switch sound file.'];
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function normalizeSwitchColor(?string $categorySlug, ?string $switchColor): ?string
    {
        if (!$this->isSwitchCategory($categorySlug)) {
            return null;
        }

        $normalized = strtoupper(trim((string) $switchColor));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeSwitchType(?string $categorySlug, ?string $switchType): ?string
    {
        if (!$this->isSwitchCategory($categorySlug)) {
            return null;
        }

        $normalized = strtolower(trim((string) $switchType));

        return $normalized !== '' ? $normalized : null;
    }

    protected function resolveSwitchSoundPaths(
        Request $request,
        ?string $categorySlug,
        array $existingPaths,
        array &$newlyStoredPaths,
        array &$pathsToDeleteAfterSave,
    ): array {
        if (!$this->isSwitchCategory($categorySlug)) {
            $pathsToDeleteAfterSave = array_merge($pathsToDeleteAfterSave, $existingPaths);
            return [];
        }

        $uploadedFiles = $request->file('switch_sounds');

        if (!$uploadedFiles) {
            return $existingPaths;
        }

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        $uploadedFiles = array_slice($uploadedFiles, 0, 3);

        $storedPaths = [];
        foreach ($uploadedFiles as $uploadedFile) {
            if (!$uploadedFile) {
                continue;
            }

            $path = $uploadedFile->store('products/switch-sounds', 'public');
            $storedPaths[] = $path;
            $newlyStoredPaths[] = $path;
        }

        $pathsToDeleteAfterSave = array_merge($pathsToDeleteAfterSave, $existingPaths);

        return $storedPaths;
    }

    protected function resolveSwitchSoundValidationPaths(
        Request $request,
        ?string $categorySlug,
        array $existingPaths,
    ): array {
        if (!$this->isSwitchCategory($categorySlug)) {
            return [];
        }

        $uploadedFiles = $request->file('switch_sounds');

        if (!$uploadedFiles) {
            return $existingPaths;
        }

        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        $uploadedFiles = array_values(array_filter(array_slice($uploadedFiles, 0, 3)));

        return array_map(function ($uploadedFile): string {
            return (string) $uploadedFile->getClientOriginalName();
        }, $uploadedFiles);
    }

    protected function resolveTextureUpload(
        Request $request,
        string $field,
        ?string $currentPath,
        string $storagePath,
        bool $isMatchingCategory,
        array &$newlyStoredPaths,
        array &$pathsToDeleteAfterSave,
    ): ?string {
        if (!$isMatchingCategory) {
            if ($currentPath) {
                $pathsToDeleteAfterSave[] = $currentPath;
            }
            return null;
        }

        if (!$request->hasFile($field)) {
            return $currentPath;
        }

        if ($currentPath) {
            $pathsToDeleteAfterSave[] = $currentPath;
        }

        $storedPath = $request->file($field)->store($storagePath, 'public');
        $newlyStoredPaths[] = $storedPath;

        return $storedPath;
    }

    protected function normalizeSoundPathArray(mixed $soundPaths): array
    {
        if (!is_array($soundPaths)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($path): string {
            return is_string($path) ? trim($path) : '';
        }, $soundPaths)));
    }

    protected function deleteStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteStoredFile($path);
        }
    }

    protected function uniquePaths(array $paths): array
    {
        return array_values(array_unique(array_filter($paths, function ($path): bool {
            return is_string($path) && trim($path) !== '';
        })));
    }

    protected function deleteStoredFile(?string $path): void
    {
        if (!$this->isStorageManagedPath($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    protected function isStorageManagedPath(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return !Str::startsWith($path, ['http://', 'https://', '/']);
    }

    protected function resolveCategorySlug(int $categoryId): ?string
    {
        return Category::query()->whereKey($categoryId)->value('slug');
    }

    protected function isSwitchCategory(?string $categorySlug): bool
    {
        return $categorySlug === 'switches';
    }

    protected function isKeycapCategory(?string $categorySlug): bool
    {
        return $categorySlug === 'keycaps';
    }

    protected function isKeyboardCategory(?string $categorySlug): bool
    {
        return $categorySlug === 'keyboards';
    }
}
