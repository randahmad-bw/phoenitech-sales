<?php

use App\Models\Attachment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs attachments that were stored under a client alias.
 *
 * The upload endpoint wrote `attachable_type = 'contract'` while
 * `$contract->attachments()` — like every Eloquent morph relation — looks for
 * the model class. Every file uploaded from the contract screen was therefore
 * saved correctly and then never shown again. This rewrites the alias to the
 * class so those files reappear; new uploads already store the class.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Attachment::ALIASES as $alias => $class) {
            DB::table('attachments')->where('attachable_type', $alias)->update(['attachable_type' => $class]);
        }
    }

    public function down(): void
    {
        foreach (Attachment::ALIASES as $alias => $class) {
            DB::table('attachments')->where('attachable_type', $class)->update(['attachable_type' => $alias]);
        }
    }
};
