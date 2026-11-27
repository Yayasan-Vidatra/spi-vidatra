<?php

namespace Database\Factories;

use App\Enums\EscalationStatus;
use App\Enums\EscalationType;
use App\Models\Escalation;
use App\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Escalation>
 */
class EscalationFactory extends Factory
{
    /**
     * Define the model's default state: a forwarded escalation of a document request slot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => EscalationType::PermintaanDokumen,
            'subject_type' => (new RequestItem)->getMorphClass(),
            'subject_id' => RequestItem::factory(),
            'charter_deadline' => now()->subDay()->toDateString(),
            'escalated_at' => now(),
            'status' => EscalationStatus::Diteruskan,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EscalationStatus::Selesai,
            'resolved_at' => now(),
        ]);
    }
}
