<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\FakeHttpClient;
use Devflow\TelegramBot\Api\HttpClient;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Exceptions\TelegramApiException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ApiUrlAndDownloadTest extends TestCase
{
    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    /** @param list<Response> $queue */
    private function client(array $queue, array $options = []): HttpClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new HttpClient('123:token', array_merge(['handler' => $stack], $options));
    }

    private function ok(mixed $result = true): Response
    {
        return new Response(200, [], (string) json_encode(['ok' => true, 'result' => $result]));
    }

    private function lastUri(): string
    {
        return (string) end($this->history)['request']->getUri();
    }

    public function test_the_default_server_is_telegrams_cloud_api(): void
    {
        $this->client([$this->ok()])->post('getMe');

        $this->assertSame('https://api.telegram.org/bot123:token/getMe', $this->lastUri());
    }

    public function test_api_url_points_at_a_self_hosted_server(): void
    {
        $this->client([$this->ok()], ['api_url' => 'http://localhost:8081/'])->post('getMe');

        $this->assertSame('http://localhost:8081/bot123:token/getMe', $this->lastUri());
    }

    public function test_test_environment_adds_the_test_segment(): void
    {
        $this->client([$this->ok()], ['test_environment' => true])->post('getMe');

        $this->assertSame('https://api.telegram.org/bot123:token/test/getMe', $this->lastUri());
    }

    public function test_download_fetches_from_the_file_endpoint(): void
    {
        $client = $this->client([new Response(200, [], 'FILEBYTES')]);

        $this->assertSame('FILEBYTES', $client->download('documents/file_1.txt'));
        $this->assertSame('https://api.telegram.org/file/bot123:token/documents/file_1.txt', $this->lastUri());
    }

    public function test_download_honours_the_server_and_test_environment(): void
    {
        $client = $this->client(
            [new Response(200, [], 'x')],
            ['api_url' => 'http://localhost:8081', 'test_environment' => true],
        );

        $client->download('photos/p.jpg');

        $this->assertSame('http://localhost:8081/file/bot123:token/test/photos/p.jpg', $this->lastUri());
    }

    public function test_download_reads_a_local_server_path_straight_from_disk(): void
    {
        // A server run with --local reports an absolute path on its own disk.
        $path = sys_get_temp_dir() . '/devflow_dl_' . uniqid() . '.bin';
        file_put_contents($path, 'LOCALBYTES');

        try {
            $client = $this->client([]);

            $this->assertSame('LOCALBYTES', $client->download($path));
            $this->assertSame([], $this->history, 'no HTTP request should be made');
        } finally {
            unlink($path);
        }
    }

    public function test_a_failed_download_throws_with_the_status(): void
    {
        $client = $this->client([new Response(404, [], 'nope')]);

        try {
            $client->download('documents/gone.txt');
            $this->fail('Expected TelegramApiException.');
        } catch (TelegramApiException $e) {
            $this->assertSame(404, $e->telegramErrorCode());
        }
    }

    // ─── TelegramApi ──────────────────────────────────────────────────────────

    public function test_file_contents_resolves_the_file_id_then_downloads(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getFile', ['file_id' => 'abc', 'file_path' => 'photos/x.jpg']);
        $http->respond('download', 'JPEGBYTES');

        $contents = (new TelegramApi($http))->fileContents('abc');

        $this->assertSame('JPEGBYTES', $contents);
        $this->assertSame('photos/x.jpg', $http->callsTo('download')[0]['params']['file_path']);
    }

    public function test_download_file_saves_to_the_destination_creating_folders(): void
    {
        $http = new FakeHttpClient();
        $http->respond('download', 'SAVED');
        $dest = sys_get_temp_dir() . '/devflow_dl_dir_' . uniqid() . '/nested/out.bin';

        try {
            $returned = (new TelegramApi($http))->downloadFile('abc', $dest);

            $this->assertSame($dest, $returned);
            $this->assertSame('SAVED', file_get_contents($dest));
        } finally {
            @unlink($dest);
            @rmdir(dirname($dest));
            @rmdir(dirname($dest, 2));
        }
    }

    public function test_a_missing_file_path_is_a_clear_error(): void
    {
        $http = new FakeHttpClient();
        $http->respond('getFile', ['file_id' => 'big']);

        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/file_path/');

        (new TelegramApi($http))->fileContents('big');
    }
}
