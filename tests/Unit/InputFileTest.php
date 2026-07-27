<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\InputFile;
use Devflow\TelegramBot\Api\TelegramApi;
use PHPUnit\Framework\TestCase;

class InputFileTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'devflow_') ?: '';
        file_put_contents($this->tempFile, 'report contents');
    }

    protected function tearDown(): void
    {
        if ($this->tempFile !== '' && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function test_path_rejects_a_file_that_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InputFile::path(sys_get_temp_dir() . '/definitely-not-here-' . uniqid() . '.pdf');
    }

    public function test_path_defaults_the_filename_to_the_basename(): void
    {
        $this->assertSame(basename($this->tempFile), InputFile::path($this->tempFile)->filename());
    }

    public function test_path_accepts_an_explicit_filename(): void
    {
        $this->assertSame('invoice.pdf', InputFile::path($this->tempFile, 'invoice.pdf')->filename());
    }

    public function test_path_opens_a_readable_stream(): void
    {
        $handle = InputFile::path($this->tempFile)->open();

        $this->assertIsResource($handle);
        $this->assertSame('report contents', stream_get_contents($handle));

        fclose($handle);
    }

    public function test_contents_carries_raw_bytes_and_a_filename(): void
    {
        $file = InputFile::contents('raw-bytes', 'chart.png');

        $this->assertSame('chart.png', $file->filename());
        $this->assertSame('raw-bytes', $file->open());
    }

    public function test_send_document_passes_the_input_file_through_to_the_transport(): void
    {
        $http = new FakeHttpClient();
        $api  = new TelegramApi($http);
        $file = InputFile::path($this->tempFile);

        $api->sendDocument(100, $file, ['caption' => 'Here you go']);

        $params = $http->callsTo('sendDocument')[0]['params'];

        $this->assertInstanceOf(InputFile::class, $params['document']);
        $this->assertSame('Here you go', $params['caption']);
    }

    public function test_a_plain_string_is_still_treated_as_a_file_id(): void
    {
        $http = new FakeHttpClient();
        $api  = new TelegramApi($http);

        $api->sendPhoto(100, 'AgACAgQAAxkBAAI');

        $this->assertSame('AgACAgQAAxkBAAI', $http->callsTo('sendPhoto')[0]['params']['photo']);
    }

    // ─── Multipart encoding ───────────────────────────────────────────────────

    /** Reaches into HttpClient's private encoder: the JSON/multipart split is the whole risk here. */
    private function multipartFor(array $params): array
    {
        $client = new HttpClient('123:token');
        $method = new \ReflectionMethod(HttpClient::class, 'toMultipart');

        return $method->invoke($client, $params);
    }

    private function partNamed(array $parts, string $name): ?array
    {
        foreach ($parts as $part) {
            if ($part['name'] === $name) {
                return $part;
            }
        }

        return null;
    }

    public function test_multipart_json_encodes_nested_arrays(): void
    {
        // Multipart bodies are flat, so reply_markup must be encoded here —
        // the exact inverse of the JSON path, where encoding it is the bug.
        $parts  = $this->multipartFor([
            'chat_id'      => 100,
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Hi', 'callback_data' => 'hi']]]],
        ]);
        $markup = $this->partNamed($parts, 'reply_markup');

        $this->assertNotNull($markup);
        $this->assertSame(
            '{"inline_keyboard":[[{"text":"Hi","callback_data":"hi"}]]}',
            $markup['contents'],
        );
    }

    public function test_multipart_renders_booleans_the_way_telegram_expects(): void
    {
        $parts = $this->multipartFor(['disable_notification' => true, 'protect_content' => false]);

        $this->assertSame('true', $this->partNamed($parts, 'disable_notification')['contents']);
        $this->assertSame('false', $this->partNamed($parts, 'protect_content')['contents']);
    }

    public function test_multipart_includes_the_filename_for_an_upload_part(): void
    {
        $parts = $this->multipartFor([
            'chat_id'  => 100,
            'document' => InputFile::contents('bytes', 'invoice.pdf'),
        ]);
        $part = $this->partNamed($parts, 'document');

        $this->assertSame('invoice.pdf', $part['filename']);
        $this->assertSame('bytes', $part['contents']);
    }

    public function test_requests_without_an_upload_still_use_a_json_body(): void
    {
        $client = new HttpClient('123:token');
        $method = new \ReflectionMethod(HttpClient::class, 'requestOptions');

        $this->assertArrayHasKey('json', $method->invoke($client, ['chat_id' => 1, 'text' => 'hi']));
    }

    public function test_requests_with_an_upload_switch_to_multipart(): void
    {
        $client = new HttpClient('123:token');
        $method = new \ReflectionMethod(HttpClient::class, 'requestOptions');

        $options = $method->invoke($client, ['chat_id' => 1, 'photo' => InputFile::contents('x', 'a.png')]);

        $this->assertArrayHasKey('multipart', $options);
        $this->assertArrayNotHasKey('json', $options);
    }
}
