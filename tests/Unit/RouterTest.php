<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Api\HttpClientInterface;
use Devflow\TelegramBot\Api\TelegramApi;
use Devflow\TelegramBot\Routing\Router;
use Devflow\TelegramBot\Types\Update;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function makeApi(): TelegramApi
    {
        $http = $this->createMock(HttpClientInterface::class);
        return new TelegramApi($http);
    }

    private function messageUpdate(string $text, int $updateId = 1): Update
    {
        $entities = [];
        if (str_starts_with($text, '/')) {
            $length = strpos($text, ' ');
            $entities[] = ['type' => 'bot_command', 'offset' => 0, 'length' => $length === false ? strlen($text) : $length];
        }

        return Update::fromArray([
            'update_id' => $updateId,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'text'       => $text,
                'entities'   => $entities,
            ],
        ]);
    }

    private function photoUpdate(int $updateId = 1): Update
    {
        return Update::fromArray([
            'update_id' => $updateId,
            'message'   => [
                'message_id' => 1,
                'date'       => 0,
                'chat'       => ['id' => 100, 'type' => 'private'],
                'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'photo'      => [['file_id' => 'p1', 'width' => 10, 'height' => 10]],
            ],
        ]);
    }

    private function callbackUpdate(string $data, int $updateId = 1): Update
    {
        return Update::fromArray([
            'update_id'      => $updateId,
            'callback_query' => [
                'id'      => 'cq1',
                'from'    => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'data'    => $data,
                'message' => [
                    'message_id' => 5,
                    'date'       => 0,
                    'chat'       => ['id' => 100, 'type' => 'private'],
                    'from'       => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'],
                ],
            ],
        ]);
    }

    public function test_command_route_matches_slash_command(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('command', 'start', function () use (&$called) { $called = true; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertTrue($called);
    }

    public function test_command_route_does_not_match_other_commands(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('command', 'help', function () use (&$called) { $called = true; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertFalse($called);
    }

    public function test_text_route_matches_non_command_text(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('text', '*', function () use (&$called) { $called = true; });

        $router->dispatch($this->messageUpdate('Hello'), $this->makeApi());

        $this->assertTrue($called);
    }

    public function test_text_route_does_not_match_commands(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('text', '*', function () use (&$called) { $called = true; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertFalse($called);
    }

    public function test_callback_query_exact_match(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('callback_query', 'btn_yes', function () use (&$called) { $called = true; });

        $router->dispatch($this->callbackUpdate('btn_yes'), $this->makeApi());

        $this->assertTrue($called);
    }

    public function test_callback_query_wildcard_match(): void
    {
        $router = new Router();
        $matched = null;
        $router->addRoute('callback_query', 'admin_*', function ($ctx) use (&$matched) {
            $matched = $ctx->callbackData();
        });

        $router->dispatch($this->callbackUpdate('admin_users'), $this->makeApi());

        $this->assertSame('admin_users', $matched);
    }

    public function test_callback_query_wildcard_does_not_match_different_prefix(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('callback_query', 'admin_*', function () use (&$called) { $called = true; });

        $router->dispatch($this->callbackUpdate('user_profile'), $this->makeApi());

        $this->assertFalse($called);
    }

    public function test_first_matching_route_wins(): void
    {
        $router = new Router();
        $log = [];
        $router->addRoute('command', 'start', function () use (&$log) { $log[] = 'first'; });
        $router->addRoute('command', 'start', function () use (&$log) { $log[] = 'second'; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertSame(['first'], $log);
    }

    public function test_middleware_runs_before_handler(): void
    {
        $router = new Router();
        $log = [];

        $router->addMiddleware(function ($ctx, $next) use (&$log) {
            $log[] = 'middleware';
            $next($ctx);
        });

        $router->addRoute('command', 'start', function () use (&$log) { $log[] = 'handler'; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertSame(['middleware', 'handler'], $log);
    }

    public function test_middleware_can_short_circuit(): void
    {
        $router = new Router();
        $called = false;

        $router->addMiddleware(function ($ctx, $next) {
            // intentionally does not call $next
        });

        $router->addRoute('command', 'start', function () use (&$called) { $called = true; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertFalse($called);
    }

    private function fakeUserRepository(string $step): object
    {
        return new class($step) {
            public function __construct(private string $step) {}
            public function findOrCreateByUpdate($update): object
            {
                return new class($this->step) {
                    public function __construct(public string $step) {}
                    public $temp_data = null;
                    public function save(): void {}
                };
            }
        };
    }

    public function test_step_route_defaults_to_text_only(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('step', 'compose', function () use (&$called) { $called = true; });

        $router->dispatch($this->photoUpdate(), $this->makeApi(), [], $this->fakeUserRepository('compose'));

        $this->assertFalse($called);
    }

    public function test_step_route_matches_configured_media_type(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('step', 'compose', function () use (&$called) { $called = true; }, ['text', 'photo']);

        $router->dispatch($this->photoUpdate(), $this->makeApi(), [], $this->fakeUserRepository('compose'));

        $this->assertTrue($called);
    }

    public function test_step_route_wildcard_matches_any_type(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('step', 'compose', function () use (&$called) { $called = true; }, ['*']);

        $router->dispatch($this->photoUpdate(), $this->makeApi(), [], $this->fakeUserRepository('compose'));

        $this->assertTrue($called);
    }

    public function test_unknown_command_route_does_not_match_a_registered_command(): void
    {
        $router = new Router();
        $unknownCalled = false;
        $router->addRoute('unknown_command', '*', function () use (&$unknownCalled) { $unknownCalled = true; });
        $router->addRoute('command', 'start', function () {});

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertFalse($unknownCalled);
    }

    public function test_unknown_command_route_matches_an_unregistered_command(): void
    {
        $router = new Router();
        $unknownCalled = false;
        $router->addRoute('unknown_command', '*', function () use (&$unknownCalled) { $unknownCalled = true; });
        $router->addRoute('command', 'start', function () {});

        $router->dispatch($this->messageUpdate('/mystery'), $this->makeApi());

        $this->assertTrue($unknownCalled);
    }

    public function test_unknown_command_matching_is_independent_of_registration_order(): void
    {
        // onUnknownCommand registered BEFORE the specific command route —
        // should still not fire for /start, unlike an onMessage catch-all.
        $router = new Router();
        $unknownCalled = false;
        $startCalled = false;
        $router->addRoute('unknown_command', '*', function () use (&$unknownCalled) { $unknownCalled = true; });
        $router->addRoute('command', 'start', function () use (&$startCalled) { $startCalled = true; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertFalse($unknownCalled);
        $this->assertTrue($startCalled);
    }

    // -------------------------------------------------------------------------
    // Step-route priority
    // -------------------------------------------------------------------------

    public function test_step_route_wins_over_a_text_route_registered_before_it(): void
    {
        // The registration order a developer reaches for naturally — a handler
        // group owning a catch-all onText loaded before the group owning the
        // wizard — used to swallow every mid-flow message silently.
        $router = new Router();
        $textCalled = false;
        $stepCalled = false;
        $router->addRoute('text', '*', function () use (&$textCalled) { $textCalled = true; });
        $router->addRoute('step', 'compose', function () use (&$stepCalled) { $stepCalled = true; });

        $router->dispatch($this->messageUpdate('hello'), $this->makeApi(), [], $this->fakeUserRepository('compose'));

        $this->assertTrue($stepCalled);
        $this->assertFalse($textCalled);
    }

    public function test_text_route_still_wins_when_the_user_is_not_in_that_step(): void
    {
        $router = new Router();
        $textCalled = false;
        $stepCalled = false;
        $router->addRoute('text', '*', function () use (&$textCalled) { $textCalled = true; });
        $router->addRoute('step', 'compose', function () use (&$stepCalled) { $stepCalled = true; });

        $router->dispatch($this->messageUpdate('hello'), $this->makeApi(), [], $this->fakeUserRepository('other'));

        $this->assertTrue($textCalled);
        $this->assertFalse($stepCalled);
    }

    public function test_command_still_escapes_an_active_step(): void
    {
        $router = new Router();
        $stepCalled = false;
        $cancelCalled = false;
        $router->addRoute('step', 'compose', function () use (&$stepCalled) { $stepCalled = true; });
        $router->addRoute('command', 'cancel', function () use (&$cancelCalled) { $cancelCalled = true; });

        $router->dispatch($this->messageUpdate('/cancel'), $this->makeApi(), [], $this->fakeUserRepository('compose'));

        $this->assertTrue($cancelCalled);
        $this->assertFalse($stepCalled);
    }

    public function test_wildcard_step_route_does_not_match_a_user_with_no_active_step(): void
    {
        $router = new Router();
        $stepCalled = false;
        $textCalled = false;
        $router->addRoute('step', '*', function () use (&$stepCalled) { $stepCalled = true; });
        $router->addRoute('text', '*', function () use (&$textCalled) { $textCalled = true; });

        $router->dispatch($this->messageUpdate('hello'), $this->makeApi(), [], $this->fakeUserRepository(''));

        $this->assertFalse($stepCalled);
        $this->assertTrue($textCalled);
    }

    public function test_wildcard_step_route_matches_any_active_step(): void
    {
        $router = new Router();
        $stepCalled = false;
        $router->addRoute('step', '*', function () use (&$stepCalled) { $stepCalled = true; });

        $router->dispatch($this->messageUpdate('hello'), $this->makeApi(), [], $this->fakeUserRepository('anything'));

        $this->assertTrue($stepCalled);
    }

    public function test_step_priority_can_be_disabled_by_config(): void
    {
        $router = new Router();
        $textCalled = false;
        $stepCalled = false;
        $router->addRoute('text', '*', function () use (&$textCalled) { $textCalled = true; });
        $router->addRoute('step', 'compose', function () use (&$stepCalled) { $stepCalled = true; });

        $router->dispatch(
            $this->messageUpdate('hello'),
            $this->makeApi(),
            ['step_routes_first' => false],
            $this->fakeUserRepository('compose'),
        );

        $this->assertTrue($textCalled);
        $this->assertFalse($stepCalled);
    }

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------

    public function test_route_middleware_runs_only_for_its_own_route(): void
    {
        $router = new Router();
        $guarded = 0;
        $middleware = function ($ctx, $next) use (&$guarded) { $guarded++; $next($ctx); };

        $router->addRoute('command', 'admin', fn() => null, middleware: [$middleware]);
        $router->addRoute('command', 'start', fn() => null);

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());
        $this->assertSame(0, $guarded);

        $router->dispatch($this->messageUpdate('/admin'), $this->makeApi());
        $this->assertSame(1, $guarded);
    }

    public function test_route_middleware_can_short_circuit_its_handler(): void
    {
        $router = new Router();
        $handlerCalled = false;
        $deny = function () { /* never calls $next */ };

        $router->addRoute('command', 'admin', function () use (&$handlerCalled) { $handlerCalled = true; }, middleware: [$deny]);

        $router->dispatch($this->messageUpdate('/admin'), $this->makeApi());

        $this->assertFalse($handlerCalled);
    }

    public function test_global_middleware_wraps_route_middleware_which_wraps_the_handler(): void
    {
        $router = new Router();
        $order = [];

        $router->addMiddleware(function ($ctx, $next) use (&$order) { $order[] = 'global'; $next($ctx); });
        $router->addRoute(
            'command',
            'admin',
            function () use (&$order) { $order[] = 'handler'; },
            middleware: [function ($ctx, $next) use (&$order) { $order[] = 'route'; $next($ctx); }],
        );

        $router->dispatch($this->messageUpdate('/admin'), $this->makeApi());

        $this->assertSame(['global', 'route', 'handler'], $order);
    }

    public function test_middleware_interface_instance_is_dispatched_via_handle(): void
    {
        // MiddlewareInterface declares handle(), not __invoke(), so an instance
        // is not callable — Bot::use(new RateLimitMiddleware(...)), the form
        // every doc page shows, used to fail before it ever ran.
        $router = new Router();
        $handlerCalled = false;

        $middleware = new class implements \Devflow\TelegramBot\Middleware\MiddlewareInterface {
            public bool $ran = false;

            public function handle(\Devflow\TelegramBot\Context $ctx, callable $next): void
            {
                $this->ran = true;
                $next($ctx);
            }
        };

        $router->addMiddleware($middleware);
        $router->addRoute('command', 'start', function () use (&$handlerCalled) { $handlerCalled = true; });

        $router->dispatch($this->messageUpdate('/start'), $this->makeApi());

        $this->assertTrue($middleware->ran);
        $this->assertTrue($handlerCalled);
    }

    // -------------------------------------------------------------------------
    // Unrouted callback queries
    // -------------------------------------------------------------------------

    public function test_unmatched_callback_query_is_auto_answered(): void
    {
        $http = new \Devflow\TelegramBot\Api\FakeHttpClient();
        $router = new Router();

        $router->dispatch($this->callbackUpdate('reports_noop'), new TelegramApi($http));

        $calls = $http->callsTo('answerCallbackQuery');
        $this->assertCount(1, $calls);
        $this->assertSame('cq1', $calls[0]['params']['callback_query_id']);
    }

    public function test_matched_callback_query_is_not_auto_answered(): void
    {
        $http = new \Devflow\TelegramBot\Api\FakeHttpClient();
        $router = new Router();
        $router->addRoute('callback_query', 'reports_*', fn() => null);

        $router->dispatch($this->callbackUpdate('reports_page_2'), new TelegramApi($http));

        $this->assertSame([], $http->callsTo('answerCallbackQuery'));
    }

    public function test_auto_answer_can_be_disabled_by_config(): void
    {
        $http = new \Devflow\TelegramBot\Api\FakeHttpClient();
        $router = new Router();

        $router->dispatch($this->callbackUpdate('reports_noop'), new TelegramApi($http), ['auto_answer_callbacks' => false]);

        $this->assertSame([], $http->callsTo('answerCallbackQuery'));
    }
}
