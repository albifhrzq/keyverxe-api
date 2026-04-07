<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_homepage_featured')->default(false)->after('is_active');
        });

        $switchCategoryId = DB::table('categories')
            ->where('slug', 'switches')
            ->value('id');

        if (!$switchCategoryId) {
            return;
        }

        $productIds = DB::table('products')
            ->where('category_id', $switchCategoryId)
            ->orderBy('id')
            ->limit(4)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return;
        }

        DB::table('products')
            ->whereIn('id', $productIds)
            ->update([
                'is_homepage_featured' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_homepage_featured');
        });
    }
};
