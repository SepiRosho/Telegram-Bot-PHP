<?php

namespace Devflow\TelegramBot\Api;

/**
 * Optional capability of an HttpClientInterface: fetching a file's bytes by
 * the `file_path` getFile returns. Files live under a different URL from API
 * methods (`/file/bot<token>/<path>`), so they cannot go through post().
 */
interface FileDownloaderInterface
{
    /** @throws \Devflow\TelegramBot\Exceptions\TelegramApiException when the file cannot be fetched */
    public function download(string $filePath): string;
}
