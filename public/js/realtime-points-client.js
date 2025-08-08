/**
 * Real-time Points Client
 * Example JavaScript client for connecting to real-time points functionality
 *
 * English: Real-time points client for spin wheel game
 * ខ្មែរ: អតិថិជនពិន្ទុពេលវេលាពិតសម្រាប់ហ្គេមរបង់
 */

class RealTimePointsClient {
    constructor() {
        this.connection = null;
        this.channels = {};
        this.isConnected = false;
        this.apiBase = '/api/v1/realtime';
        this.eventHandlers = {
            'points.earned': [],
            'leaderboard.updated': [],
            'connection.established': [],
            'connection.error': [],
        };
    }

    /**
     * Initialize the real-time connection
     */
    async initialize() {
        try {
            // Get broadcasting configuration from server
            const response = await fetch(`${this.apiBase}/broadcasting-info`);
            const config = await response.json();

            if (!config.success) {
                throw new Error('Failed to get broadcasting configuration');
            }

            // Initialize Pusher (or other broadcasting driver)
            if (config.data.broadcast_driver === 'pusher' && config.data.pusher_config) {
                this.initializePusher(config.data.pusher_config);
            } else {
                console.warn('Pusher not configured, using fallback polling');
                this.initializePolling();
            }

            // Set up channels
            this.setupChannels(config.data.channels);

            this.isConnected = true;
            this.triggerEvent('connection.established', { config: config.data });

        } catch (error) {
            console.error('Failed to initialize real-time connection:', error);
            this.triggerEvent('connection.error', { error: error.message });
        }
    }

    /**
     * Initialize Pusher connection
     */
    initializePusher(config) {
        if (typeof Pusher === 'undefined') {
            console.error('Pusher library not loaded');
            return;
        }

        this.connection = new Pusher(config.key, {
            cluster: config.cluster,
            encrypted: config.encrypted,
        });

        this.connection.connection.bind('connected', () => {
            console.log('Connected to Pusher');
            this.isConnected = true;
        });

        this.connection.connection.bind('error', (error) => {
            console.error('Pusher connection error:', error);
            this.triggerEvent('connection.error', { error });
        });
    }

    /**
     * Initialize polling fallback
     */
    initializePolling() {
        console.log('Using polling fallback for real-time updates');
        this.startPolling();
    }

    /**
     * Set up broadcasting channels
     */
    setupChannels(channels) {
        if (this.connection) {
            // Set up Pusher channels
            Object.entries(channels).forEach(([name, channelName]) => {
                const channel = this.connection.subscribe(channelName);

                channel.bind('points.earned', (data) => {
                    this.handlePointsEarned(data);
                });

                channel.bind('leaderboard.updated', (data) => {
                    this.handleLeaderboardUpdated(data);
                });

                this.channels[name] = channel;
            });
        }
    }

    /**
     * Start polling for updates (fallback)
     */
    startPolling() {
        this.pollingInterval = setInterval(async () => {
            try {
                const response = await fetch(`${this.apiBase}/data`);
                const data = await response.json();

                if (data.success) {
                    this.handlePollingUpdate(data.data);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 5000); // Poll every 5 seconds
    }

    /**
     * Handle points earned event
     */
    handlePointsEarned(data) {
        console.log('Points earned:', data);
        this.triggerEvent('points.earned', data);

        // Show notification
        this.showNotification(data.message.en, data.message.km);

        // Update UI
        this.updatePointsDisplay(data);
    }

    /**
     * Handle leaderboard updated event
     */
    handleLeaderboardUpdated(data) {
        console.log('Leaderboard updated:', data);
        this.triggerEvent('leaderboard.updated', data);

        // Update leaderboard UI
        this.updateLeaderboardDisplay(data.leaderboard);
    }

    /**
     * Handle polling update
     */
    handlePollingUpdate(data) {
        // Compare with previous data and trigger events if changed
        if (this.previousData) {
            if (JSON.stringify(data.leaderboard) !== JSON.stringify(this.previousData.leaderboard)) {
                this.handleLeaderboardUpdated({ leaderboard: data.leaderboard });
            }
        }
        this.previousData = data;
    }

    /**
     * Show notification
     */
    showNotification(messageEn, messageKm) {
        // Check if browser supports notifications
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Spin Wheel Game', {
                body: messageEn,
                icon: '/favicon.ico'
            });
        }

        // Show in-app notification
        this.showInAppNotification(messageEn, messageKm);
    }

