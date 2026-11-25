<?php

namespace Database\Factories;

use App\Enums\RequestItemStatus;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestItem>
 */
class RequestItemFactory extends Factory
{
    /**
     * Define the model's default state: a slot still waiting for the unit's upload.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_request_id' => DocumentRequest::factory(),
            'sort_order' => fake()->unique()->numberBetween(1, 60000),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => null,
            'accepted_extensions' => ['pdf'],
            'status' => RequestItemStatus::Menunggu,
        ];
    }

    public function withStatus(RequestItemStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
