<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "Running Laravel commands...<br>";
\Artisan::call('key:generate');
echo "key:generate OK<br>";
\Artisan::call('config:cache');
echo "config:cache OK<br>";
\Artisan::call('route:cache');
echo "route:cache OK<br>";
\Artisan::call('view:cache');
echo "view:cache OK<br>";
