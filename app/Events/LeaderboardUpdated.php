<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaderboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $leaderboardData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $leaderboardData)
    {
        $this->leaderboardData = $leaderboardData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('leaderboard-updates'),
            new Channel('points-updates'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'leaderboard' => $this->leaderboardData,
            'timestamp' => now()->toISOString(),
            'message' => [
                'en' => 'Leaderboard has been updated!',
                'km' => 'តារាងឈ្នះត្រូវបានធ្វើបច្ចុប្បន្នភាព!',
            ]
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'leaderboard.updated';
    }
}
