# SvoiDelivery

Проект на Laravel 12 и MoonShine 4.

## Руководства для AI-агента

- [Создание системы MoonShine permissions для админки](docs/moonshine-permissions-ai-agent-guideline.md) — архитектура, примеры реализации, проверки и внедрение в другом проекте.

## Развёртывание на хостинге

Команды ниже рассчитаны на Linux/SSH и выполняются из корня проекта. В cron замените пути на реальные пути хостинга. Установка зависимостей, Artisan и cron должны работать под пользователем проекта и одной версией PHP.

### 1. Подготовка сервера

- PHP **8.4** или совместимая версия 8.x, удовлетворяющая `composer.lock`. Проверьте отдельно PHP сайта и CLI: `php -v`.
- Composer 2, доступ к GitHub по HTTPS и возможность распаковывать ZIP-архивы.
- Расширения Laravel: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML. Проект также явно требует FTP и SimpleXML; для изображений по умолчанию используется GD. Установите PDO-драйвер выбранной БД. Полный набор требований проверяется Composer.
- База данных и пользователь с доступом к ней; возможность запускать cron каждую минуту.
- Для сборки интерфейса: Node.js 22.12+ совместимой с Vite версии и npm. Сборку можно выполнить локально или в CI и загрузить весь `public/build` на сервер.

В панели хостинга установите корень сайта в **`/путь/к/проекту/public`**, включите HTTPS и маршрутизацию запросов через `public/index.php` (Apache: обработка `public/.htaccess`; Nginx: `try_files $uri $uri/ /index.php?$query_string;`). Не открывайте корень проекта через веб-сервер.

