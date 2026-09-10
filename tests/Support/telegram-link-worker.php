<?php

use DefStudio\Telegraph\Facades\Telegraph;
use Domain\Telegram\Actions\FindOrRegisterTelegramUser;
use Domain\Telegram\Actions\LinkTelegramAccount;
use Domain\Telegram\Actions\UnlinkTelegramAccount;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.mysql.database') !== 'svoidelivery2026_test'
    || DB::selectOne('select database() as db')->db !== 'svoidelivery2026_test') {
    throw new RuntimeException('Requires isolated Telegram test database');
}
Http::preventStrayRequests();
Mail::fake();
Telegraph::fake();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo "READY\n";
flush();
if ($input['operation'] === 'login') {
    $user = app(FindOrRegisterTelegramUser::class)->handle($input['bot_id'], $input['chat_id'], 'Test', null);
    $result = ['result' => 'login', 'user_id' => $user->id];
} elseif ($input['operation'] === 'unlink') {
    app(UnlinkTelegramAccount::class)->handle('user', $input['user_id']);
    $result = ['result' => 'unlinked'];
} else {
    $result = ['result' => app(LinkTelegramAccount::class)->handle(
        $input['code'], $input['bot_id'], (string) $input['chat_id'], $input['chat_id'], 'private', false, 'Test',
    )->value];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
