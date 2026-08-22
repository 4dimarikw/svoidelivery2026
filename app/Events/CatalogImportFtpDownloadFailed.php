<?php

namespace App\Events;

/**
 * Загрузка CSV-выгрузки 1С с FTP не удалась (Infrastructure\Ftp\Catalog1cFtpClient
 * бросил исключение) — попадает в event_logs через App\Listeners\PersistEventLog
 * (автодискавери по LoggableEvent). Диспатчится из
 * App\Console\Commands\CatalogImportCommand::resolveImportPath(), до того как
 * CsvParserService успевает начать работу — при постоянно сломанном FTP это
 * единственная запись о том, что catalog:import (каждые 30 минут) не выполняется.
 */
final readonly class CatalogImportFtpDownloadFailed implements LoggableEvent
{
    public function __construct(
        public ?string $host,
        public ?int $port,
        public string $file,
        public string $exceptionClass,
        public string $errorMessage,
    ) {}

    public function eventType(): string
    {
        return 'catalog_import.ftp_download_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Загрузка каталога с FTP не удалась: {$this->errorMessage}";
    }

    public function context(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'file' => $this->file,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
