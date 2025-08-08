# Real-time Points System Setup Guide

# មគ្គុទ្ទេសក៍កំណត់រចនាសម្ព័ន្ធពិន្ទុពេលវេលាពិត

## English / អង់គ្លេស

This guide will help you set up real-time points functionality for your spin wheel game.

## ខ្មែរ

មគ្គុទ្ទេសក៍នេះនឹងជួយអ្នកកំណត់រចនាសម្ព័ន្ធពិន្ទុពេលវេលាពិតសម្រាប់ហ្គេមរបង់របស់អ្នក។

## Features / មុខងារ

-   ✅ **Real-time points updates** - Instant point notifications
-   ✅ **Live leaderboards** - Real-time ranking updates
-   ✅ **Multi-language support** - English and Khmer
-   ✅ **WebSocket integration** - Pusher support with polling fallback
-   ✅ **Caching system** - Optimized performance
-   ✅ **Security features** - Rate limiting and authentication
-   ✅ **Browser notifications** - Desktop notifications
-   ✅ **In-app notifications** - Beautiful UI notifications

## Installation / ការដំឡើង

### 1. Environment Configuration / ការកំណត់រចនាសម្ព័ន្ធបរិស្ថាន

Add these variables to your `.env` file:

```env
# Broadcasting Configuration
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=your_pusher_cluster

# Real-time Points Configuration
REALTIME_POINTS_DEBUG=true
```

### 2. Install Dependencies / ដំឡើងកញ្ចប់ដែលត្រូវការ

```bash
# Install Pusher PHP SDK
composer require pusher/pusher-php-server

# Install Laravel Echo (for frontend)
npm install laravel-echo pusher-js
```

### 3. Configure Broadcasting / កំណត់រចនាសម្ព័ន្ធការផ្សព្វផ្សាយ

Update `config/broadcasting.php`:

```php
'default' => env('BROADCAST_DRIVER', 'pusher'),

'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'encrypted' => true,
        ],
    ],
],
```

### 4. Update AppServiceProvider / ធ្វើបច្ចុប្បន្នភាព AppServiceProvider

Add to `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Broadcast;

public function boot()
{
    Broadcast::routes();
}
```

## API Endpoints / ចំណុចបញ្ចប់ API

### Real-time Data / ទិន្នន័យពេលវេលាពិត

```
GET /api/v1/realtime/data
GET /api/v1/realtime/leaderboard
GET /api/v1/realtime/stats
GET /api/v1/realtime/broadcasting-info
```

### User-specific / ជាក់លាក់អ្នកប្រើប្រាស់

```
GET /api/v1/realtime/user-points (requires auth)
POST /api/v1/realtime/force-update-leaderboard (admin only)
```

### Query Parameters / ប៉ារ៉ាម៉ែត្រសំណួរ

-   `timeframe`: `all`, `today`, `week`, `month`
-   `limit`: Number of leaderboard entries (max 50)

## Frontend Integration / ការរួមបញ្ចូល Frontend

### 1. Include JavaScript Client / រួមបញ្ចូល JavaScript Client

```html
<!-- Include Pusher -->
<script src="https://js.pusher.com/7.0/pusher.min.js"></script>

<!-- Include Real-time Points Client -->
<script src="/js/realtime-points-client.js"></script>
```

### 2. Initialize Client / ចាប់ផ្តើម Client

```javascript
// Initialize real-time points client
const realtimeClient = new RealTimePointsClient();

// Set up event listeners
realtimeClient.on("points.earned", (data) => {
    console.log("Points earned:", data);
    // Update your UI here
});

realtimeClient.on("leaderboard.updated", (data) => {
    console.log("Leaderboard updated:", data);
    // Update leaderboard UI
});

realtimeClient.on("connection.established", (data) => {
    console.log("Connected to real-time system");
});

realtimeClient.on("connection.error", (data) => {
    console.error("Connection error:", data);
});

// Initialize connection
realtimeClient.initialize();
```

### 3. HTML Structure / រចនាសម្ព័ន្ធ HTML

```html
<!-- Points Display -->
<div id="user-points">0</div>

<!-- Leaderboard -->
<div id="leaderboard">
    <!-- Will be populated by JavaScript -->
</div>

<!-- Notification Area -->
<div id="notifications"></div>
```

## Broadcasting Events / ព្រឹត្តិការផ្សព្វផ្សាយ

### Points Earned Event / ព្រឹត្តិការរកពិន្ទុ

