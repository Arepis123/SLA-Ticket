<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketEnvironment;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'reporter_id' => fn (array $attributes) => User::factory()->client(
                Project::find($attributes['project_id'])->company,
            ),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'steps_to_reproduce' => "1. Log in\n2. Open the page\n3. Click the button",
            'environment' => TicketEnvironment::Production,
            'category' => fake()->randomElement(TicketCategory::cases()),
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::Open,
        ];
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn (array $attributes) => ['priority' => $priority]);
    }
}
