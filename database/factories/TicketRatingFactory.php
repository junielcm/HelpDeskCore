<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRating>
 */
class TicketRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'feedback' => fake()->optional()->sentence(),
        ];
    }
}