Пользователям PHP и cron нужен доступ на запись в `storage` и `bootstrap/cache`. Настройте владельца/группу средствами хостинга, без прав `777`. Подробнее: [развёртывание Laravel](https://laravel.com/docs/12.x/deployment).

### 2. Доступ Composer к приватному пакету

Проект использует `dimarik/laravel-content-cms` из `4dimarikw/laravel-content-cms`. В текущем `composer.lock` закреплена версия `v2.0.1`, коммит `08e0fd4caeacf41dc47b61d632ab940f45b12893`.

Если `composer install` завершается ошибкой `HTTP/2 404` при загрузке этого пакета с `api.github.com`, вероятная причина — отсутствие доступа к приватному репозиторию. GitHub также возвращает 404, если репозиторий или коммит удалён. Сообщение `Source fallback is disabled` означает, что после ошибки загрузки архива Composer не пробует клонировать исходники.

1. Создайте [GitHub Fine-grained token](https://github.com/settings/personal-access-tokens/new) от аккаунта с доступом к репозиторию. Выберите только `laravel-content-cms` и разрешение **Contents: Read-only**.
2. На хостинге под пользователем, запускающим Composer, откройте глобальные настройки авторизации:

   ```bash
   composer config --global --editor --auth
   ```

3. Добавьте в JSON следующий раздел, сохранив существующие настройки:

   ```json
   {
     "github-oauth": {
       "github.com": "ТВОЙ_GITHUB_TOKEN"
     }
   }
   ```

Если деплой выполняет панель или CI, задайте **секретную переменную окружения `COMPOSER_AUTH`** с этим JSON в настройках сборки. Обычный Laravel `.env` не передаёт такую переменную процессу Composer. Токен не добавляйте в `composer.json`, Git или логи; проектный `auth.json` уже исключён через `.gitignore`.

После настройки повторите `composer install` из шага 4. Если 404 остаётся, проверьте срок действия токена, доступ именно к этому репозиторию и наличие закреплённого коммита. Не удаляйте `composer.lock` и не запускайте общий `composer update` для исправления ошибки доступа. Подробности: [авторизация Composer для GitHub](https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth).

### 3. Настройка окружения

При первой установке создайте `.env` из примера, только если файла ещё нет:

```bash
test -f .env || cp .env.example .env
```

Пример настроек для MySQL (подставьте домен и реквизиты хостинга):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.ru
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=svoidelivery
DB_USERNAME=svoidelivery
DB_PASSWORD="ЗАМЕНИТЕ_НА_ПАРОЛЬ"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=file
```

В `.env.example` по умолчанию указан SQLite. Если оставляете его, создайте файл БД, задайте абсолютный `DB_DATABASE` и обеспечьте запись в файл и его каталог. При переносе действующего сайта используйте существующую БД и прежний `APP_KEY`.

Основной домен из `APP_URL` разрешён автоматически. Дополнительные домены задаются через `SECURITY_TRUSTED_HOSTS`; при наличии reverse proxy укажите его конкретные IP/CIDR в `SECURITY_TRUSTED_PROXIES`. Без прокси оставьте значение пустым.

Для отправки почты настройте `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` по параметрам провайдера и действительный `MAIL_FROM_ADDRESS`. Значение `MAIL_MAILER=log` из примера не отправляет письма получателям.

### 4. Зависимости и база данных

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan config:clear
```

Только при первой установке и пустом `APP_KEY`:

```bash
php artisan key:generate --force
```

Создайте таблицы:

```bash
php artisan migrate --force
```

Для **новой базы** предусмотрено первоначальное заполнение справочников, настроек и контента. `DatabaseSeeder` запускает `CurrentSiteDataSeeder`, который требует токены ботов из локального файла, если таблица `telegraph_bots` пуста. До запуска подготовьте файл:

```bash
cp database/seeders/data/current-site.local.example.php database/seeders/data/current-site.local.php
```

Заполните токены для ID ботов из примера. Файл `current-site.local.php` исключён из Git. Затем выполните:

```bash
php artisan db:seed --force
php artisan moonshine:user
```

Вторая команда интерактивно создаёт администратора. Админ-панель по умолчанию доступна по `/admin`. Проверьте импортированные настройки, контакты, ссылки и контент перед открытием сайта. Если переносите существующую БД, восстановите её из резервной копии, примените миграции и используйте существующих пользователей; общий сидер не включайте в каждое обновление.

### 5. Интерфейс и загруженные файлы

```bash
npm ci
npm run build
php artisan vendor:publish --tag=laravel-assets --force
php artisan storage:link
```

Для сборки нужны dev-зависимости npm: не используйте `npm ci --omit=dev`. Если сборка выполнена локально/в CI, на хостинг достаточно передать `public/build` вместе с `manifest.json`. Не переносите `public/hot`: он переключает сайт на сервер разработки Vite.

Публикация `laravel-assets` нужна для ресурсов MoonShine и редакторов. В проекте она прописана в `post-update-cmd`, поэтому при обычном `composer install` её нужно вызвать отдельно.

`public/storage` должен ссылаться на `storage/app/public`. При переносе сайта скопируйте сами загрузки из `storage/app/public`, а также `public/media`, если использовался диск `media_prod`: записи БД и сидеры не заменяют файлы изображений. На хостинге должна быть разрешена работа символических ссылок.

### 6. Telegram и внешние интеграции

Токены Telegram хранятся в таблице `telegraph_bots`, а не в `.env`. Для нового бота вне импорта снимка есть команда `php artisan telegraph:new-bot`. После настройки рабочего HTTPS-домена зарегистрируйте webhook для нужного ID бота:

```bash
php artisan telegraph:set-webhook 1
php artisan telegraph:debug-webhook 1
```

Замените `1` на фактический ID. Регистрация меняет webhook этого бота: если бот используется локально или на другом сервере, обновления начнут приходить на новый сайт. Проверьте домен входа и URL Mini App в BotFather, если используете эти функции.

Дополнительно настройте используемые интеграции:

- Уведомления в служебную группу: `TELEGRAM_MANAGE_GROUP`, при необходимости `TELEGRAM_BOT_NEW_ORDER_THREAD_ID`; бот должен иметь доступ к группе.
- VK: `VK_ACCESS_TOKEN`, `VK_API_VERSION` и настройки синхронизации в админ-панели.
- Импорт 1С по FTP: `DB_1C_FTP_SERVER`, `DB_1C_FTP_PORT` (обычно `21`), `DB_1C_FTP_LOGIN`, `DB_1C_FTP_PASSWORD` и настройки импорта в админ-панели.

Проверьте исходящие соединения хостинга к используемым API и FTP. Включайте cron после проверки настроек импортов: он запускает реальные синхронизации и отправку уведомлений.

### 7. Планировщик и очередь

Добавьте в cron хостинга запуск **каждую минуту**:

```cron
* * * * * cd /home/USER/svoidelivery && /usr/bin/php artisan schedule:run >> /home/USER/svoidelivery/storage/logs/scheduler.log 2>&1
```

Укажите реальный путь к PHP 8.4 и проекту. Настройте ротацию `scheduler.log` средствами хостинга.

В `routes/console.php` уже настроены:

- Каждую минуту: доставка заказов и `queue:work --stop-when-empty`.
- Каждые 30 минут: синхронизация VK и импорт каталога.
- Каждые 3 часа: синхронизация Untappd.
- Ежедневно: очистка кодов привязки Telegram, чувствительных логов и старых изображений VK.

Поэтому для текущей схемы достаточно cron: отдельный постоянный worker не обязателен. Задачи очереди могут ожидать следующего минутного запуска. Хостинг должен позволять дочерние CLI-процессы и достаточное время работы задач. Если переводите очередь под Supervisor, согласуйте это с уже существующим запуском worker в планировщике. Часовой пояс приложения — `Europe/Moscow`.

Проверка расписания и ошибок очереди:

```bash
php artisan schedule:list
php artisan queue:failed
```

### 8. Завершение и проверка

После окончательной настройки `.env` и БД:

```bash
php artisan config:cache
php artisan view:cache
```

Повторяйте `config:cache` после изменения `.env`. Откройте главную страницу, `/admin` и `/up`, проверьте изображения и оформление, вход, тестовый заказ и доставку уведомления. `/up` проверяет загрузку приложения, но не заменяет проверку БД, очереди и интеграций. При ошибках смотрите `storage/logs`, журнал cron и логи PHP хостинга.

Настройте резервные копии БД, загрузок, `.env` с `APP_KEY` и локального файла токенов. Храните секреты в защищённом месте и проверьте возможность восстановления.

### Обновление работающего сайта

Перед обновлением сделайте резервную копию БД и загрузок. Для обновления в том же каталоге:

1. Приостановите cron и постоянные workers, если они настроены, и дождитесь завершения уже запущенных задач.
2. Выполните `php artisan down`, затем загрузите новую версию кода с `composer.lock` и `package-lock.json`, сохранив `.env`, загрузки и локальный файл токенов.
3. Выполните команды ниже по порядку, останавливаясь при ошибке:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   composer check-platform-reqs --no-dev
   php artisan config:clear
   php artisan route:clear
   php artisan event:clear
   php artisan view:clear
   php artisan migrate --force
   npm ci
   npm run build
   php artisan vendor:publish --tag=laravel-assets --force
   php artisan config:cache
   php artisan view:cache
   php artisan queue:restart
   php artisan up
   ```

4. Если интерфейс собран в CI, вместо npm-команд загрузите готовый `public/build`. Возобновите cron/workers и проверьте сайт и очереди.

При ошибке не продолжайте автоматически до `artisan up`: сначала устраните причину или восстановите согласованную версию кода и БД. При обновлении не генерируйте новый `APP_KEY`, не запускайте `migrate:fresh` и не выполняйте общий `db:seed`.
