<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave types, as data instead of a hard-coded list.
 *
 * The five types the system shipped with were a PHP constant, a database enum
 * and a TypeScript union all at once, so adding one meant a deployment and
 * removing one was not possible at all. A company that does not use "special"
 * leave had no way to take it off the form.
 *
 * They are seeded here rather than in a seeder on purpose: the request form
 * cannot render without at least one type, so no environment — including a
 * fresh test database — may ever exist without them.
 *
 * `deducts_from_allowance` is the only behaviour the type carries. It is what
 * made `annual` special: its approved days come off the employee's yearly
 * balance, while every other type is counted but costs nothing. Now that it is
 * a column, a company can have two types that both draw on the balance, or
 * none at all, without a code change.
 */
return new class extends Migration
{
    /**
     * The types the system already had, with the names the interface was
     * showing for them. `annual` carries the flag because it was the only one
     * the old balance calculation deducted.
     */
    private const DEFAULTS = [
        ['key' => 'annual', 'name_ar' => 'سنوية', 'name_en' => 'Annual', 'deducts_from_allowance' => true, 'sort_order' => 1],
        ['key' => 'sick', 'name_ar' => 'مرضية', 'name_en' => 'Sick', 'deducts_from_allowance' => false, 'sort_order' => 2],
        ['key' => 'unpaid', 'name_ar' => 'بلا أجر', 'name_en' => 'Unpaid', 'deducts_from_allowance' => false, 'sort_order' => 3],
        ['key' => 'emergency', 'name_ar' => 'طارئة', 'name_en' => 'Emergency', 'deducts_from_allowance' => false, 'sort_order' => 4],
        ['key' => 'special', 'name_ar' => 'خاصة', 'name_en' => 'Special', 'deducts_from_allowance' => false, 'sort_order' => 5],
    ];

    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            // What `employee_leaves.leave_type` stores. Immutable once created:
            // renaming it would orphan every request already filed under it.
            $table->string('key', 40)->unique();
            $table->string('name_ar', 60);
            $table->string('name_en', 60);
            // Switched off rather than deleted: a type that has been used
            // cannot be removed without rewriting history, but it can stop
            // appearing on the form.
            $table->boolean('is_active')->default(true);
            $table->boolean('deducts_from_allowance')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('leave_types')->insert(array_map(
            fn (array $row): array => $row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::DEFAULTS
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
