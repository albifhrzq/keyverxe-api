<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_PROFILE_MAP = [
        'red' => [
            'color' => '#EF4444',
            'type' => 'linear',
            'sounds' => ['/sounds/red-1.mp3', '/sounds/red-2.mp3', '/sounds/red-3.mp3'],
        ],
        'brown' => [
            'color' => '#C08457',
            'type' => 'tactile',
            'sounds' => ['/sounds/brown-1.mp3', '/sounds/brown-2.mp3', '/sounds/brown-3.mp3'],
        ],
        'blue' => [
            'color' => '#38BDF8',
            'type' => 'clicky',
            'sounds' => ['/sounds/blue-1.mp3', '/sounds/blue-2.mp3', '/sounds/blue-3.mp3'],
        ],
        'black' => [
            'color' => '#94A3B8',
            'type' => 'silent',
            'sounds' => ['/sounds/black-1.mp3', '/sounds/black-2.mp3', '/sounds/black-3.mp3'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('switch_color', 7)->nullable()->after('is_homepage_featured');
            $table->string('switch_type')->nullable()->after('switch_color');
            $table->json('switch_sound_paths')->nullable()->after('switch_type');
            $table->string('keyboard_texture_uv')->nullable()->after('keycap_texture_uv');
        });

        if (Schema::hasColumn('products', 'switch_asset_profile')) {
            DB::table('products')
                ->select('id', 'switch_asset_profile')
                ->whereNotNull('switch_asset_profile')
                ->orderBy('id')
                ->chunkById(100, function ($products): void {
                    foreach ($products as $product) {
                        $legacyProfile = (string) $product->switch_asset_profile;
                        $mapped = self::LEGACY_PROFILE_MAP[$legacyProfile] ?? null;

                        if (!$mapped) {
                            continue;
                        }

                        DB::table('products')
                            ->where('id', $product->id)
                            ->update([
                                'switch_color' => $mapped['color'],
                                'switch_type' => $mapped['type'],
                                'switch_sound_paths' => json_encode($mapped['sounds']),
                            ]);
                    }
                });

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('switch_asset_profile');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('products', 'switch_asset_profile')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('switch_asset_profile')->nullable()->after('is_homepage_featured');
            });

            DB::table('products')
                ->select('id', 'switch_type')
                ->whereNotNull('switch_type')
                ->orderBy('id')
                ->chunkById(100, function ($products): void {
                    foreach ($products as $product) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update([
                                'switch_asset_profile' => $this->profileFromType($product->switch_type),
                            ]);
                    }
                });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'switch_color',
                'switch_type',
                'switch_sound_paths',
                'keyboard_texture_uv',
            ]);
        });
    }

    private function profileFromType(?string $switchType): ?string
    {
        return match ($switchType) {
            'linear' => 'red',
            'tactile' => 'brown',
            'clicky' => 'blue',
            'silent' => 'black',
            default => null,
        };
    }
};
