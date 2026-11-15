<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Message;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'message_id' => null,
            'original_name' => 'document.pdf',
            'storage_path' => 'attachments/test/'.Str::uuid()->toString().'.pdf',
            'disk' => 'local',
            'uploaded_by_id' => null,
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', 'test'),
            'attachment_type' => 'file',
            'provider_media_id' => null,
            'metadata' => null,
        ];
    }

    public function forMessage(Message $message): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $message->organization_id,
            'message_id' => $message->id,
        ]);
    }
}