    /**
     * Show in-app notification
     */
    showInAppNotification(messageEn, messageKm) {
        const notification = document.createElement('div');
        notification.className = 'realtime-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-message">
                    <div class="message-en">${messageEn}</div>
                    <div class="message-km">${messageKm}</div>
                </div>
                <button class="notification-close">&times;</button>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 300px;
            animation: slideIn 0.3s ease-out;
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);

        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }

    /**
     * Update points display
     */
    updatePointsDisplay(data) {
        const pointsElement = document.getElementById('user-points');
        if (pointsElement) {
            pointsElement.textContent = data.new_total_points;
            pointsElement.classList.add('points-updated');
            setTimeout(() => {
                pointsElement.classList.remove('points-updated');
            }, 1000);
        }
    }

    /**
     * Update leaderboard display
     */
    updateLeaderboardDisplay(leaderboard) {
        const leaderboardElement = document.getElementById('leaderboard');
        if (leaderboardElement) {
            leaderboardElement.innerHTML = leaderboard.map((user, index) => `
                <div class="leaderboard-item ${index < 3 ? 'top-' + (index + 1) : ''}">
                    <div class="rank">${user.rank}</div>
                    <div class="username">${user.username}</div>
                    <div class="points">${user.points}</div>
                </div>
            `).join('');
        }
    }

    /**
     * Add event listener
     */
    on(event, handler) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].push(handler);
        }
    }

    /**
     * Remove event listener
     */
    off(event, handler) {
        if (this.eventHandlers[event]) {
            const index = this.eventHandlers[event].indexOf(handler);
            if (index > -1) {
                this.eventHandlers[event].splice(index, 1);
            }
        }
    }

    /**
     * Trigger event
     */
    triggerEvent(event, data) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    console.error('Event handler error:', error);
                }
            });
        }
    }

    /**
     * Get real-time data
     */
    async getRealTimeData(timeframe = 'all', limit = 10) {
        try {
            const response = await fetch(`${this.apiBase}/data?timeframe=${timeframe}&limit=${limit}`);
            return await response.json();
        } catch (error) {
            console.error('Failed to get real-time data:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Get user points status
     */
    async getUserPointsStatus() {
        try {
            const response = await fetch(`${this.apiBase}/user-points`, {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json',
                }
            });
            return await response.json();
        } catch (error) {
            console.error('Failed to get user points status:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Get authentication token
     */
    getAuthToken() {
        // Get token from localStorage, sessionStorage, or cookie
        return localStorage.getItem('auth_token') ||
               sessionStorage.getItem('auth_token') ||
               this.getCookie('auth_token');
    }

    /**
     * Get cookie value
     */
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Disconnect
     */
    disconnect() {
        if (this.connection) {
            this.connection.disconnect();
        }

        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }

        this.isConnected = false;
        console.log('Real-time connection disconnected');
    }
}

// Add CSS for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .realtime-notification .notification-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .realtime-notification .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        margin-left: 10px;
    }

    .points-updated {
        animation: pulse 0.5s ease-in-out;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .leaderboard-item {
        display: flex;
        justify-content: space-between;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .leaderboard-item.top-1 { background: #FFD700; }
    .leaderboard-item.top-2 { background: #C0C0C0; }
    .leaderboard-item.top-3 { background: #CD7F32; }
`;

document.head.appendChild(style);

// Export for use in other scripts
window.RealTimePointsClient = RealTimePointsClient;
