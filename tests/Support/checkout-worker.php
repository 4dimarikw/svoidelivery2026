<?php

declare(strict_types=1);
use App\Events\Order\OrderCreated;
use App\Events\Order\OrderStatusChanged;
use App\Http\Controllers\OrderController;
use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Order\Requests\OrderRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

// Отдельный процесс и соединение MySQL: HTTP-сервер разработчика однопоточный.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.default') !== 'mysql'
    || DB::selectOne('select database() as db')->db !== 'svoidelivery2026_test') {
    throw new RuntimeException('Worker requires the isolated checkout test database');
}

Http::preventStrayRequests();
Mail::fake();
Bus::fake();
Event::forget(OrderCreated::class);
Event::forget(OrderStatusChanged::class);
$events = 0;
$eventLevels = [];
Event::listen(OrderCreated::class, function () use (&$events, &$eventLevels) {
    $events++;
    $eventLevels[] = DB::transactionLevel();
});

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
auth()->setUser(User::findOrFail($input['user_id']));
$request = OrderRequest::create('/checkout', 'POST', $input['payload'] ?? []);
$request->headers->set('Accept', 'application/json');
$request->setContainer($app)->setRedirector(app('redirect'));
$request->setUserResolver(fn () => auth()->user());
$app->instance('request', $request);
echo "READY\n";
flush();

try {
    if ($input['operation'] === 'checkout') {
        $request->validateResolved();
        $response = app(OrderController::class)->store($request);
        $result = ['status' => $response->getStatusCode(), 'redirect' => $response->headers->get('X-Redirect')];
    } elseif ($input['operation'] === 'clear') {
        cart()->truncate();
        $result = ['status' => 200];
    } else {
        $product = Product::findOrFail($input['product_id']);
        cart()->increment($product, $input['by'] ?? 1);
        $result = ['status' => 200];
    }
} catch (ValidationException $e) {
    $result = ['status' => 422, 'errors' => $e->errors()];
}

echo json_encode($result + ['events' => $events, 'event_levels' => $eventLevels], JSON_THROW_ON_ERROR)."\n";
