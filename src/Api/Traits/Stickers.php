<?php

namespace Devflow\TelegramBot\Api\Traits;

trait Stickers
{
    // -------------------------------------------------------------------------
    // Sticker sets
    // -------------------------------------------------------------------------

    public function getStickerSet(string $name): array
    {
        return $this->http->post('getStickerSet', ['name' => $name]);
    }

    public function getCustomEmojiStickers(array $customEmojiIds): array
    {
        return $this->http->post('getCustomEmojiStickers', ['custom_emoji_ids' => $customEmojiIds]);
    }

    public function createNewStickerSet(int $userId, string $name, string $title, array $stickers, array $options = []): bool
    {
        return (bool) $this->http->post('createNewStickerSet', array_merge([
            'user_id'  => $userId,
            'name'     => $name,
            'title'    => $title,
            'stickers' => $stickers,
        ], $options));
    }

    public function setStickerSetTitle(string $name, string $title): bool
    {
        return (bool) $this->http->post('setStickerSetTitle', [
            'name'  => $name,
            'title' => $title,
        ]);
    }

    public function setStickerSetThumbnail(string $name, int $userId, string $format, array $options = []): bool
    {
        return (bool) $this->http->post('setStickerSetThumbnail', array_merge([
            'name'    => $name,
            'user_id' => $userId,
            'format'  => $format,
        ], $options));
    }

    public function setCustomEmojiStickerSetThumbnail(string $name, array $options = []): bool
    {
        return (bool) $this->http->post('setCustomEmojiStickerSetThumbnail', array_merge([
            'name' => $name,
        ], $options));
    }

    public function deleteStickerSet(string $name): bool
    {
        return (bool) $this->http->post('deleteStickerSet', ['name' => $name]);
    }

    // -------------------------------------------------------------------------
    // Sticker set contents
    // -------------------------------------------------------------------------

    public function addStickerToSet(int $userId, string $name, array $sticker): bool
    {
        return (bool) $this->http->post('addStickerToSet', [
            'user_id' => $userId,
            'name'    => $name,
            'sticker' => $sticker,
        ]);
    }

    public function setStickerPositionInSet(string $sticker, int $position): bool
    {
        return (bool) $this->http->post('setStickerPositionInSet', [
            'sticker'  => $sticker,
            'position' => $position,
        ]);
    }

    public function deleteStickerFromSet(string $sticker): bool
    {
        return (bool) $this->http->post('deleteStickerFromSet', ['sticker' => $sticker]);
    }

    public function replaceStickerInSet(int $userId, string $name, string $oldSticker, array $sticker): bool
    {
        return (bool) $this->http->post('replaceStickerInSet', [
            'user_id'     => $userId,
            'name'        => $name,
            'old_sticker' => $oldSticker,
            'sticker'     => $sticker,
        ]);
    }

    // -------------------------------------------------------------------------
    // Sticker properties
    // -------------------------------------------------------------------------

    public function setStickerEmojiList(string $sticker, array $emojiList): bool
    {
        return (bool) $this->http->post('setStickerEmojiList', [
            'sticker'    => $sticker,
            'emoji_list' => $emojiList,
        ]);
    }

    public function setStickerKeywords(string $sticker, array $options = []): bool
    {
        return (bool) $this->http->post('setStickerKeywords', array_merge([
            'sticker' => $sticker,
        ], $options));
    }

    public function setStickerMaskPosition(string $sticker, array $options = []): bool
    {
        return (bool) $this->http->post('setStickerMaskPosition', array_merge([
            'sticker' => $sticker,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // File upload
    // -------------------------------------------------------------------------

    public function uploadStickerFile(int $userId, mixed $sticker, string $stickerFormat): array
    {
        return $this->http->post('uploadStickerFile', [
            'user_id'        => $userId,
            'sticker'        => $sticker,
            'sticker_format' => $stickerFormat,
        ]);
    }
}
