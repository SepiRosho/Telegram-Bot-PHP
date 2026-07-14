<?php

namespace Devflow\TelegramBot\Tests\Unit;

use Devflow\TelegramBot\Console\Commands\BroadcastRunCommand;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class BroadcastRunCommandTest extends TestCase
{
    private string $projectDir;
    private string $originalCwd;
    private string $bootstrapFile;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->projectDir = sys_get_temp_dir() . '/devflow_broadcast_test_' . uniqid();
        mkdir($this->projectDir . '/bootstrap', 0777, true);
        $this->bootstrapFile = $this->projectDir . '/bootstrap/app.php';

        // A real file (not :memory:) so state persists whether this bootstrap
        // is require()'d by the test's own setup or, separately, again inside
        // BroadcastRunCommand::execute(). hasTable() guards make each
        // require idempotent either way.
        $dbFile = $this->projectDir . '/database.sqlite';
        touch($dbFile);

        // See TelegramUserTest::resetGuardableColumnsCache() — other test
        // classes create a `telegram_users` table with a different column
        // set under this same model class, so Eloquent's per-class
        // guardable-columns cache must be cleared before each test.
        $modelRef = new \ReflectionClass(\Illuminate\Database\Eloquent\Model::class);
        $guardableProp = $modelRef->getProperty('guardableColumns');
        $guardableProp->setAccessible(true);
        $guardableProp->setValue(null, []);

        file_put_contents($this->bootstrapFile, <<<PHP
        <?php
        use Illuminate\\Database\\Capsule\\Manager as Capsule;
        use Devflow\\TelegramBot\\Api\\FakeHttpClient;
        use Devflow\\TelegramBot\\Bot;
        use Devflow\\TelegramBot\\BotInstance;

        \$capsule = new Capsule();
        \$capsule->addConnection(['driver' => 'sqlite', 'database' => '{$dbFile}']);
        \$capsule->setAsGlobal();
        \$capsule->bootEloquent();

        if (!Capsule::schema()->hasTable('telegram_users')) {
            Capsule::schema()->create('telegram_users', function (\$table) {
                \$table->id();
                \$table->unsignedBigInteger('telegram_id')->unique();
                \$table->bigInteger('chat_id');
                \$table->string('first_name');
                \$table->boolean('is_active')->default(true);
                \$table->boolean('is_banned')->default(false);
            });
        }

        if (!Capsule::schema()->hasTable('telegram_broadcasts')) {
            Capsule::schema()->create('telegram_broadcasts', function (\$table) {
                \$table->id();
                \$table->text('message');
                \$table->string('type', 50)->default('text');
                \$table->string('media')->nullable();
                \$table->json('options')->nullable();
                \$table->string('status', 20)->default('pending');
                \$table->unsignedInteger('total_recipients')->default(0);
                \$table->unsignedInteger('sent_count')->default(0);
                \$table->unsignedInteger('failed_count')->default(0);
                \$table->unsignedBigInteger('notify_chat_id')->nullable();
                \$table->timestamp('scheduled_at')->nullable();
                \$table->timestamp('started_at')->nullable();
                \$table->timestamp('completed_at')->nullable();
                \$table->timestamps();
            });
        }

        // Wires a FakeHttpClient as the active Bot facade instance (so
        // BroadcastRunCommand's Bot::send*() calls hit no real network) and
        // stashes it in a global so the test can inspect what was "sent"
        // after execute() returns.
        \$GLOBALS['__test_http'] = \$http = new FakeHttpClient();
        \$botInstance = new BotInstance('fake-token', ['database' => true], \$http);

        \$ref = new \\ReflectionClass(Bot::class);
        \$prop = \$ref->getProperty('instance');
        \$prop->setAccessible(true);
        \$prop->setValue(null, \$botInstance);
        PHP);

        chdir($this->projectDir);

        // Set up the DB/tables once up front so the test can seed rows
        // before execute() (which will require this same file again).
        require $this->bootstrapFile;
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (Capsule::connection() !== null) {
            Capsule::connection()->disconnect();
        }

        unset($GLOBALS['__test_http']);
        @unlink($this->projectDir . '/database.sqlite');
        @unlink($this->bootstrapFile);
        @rmdir($this->projectDir . '/bootstrap');
        @rmdir($this->projectDir);
    }

    public function test_broadcasts_a_photo_using_the_type_column(): void
    {
        Capsule::table('telegram_users')->insert([
            ['telegram_id' => 1, 'chat_id' => 100, 'first_name' => 'Ali'],
            ['telegram_id' => 2, 'chat_id' => 200, 'first_name' => 'Reza'],
        ]);
        Capsule::table('telegram_broadcasts')->insert([
            'message' => '',
            'type' => 'photo',
            'media' => 'file_id_abc',
            'options' => json_encode(['caption' => 'Look at this!']),
            'status' => 'pending',
        ]);

        ob_start();
        (new BroadcastRunCommand())->execute([]);
        ob_get_clean();

        $calls = $GLOBALS['__test_http']->callsTo('sendPhoto');
        $this->assertCount(2, $calls);
        $this->assertSame('file_id_abc', $calls[0]['params']['photo']);
        $this->assertSame('Look at this!', $calls[0]['params']['caption']);

        $broadcast = Capsule::table('telegram_broadcasts')->first();
        $this->assertSame('completed', $broadcast->status);
        $this->assertSame(2, $broadcast->sent_count);
    }

    public function test_notifies_admin_chat_on_completion(): void
    {
        Capsule::table('telegram_users')->insert([
            ['telegram_id' => 1, 'chat_id' => 100, 'first_name' => 'Ali'],
        ]);
        Capsule::table('telegram_broadcasts')->insert([
            'message' => 'hello everyone',
            'type' => 'text',
            'status' => 'pending',
            'notify_chat_id' => 999,
        ]);

        ob_start();
        (new BroadcastRunCommand())->execute([]);
        ob_get_clean();

        $notifyCalls = array_values(array_filter(
            $GLOBALS['__test_http']->callsTo('sendMessage'),
            fn(array $c) => ($c['params']['chat_id'] ?? null) === 999,
        ));

        $this->assertNotEmpty($notifyCalls);
        $this->assertStringContainsString('Sent: 1', $notifyCalls[0]['params']['text']);
    }
}
