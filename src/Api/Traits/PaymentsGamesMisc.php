<?php

namespace Devflow\TelegramBot\Api\Traits;

use Devflow\TelegramBot\Types\Message;

trait PaymentsGamesMisc
{
    // -------------------------------------------------------------------------
    // Stars & payments
    // -------------------------------------------------------------------------

    public function createInvoiceLink(string $title, string $description, string $payload, string $currency, array $prices, array $options = []): string
    {
        return (string) $this->http->post('createInvoiceLink', array_merge([
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'currency' => $currency,
            'prices' => $prices,
        ], $options));
    }

    public function getMyStarBalance(): array
    {
        return $this->http->post('getMyStarBalance');
    }

    public function getStarTransactions(array $options = []): array
    {
        return $this->http->post('getStarTransactions', $options);
    }

    public function refundStarPayment(int $userId, string $telegramPaymentChargeId): bool
    {
        return (bool) $this->http->post('refundStarPayment', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
        ]);
    }

    public function editUserStarSubscription(int $userId, string $telegramPaymentChargeId, bool $isCanceled): bool
    {
        return (bool) $this->http->post('editUserStarSubscription', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
            'is_canceled' => $isCanceled,
        ]);
    }

    // -------------------------------------------------------------------------
    // Games
    // -------------------------------------------------------------------------

    public function sendGame(int|string $chatId, string $gameShortName, array $options = []): Message
    {
        return Message::fromArray($this->http->post('sendGame', array_merge([
            'chat_id' => $chatId,
            'game_short_name' => $gameShortName,
        ], $options)));
    }

    public function setGameScore(int $userId, int $score, array $options = []): array
    {
        return $this->http->post('setGameScore', array_merge([
            'user_id' => $userId,
            'score' => $score,
        ], $options));
    }

    public function getGameHighScores(int $userId, array $options = []): array
    {
        return $this->http->post('getGameHighScores', array_merge([
            'user_id' => $userId,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Passport
    // -------------------------------------------------------------------------

    public function setPassportDataErrors(int $userId, array $errors): bool
    {
        return (bool) $this->http->post('setPassportDataErrors', [
            'user_id' => $userId,
            'errors' => $errors,
        ]);
    }

    // -------------------------------------------------------------------------
    // Server maintenance
    // -------------------------------------------------------------------------

    public function close(): bool
    {
        return (bool) $this->http->post('close');
    }

    public function logOut(): bool
    {
        return (bool) $this->http->post('logOut');
    }

    // -------------------------------------------------------------------------
    // Web Apps & prepared messages
    // -------------------------------------------------------------------------

    public function answerWebAppQuery(string $webAppQueryId, array $result): array
    {
        return $this->http->post('answerWebAppQuery', [
            'web_app_query_id' => $webAppQueryId,
            'result' => $result,
        ]);
    }

    public function savePreparedInlineMessage(int $userId, array $result, array $options = []): array
    {
        return $this->http->post('savePreparedInlineMessage', array_merge([
            'user_id' => $userId,
            'result' => $result,
        ], $options));
    }

    public function savePreparedKeyboardButton(int $userId, array $button): array
    {
        return $this->http->post('savePreparedKeyboardButton', [
            'user_id' => $userId,
            'button' => $button,
        ]);
    }

    // -------------------------------------------------------------------------
    // Managed bots
    // -------------------------------------------------------------------------

    public function getManagedBotToken(int $userId): string
    {
        return (string) $this->http->post('getManagedBotToken', [
            'user_id' => $userId,
        ]);
    }

    public function replaceManagedBotToken(int $userId): string
    {
        return (string) $this->http->post('replaceManagedBotToken', [
            'user_id' => $userId,
        ]);
    }

    public function getManagedBotAccessSettings(int $userId): array
    {
        return $this->http->post('getManagedBotAccessSettings', [
            'user_id' => $userId,
        ]);
    }

    public function setManagedBotAccessSettings(int $userId, bool $isAccessRestricted, array $options = []): bool
    {
        return (bool) $this->http->post('setManagedBotAccessSettings', array_merge([
            'user_id' => $userId,
            'is_access_restricted' => $isAccessRestricted,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Personal chats, guest queries & profile
    // -------------------------------------------------------------------------

    public function getUserPersonalChatMessages(int $userId, int $limit): array
    {
        return $this->http->post('getUserPersonalChatMessages', [
            'user_id' => $userId,
            'limit' => $limit,
        ]);
    }

    public function answerGuestQuery(string $guestQueryId, array $result): array
    {
        return $this->http->post('answerGuestQuery', [
            'guest_query_id' => $guestQueryId,
            'result' => $result,
        ]);
    }

    public function setUserEmojiStatus(int $userId, array $options = []): bool
    {
        return (bool) $this->http->post('setUserEmojiStatus', array_merge([
            'user_id' => $userId,
        ], $options));
    }

    public function getUserProfileAudios(int $userId, array $options = []): array
    {
        return $this->http->post('getUserProfileAudios', array_merge([
            'user_id' => $userId,
        ], $options));
    }
}
