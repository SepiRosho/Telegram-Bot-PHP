<?php

namespace Devflow\TelegramBot\Api;

/**
 * A local file to upload, for any API parameter that Telegram documents as
 * accepting InputFile — photo, document, video, audio, voice, sticker,
 * thumbnail, and so on.
 *
 * Those parameters otherwise only take a `file_id` or a public URL, which is
 * a string; wrapping a local file in this class is what tells HttpClient to
 * switch the request from a JSON body to a multipart upload.
 *
 *   $ctx->replyWithDocument(InputFile::path('/tmp/invoice.pdf'));
 *   $ctx->replyWithPhoto(InputFile::contents($pngBytes, 'chart.png'));
 */
class InputFile
{
    private function __construct(
        private readonly ?string $path,
        private readonly ?string $contents,
        private readonly ?string $filename,
    ) {}

    /** Upload a file from disk. */
    public static function path(string $path, ?string $filename = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("File not found or not readable: {$path}");
        }

        return new self($path, null, $filename ?? basename($path));
    }

    /** Upload raw bytes generated in memory (a rendered PDF, an image, a CSV export). */
    public static function contents(string $contents, string $filename): self
    {
        return new self(null, $contents, $filename);
    }

    public function filename(): ?string
    {
        return $this->filename;
    }

    /**
     * Guzzle accepts a stream resource or a string for a multipart part's
     * contents; a path opens lazily so a large upload is streamed rather than
     * read into memory in full.
     *
     * @return resource|string
     */
    public function open(): mixed
    {
        if ($this->path !== null) {
            $handle = fopen($this->path, 'r');
            if ($handle === false) {
                throw new \RuntimeException("Could not open file for upload: {$this->path}");
            }
            return $handle;
        }

        return (string) $this->contents;
    }
}
