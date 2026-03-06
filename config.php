<?php
// ============================================================
// config.php — Central configuration (replaces .env)
// ============================================================

// Supabase
define('SUPABASE_URL',         'https://okyfmrpnuhksnulsgyfv.supabase.co');
define('SUPABASE_ANON_KEY',    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9reWZtcnBudWhrc251bHNneWZ2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzI2ODYzMzksImV4cCI6MjA4ODI2MjMzOX0.nk1k4HDffdNurHAgbLWc6T7yJEaeQm6sbE_06tDV0oU');
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9reWZtcnBudWhrc251bHNneWZ2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MjY4NjMzOSwiZXhwIjoyMDg4MjYyMzM5fQ.-mCXUC9JegP2FHfr6a1WDnnD2ThgE58hYWHwGqVWKQI');

// App
define('APP_ENV',              'production');
define('APP_DEBUG',            false);
define('APP_URL',              'http://localhost:8080');
define('SESSION_LIFETIME',     28800);
