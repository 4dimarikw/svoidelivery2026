<?php

namespace Infrastructure\Ftp;

use RuntimeException;

/**
 * FTP-клиент для скачивания экспортного файла из 1C.
 *
 * Конфигурация: config('services.catalog_1c_ftp.*')
 * (env: DB_1C_FTP_SERVER, DB_1C_FTP_PORT, DB_1C_FTP_LOGIN, DB_1C_FTP_PASSWORD).
 *
 * Использует нативный ext-ftp в пассивном режиме (совместимость с нестандартными портами).
 */
class Catalog1cFtpClient
{
    private const int CONNECT_TIMEOUT = 15;

    /**
     * Скачивает файл с FTP-сервера и сохраняет локально.
     *
     * @param  string  $remoteFile  Имя/путь файла на FTP.
     * @param  string  $localPath  Абсолютный путь для сохранения локально.
     * @return string Абсолютный путь к скачанному файлу.
     *
     * @throws RuntimeException При ошибках подключения, авторизации или скачивания.
     */
    public function download(string $remoteFile, string $localPath): string
    {
        $host = (string) config('services.catalog_1c_ftp.host');
        $port = (int) config('services.catalog_1c_ftp.port', 21);
        $username = (string) config('services.catalog_1c_ftp.username');
        $password = (string) config('services.catalog_1c_ftp.password');

        $dir = dirname($localPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }

        $conn = ftp_connect($host, $port, self::CONNECT_TIMEOUT);

        if ($conn === false) {
            throw new RuntimeException("FTP connection failed: {$host}:{$port}");
        }

        try {
            if (! ftp_login($conn, $username, $password)) {
                throw new RuntimeException("FTP login failed for user '{$username}' on {$host}:{$port}");
            }

            ftp_pasv($conn, true);

            // PASV за NAT может вернуть приватный IP сервера — держим data-канал
            // на адресе управляющего соединения, а не на адресе из ответа PASV.
            ftp_set_option($conn, FTP_USEPASVADDRESS, false);

            if (! ftp_get($conn, $localPath, $remoteFile, FTP_BINARY)) {
                throw new RuntimeException("FTP download failed: '{$remoteFile}' from {$host}:{$port}");
            }
        } finally {
            ftp_close($conn);
        }

        return $localPath;
    }
}
