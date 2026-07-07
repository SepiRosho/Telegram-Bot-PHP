<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Types\Update;
use Devflow\TelegramBot\Types\Message;
use Devflow\TelegramBot\Types\CallbackQuery;
use PHPUnit\Framework\TestCase;

class UpdateTypeTest extends TestCase
{
    private function baseMessage(): array
    {
        return [
            'message_id' => 1,
            'date'       => 0,
            'chat'       => ['id' => 100, 'type' => 'private'],
            'from'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
            'text'       => 'hello',
        ];
    }

    public function test_message_update_type(): void
    {
        $update = Update::fromArray([
            'update_id' => 1,
            'message'   => $this->baseMessage(),
        ]);

        $this->assertSame('message', $update->type());
        $this->assertInstanceOf(Message::class, $update->message);
        $this->assertNull($update->callbackQuery);
    }

    public function test_callback_query_update_type(): void
    {
        $update = Update::fromArray([
            'update_id'      => 2,
            'callback_query' => [
                'id'   => 'cq1',
                'from' => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'data' => 'btn_action',
            ],
        ]);

        $this->assertSame('callback_query', $update->type());
        $this->assertInstanceOf(CallbackQuery::class, $update->callbackQuery);
        $this->assertSame('btn_action', $update->callbackQuery->data);
    }

    public function test_poll_update_type(): void
    {
        $update = Update::fromArray([
            'update_id' => 3,
            'poll' => [
                'id'                 => 'poll1',
                'question'           => 'Favourite colour?',
                'options'            => [['text' => 'Red', 'voter_count' => 0]],
                'total_voter_count'  => 0,
                'is_closed'          => false,
                'is_anonymous'       => true,
                'type'               => 'regular',
            ],
        ]);

        $this->assertSame('poll', $update->type());
        $this->assertNotNull($update->poll);
        $this->assertSame('poll1', $update->poll->id);
    }

    public function test_poll_answer_update_type(): void
    {
        $update = Update::fromArray([
            'update_id'   => 4,
            'poll_answer' => [
                'poll_id'    => 'poll1',
                'option_ids' => [0],
                'user'       => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
            ],
        ]);

        $this->assertSame('poll_answer', $update->type());
        $this->assertSame('poll1', $update->pollAnswer?->pollId);
        $this->assertSame([0], $update->pollAnswer?->optionIds);
    }

    public function test_my_chat_member_update_type(): void
    {
        $update = Update::fromArray([
            'update_id'      => 5,
            'my_chat_member' => [
                'chat'            => ['id' => 100, 'type' => 'group'],
                'from'            => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'date'            => 0,
                'old_chat_member' => ['status' => 'left'],
                'new_chat_member' => ['status' => 'member'],
            ],
        ]);

        $this->assertSame('my_chat_member', $update->type());
        $this->assertNotNull($update->myChatMember);
        $this->assertSame('left', $update->myChatMember->oldStatus);
        $this->assertSame('member', $update->myChatMember->newStatus);
        $this->assertTrue($update->myChatMember->userJoined());
    }

    public function test_chat_join_request_update_type(): void
    {
        $update = Update::fromArray([
            'update_id'         => 6,
            'chat_join_request' => [
                'chat' => ['id' => 100, 'type' => 'group'],
                'from' => ['id' => 200, 'is_bot' => false, 'first_name' => 'Ali'],
                'date' => 0,
            ],
        ]);

        $this->assertSame('chat_join_request', $update->type());
        $this->assertNotNull($update->chatJoinRequest);
        $this->assertSame(200, $update->chatJoinRequest->from->id);
    }

    public function test_edited_message_update_type(): void
    {
        $update = Update::fromArray([
            'update_id'      => 7,
            'edited_message' => $this->baseMessage(),
        ]);

        $this->assertSame('edited_message', $update->type());
        $this->assertNotNull($update->editedMessage);
    }
}
