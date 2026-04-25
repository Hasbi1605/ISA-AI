<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filename' => $this->faker->uuid() . '.pdf',
            'original_name' => $this->faker->word() . '.pdf',
            'provider_file_id' => null,
            'file_path' => 'documents/' . $this->faker->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => $this->faker->numberBetween(1024, 10485760),
            'status' => 'ready',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
            'provider_file_id' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'processing',
            'provider_file_id' => null,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'ready',
            'provider_file_id' => $this->faker->uuid(),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'error',
            'provider_file_id' => null,
        ]);
    }

    public function withProviderFileId(string $providerFileId): static
    {
        return $this->state(fn(array $attributes) => [
            'provider_file_id' => $providerFileId,
            'status' => 'ready',
        ]);
    }
}