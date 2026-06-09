<?php

namespace Devflow\TelegramBot\Middleware;

use Devflow\TelegramBot\Context;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxHits = 10,
        private int $windowSeconds = 60,
        private string $message = 'Too many requests. Please slow down.',
    ) {}

    public function handle(Context $ctx, callable $next): void
    {
        $user = $ctx->user();

        if ($user === null) {
            $next($ctx);
            return;
        }

        $now  = time();
        $hits = is_array($user->rate_hits) ? $user->rate_hits : [];
        $hits = array_values(array_filter($hits, fn($t) => $now - $t < $this->windowSeconds));

        if (count($hits) >= $this->maxHits) {
            $ctx->reply($this->message);
            return;
        }

        $hits[] = $now;
        $user->rate_hits = $hits;
        $user->save();

        $next($ctx);
    }
}
