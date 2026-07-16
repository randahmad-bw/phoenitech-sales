<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'attachable_type' => Contract::class,
            'attachable_id' => Contract::factory(),
            'original_name' => 'document.pdf',
            'stored_name' => $this->faker->uuid . '.pdf',
            'disk' => 'public',
            'path' => 'attachments/document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024 * 1024,
        ];
    }
}
