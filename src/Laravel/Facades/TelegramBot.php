<?php

namespace Devflow\TelegramBot\Laravel\Facades;

use Devflow\TelegramBot\BotInstance;
use Illuminate\Support\Facades\Facade;

/**
 * Telegram API calls (proxied to TelegramApi — any other Bot API method works too):
 * @method static \Devflow\TelegramBot\Types\Message sendMessage(int|string $chatId, string $text, array $options = [])
 * @method static \Devflow\TelegramBot\Types\Message sendPhoto(int|string $chatId, string $photo, array $options = [])
 * @method static \Devflow\TelegramBot\Types\Message sendDocument(int|string $chatId, string $document, array $options = [])
 * @method static bool deleteMessage(int|string $chatId, int $messageId)
 * @method static bool answerCallbackQuery(string $callbackQueryId, array $options = [])
 *
 * Routing:
 * @method static \Devflow\TelegramBot\BotInstance onCommand(string $command, callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onText(string|callable $patternOrHandler, callable|string|null $handler = null, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onMessage(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onCallbackQuery(string|callable $patternOrHandler, callable|string|null $handler = null, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onPhoto(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onDocument(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onInlineQuery(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onChosenInlineResult(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onEditedMessage(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onChannelPost(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onEditedChannelPost(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onPoll(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onPollAnswer(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onMyChatMember(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onChatMember(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onChatJoinRequest(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onShippingQuery(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onPreCheckoutQuery(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onUpdate(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onBusinessConnection(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onBusinessMessage(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onEditedBusinessMessage(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onDeletedBusinessMessages(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onGuestMessage(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onMessageReaction(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onMessageReactionCount(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onPurchasedPaidMedia(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onChatBoost(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onRemovedChatBoost(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onManagedBot(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onStep(string $step, callable|string $handler, array $types = ['text'], array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance onUnknownCommand(callable|string $handler, array $middleware = [])
 * @method static \Devflow\TelegramBot\BotInstance loadHandlers(array|string $handlers)
 * @method static \Devflow\TelegramBot\BotInstance use(callable|string|\Devflow\TelegramBot\Middleware\MiddlewareInterface $middleware)
 * @method static \Devflow\TelegramBot\BotInstance chatTypes(array $chatTypes, callable $register)
 *
 * Instance:
 * @method static \Devflow\TelegramBot\Api\TelegramApi api()
 * @method static mixed config(string $key, mixed $default = null)
 * @method static string username()
 * @method static void handleWebhook(string $payload, ?string $secretToken = null)
 * @method static void run()
 *
 * @see \Devflow\TelegramBot\BotInstance
 */
class TelegramBot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BotInstance::class;
    }
}
