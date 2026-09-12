<?php

Schedule::command('orders:dispatch-deliveries')->everyMinute()->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
Schedule::command('telegram:prune-link-codes')->daily();
Schedule::command('logs:prune-sensitive')->daily();
Schedule::command('vk:prune-post-images')->daily();

// Синхронизация VK запускается каждые 30 минут и учитывает флаг active в команде.
Schedule::command('vk:sync-posts')->everyThirtyMinutes();

Schedule::command('catalog:import')->everyThirtyMinutes();

Schedule::command('untappd:sync-beers')->everyThreeHours();
