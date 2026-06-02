<?php

namespace Devflow\TelegramBot\Laravel\Facades;

use Devflow\TelegramBot\BotInstance;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Devflow\TelegramBot\Types\Message sendMessage(int|string $chatId, string $text, array $options = [])
 * @method static \Devflow\TelegramBot\Types\Message sendPhoto(int|string $chatId, string $photo, array $options = [])
 * @method static \Devflow\TelegramBot\Types\Message sendDocument(int|string $chatId, string $document, array $options = [])
 * @method static bool deleteMessage(int|string $chatId, int $messageId)
 * @method static bool answerCallbackQuery(string $callbackQueryId, array $options = [])
 * @method static \Devflow\TelegramBot\BotInstance onCommand(string $command, callable|string $handler)
 * @method static \Devflow\TelegramBot\BotInstance onText(callable|string $handler)
 * @method static \Devflow\TelegramBot\BotInstance onCallbackQuery(string|callable $patternOrHandler, callable|string|null $handler = null)
 * @method static \Devflow\TelegramBot\BotInstance use(callable|string $middleware)
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
