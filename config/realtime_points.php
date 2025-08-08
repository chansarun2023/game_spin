<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Real-time Points Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for real-time points functionality
    |
    */

    // Broadcasting settings
    'broadcasting' => [
        'driver' => env('BROADCAST_DRIVER', 'null'),
        'channels' => [
            'points_updates' => 'points-updates',
            'leaderboard_updates' => 'leaderboard-updates',
            'user_updates' => 'user.{id}',
        ],
    ],

    // Cache settings
    'cache' => [
        'leaderboard_ttl' => 60, // seconds
        'stats_ttl' => 30, // seconds
        'user_status_ttl' => 15, // seconds
    ],

    // Leaderboard settings
    'leaderboard' => [
        'default_limit' => 10,
        'max_limit' => 50,
        'timeframes' => ['all', 'today', 'week', 'month'],
        'update_threshold' => 5, // Update leaderboard every N points earned
    ],

    // Notification settings
    'notifications' => [
        'enabled' => true,
        'browser_notifications' => true,
        'in_app_notifications' => true,
        'notification_duration' => 5000, // milliseconds
    ],

    // Points calculation settings
    'points' => [
        'auto_calculate' => true,
        'cache_calculations' => true,
        'recalculate_interval' => 300, // seconds
    ],

    // Real-time update settings
    'updates' => [
        'polling_fallback' => true,
        'polling_interval' => 5000, // milliseconds
        'max_retries' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    // Security settings
    'security' => [
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 60,
            'window' => 60, // seconds
        ],
        'private_channels' => true,
        'authenticate_private_channels' => true,
    ],

    // Performance settings
    'performance' => [
        'batch_updates' => true,
        'batch_size' => 10,
        'batch_delay' => 100, // milliseconds
        'optimize_queries' => true,
    ],

    // Debug settings
    'debug' => [
        'log_events' => env('REALTIME_POINTS_DEBUG', false),
        'log_level' => 'info',
        'show_errors' => env('APP_DEBUG', false),
    ],

    // Localization settings
    'localization' => [
        'default_language' => 'en',
        'supported_languages' => ['en', 'km'],
        'messages' => [
            'en' => [
                'points_earned' => 'Congratulations! You earned {points} points!',
                'leaderboard_updated' => 'Leaderboard has been updated!',
                'connection_established' => 'Real-time connection established',
                'connection_error' => 'Connection error occurred',
            ],
            'km' => [
                'points_earned' => 'សូមអបអរសាទរ! អ្នកបានរកពិន្ទុ {points}!',
                'leaderboard_updated' => 'តារាងឈ្នះត្រូវបានធ្វើបច្ចុប្បន្នភាព!',
                'connection_established' => 'ការតភ្ជាប់ពេលវេលាពិតត្រូវបានបង្កើត',
                'connection_error' => 'កំហុសការតភ្ជាប់បានកើតឡើង',
            ],
        ],
    ],

];
