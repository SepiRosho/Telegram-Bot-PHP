<?php

namespace Devflow\TelegramBot\Middleware;

use Devflow\TelegramBot\Context;

class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param string|\Closure(Context): string $message  A closure is resolved
     *        per request, which is the only way to localize the block notice:
     *        the constructor runs at registration time, long before any
     *        Context — and therefore any resolvable locale — exists.
     */
    public function __construct(
        protected int $maxHits = 10,
        protected int $windowSeconds = 60,
        protected string|\Closure $message = 'Too many requests. Please slow down.',
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
            $ctx->reply($this->resolveMessage($ctx));
            return;
        }

        $hits[] = $now;
        $user->rate_hits = $hits;
        $user->save();

        $next($ctx);
    }

    protected function resolveMessage(Context $ctx): string
    {
        return $this->message instanceof \Closure
            ? ($this->message)($ctx)
            : $this->message;
    }
}
