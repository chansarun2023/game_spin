<?php

// Database connection test script
// Run this to verify your database connection is working

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

echo "🔍 Testing database connection...\n";

try {
    // Test basic connection
    $connection = DB::connection();
    $pdo = $connection->getPdo();

    echo "✅ Database connection successful!\n";
    echo "📊 Database: " . $connection->getDatabaseName() . "\n";
    echo "🔗 Driver: " . $connection->getDriverName() . "\n";

    // Test if we can execute a simple query
    $result = $connection->select('SELECT current_database(), current_schema()');
    echo "🗄️ Current database: " . $result[0]->current_database . "\n";
    echo "📁 Current schema: " . $result[0]->current_schema . "\n";

    // Test if migrations table exists
    $migrationsExist = $connection->getSchemaBuilder()->hasTable('migrations');
    echo "📋 Migrations table exists: " . ($migrationsExist ? "Yes" : "No") . "\n";

    if ($migrationsExist) {
        $migrationCount = $connection->table('migrations')->count();
        echo "📈 Total migrations: " . $migrationCount . "\n";
    }

} catch (Exception $e) {
    echo "❌ Database connection failed!\n";
    echo "🔍 Error: " . $e->getMessage() . "\n";
    echo "📝 Stack trace:\n" . $e->getTraceAsString() . "\n";

    // Show current database configuration
    echo "\n🔧 Current database configuration:\n";
    echo "Connection: " . Config::get('database.default') . "\n";
    echo "Host: " . Config::get('database.connections.pgsql.host') . "\n";
    echo "Port: " . Config::get('database.connections.pgsql.port') . "\n";
    echo "Database: " . Config::get('database.connections.pgsql.database') . "\n";
    echo "Schema: " . Config::get('database.connections.pgsql.search_path') . "\n";
}

echo "\n🏁 Test completed.\n";
