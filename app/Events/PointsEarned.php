<?php

namespace App\Events;

use App\Models\User;
use App\Models\Result;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointsEarned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $result;
    public $pointsEarned;
    public $newTotalPoints;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Result $result, int $pointsEarned, int $newTotalPoints)
    {
        $this->user = $user;
        $this->result = $result;
        $this->pointsEarned = $pointsEarned;
        $this->newTotalPoints = $newTotalPoints;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
            new Channel('points-updates'),
            new Channel('leaderboard-updates'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'points_earned' => $this->pointsEarned,
            'new_total_points' => $this->newTotalPoints,
            'result_id' => $this->result->id,
            'result_english' => $this->result->result_english,
            'result_khmer' => $this->result->result_khmer,
            'timestamp' => now()->toISOString(),
            'message' => [
                'en' => "Congratulations! You earned {$this->pointsEarned} points!",
                'km' => "សូមអបអរសាទរ! អ្នកបានរកពិន្ទុ {$this->pointsEarned}!",
            ]
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'points.earned';
    }
}