```json
{
    "user_id": 1,
    "username": "player1",
    "points_earned": 25,
    "new_total_points": 150,
    "result_id": 123,
    "result_english": "Points 25",
    "result_khmer": "ពិន្ទុ ២៥",
    "timestamp": "2024-01-01T12:00:00Z",
    "message": {
        "en": "Congratulations! You earned 25 points!",
        "km": "សូមអបអរសាទរ! អ្នកបានរកពិន្ទុ 25!"
    }
}
```

### Leaderboard Updated Event / ព្រឹត្តិការធ្វើបច្ចុប្បន្នភាពតារាងឈ្នះ

```json
{
    "leaderboard": {
        "all_time": [...],
        "today": [...],
        "this_week": [...],
        "this_month": [...]
    },
    "timestamp": "2024-01-01T12:00:00Z",
    "message": {
        "en": "Leaderboard has been updated!",
        "km": "តារាងឈ្នះត្រូវបានធ្វើបច្ចុប្បន្នភាព!"
    }
}
```

## Configuration Options / ជម្រើសកំណត់រចនាសម្ព័ន្ធ

### Cache Settings / ការកំណត់ Cache

```php
// config/realtime_points.php
'cache' => [
    'leaderboard_ttl' => 60, // seconds
    'stats_ttl' => 30, // seconds
    'user_status_ttl' => 15, // seconds
],
```

### Notification Settings / ការកំណត់ការជូនដំណឹង

```php
'notifications' => [
    'enabled' => true,
    'browser_notifications' => true,
    'in_app_notifications' => true,
    'notification_duration' => 5000, // milliseconds
],
```

## Testing / ការធ្វើតេស្ត

### 1. Test API Endpoints / ធ្វើតេស្តចំណុចបញ្ចប់ API

```bash
# Test leaderboard
curl -X GET "http://your-domain.com/api/v1/realtime/leaderboard"

# Test statistics
curl -X GET "http://your-domain.com/api/v1/realtime/stats"

# Test with authentication
curl -X GET "http://your-domain.com/api/v1/realtime/user-points" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Test Broadcasting / ធ្វើតេស្តការផ្សព្វផ្សាយ

```php
// In your application
event(new \App\Events\PointsEarned($user, $result, 25, 150));
```

### 3. Browser Testing / ធ្វើតេស្តកម្មវិធីរុករក

1. Open browser console
2. Initialize real-time client
3. Trigger a spin result
4. Check for real-time updates

## Troubleshooting / ការដោះស្រាយបញ្ហា

### Common Issues / បញ្ហាទូទៅ

1. **Broadcasting not working**

    - Check Pusher credentials
    - Verify `BROADCAST_DRIVER` is set to `pusher`
    - Check network connectivity

2. **Events not firing**

    - Ensure events implement `ShouldBroadcast`
    - Check Laravel logs for errors
    - Verify channel authorization

3. **Frontend not receiving updates**
    - Check browser console for errors
    - Verify Pusher library is loaded
    - Check authentication for private channels

### Debug Mode / របៀប Debug

Enable debug mode in `.env`:

```env
REALTIME_POINTS_DEBUG=true
APP_DEBUG=true
```

Check logs at `storage/logs/laravel.log`

## Performance Optimization / ការធ្វើឱ្យប្រសើរឡើងដំណើរការ

### 1. Caching / ការកេស

-   Leaderboard data is cached for 60 seconds
-   Statistics are cached for 30 seconds
-   User status is cached for 15 seconds

### 2. Database Optimization / ការធ្វើឱ្យប្រសើរឡើង Database

-   Use database indexes on frequently queried columns
-   Consider read replicas for heavy traffic
-   Implement query optimization

### 3. Broadcasting Optimization / ការធ្វើឱ្យប្រសើរឡើងការផ្សព្វផ្សាយ

-   Batch updates when possible
-   Use private channels for user-specific data
-   Implement rate limiting

## Security Considerations / ការពិចារណាសុវត្ថិភាព

1. **Rate Limiting** - API endpoints are rate limited
2. **Authentication** - Private channels require authentication
3. **Input Validation** - All inputs are validated
4. **SQL Injection Protection** - Using Eloquent ORM
5. **XSS Protection** - Output is properly escaped

## Support / ការគាំទ្រ

For support and questions:

-   Check Laravel documentation
-   Review Pusher documentation
-   Check application logs
-   Enable debug mode for detailed error messages

## License / អាជ្ញាប័ណ្ណ

This real-time points system is part of your spin wheel game application.

---

**Note**: Make sure to test thoroughly in a development environment before deploying to production.

**ចំណាំ**: ត្រូវប្រាកដថាធ្វើតេស្តឱ្យគ្រប់ជ្រុងជ្រោយក្នុងបរិស្ថានអភិវឌ្ឍន៍មុនពេលដំឡើងទៅ production។
