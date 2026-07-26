<?php

namespace Devflow\TelegramBot\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Composer never installs a dependency's require-dev packages into a consuming
 * project, so anything under src/ that references PHPUnit is a fatal error
 * waiting to happen in every project that installs this library — which is
 * exactly what Bot::fake() used to do, breaking the whole documented testing
 * workflow on its first assertion.
 */
class ProductionDependencyTest extends TestCase
{
    public function test_no_production_source_file_references_phpunit(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            // Testing\Assertions is the one sanctioned reference: it is guarded
            // by class_exists() and falls back to a plain exception.
            if (str_contains($source, 'PHPUnit\\') && !str_contains($source, 'class_exists(\\PHPUnit')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'Production source must not hard-depend on phpunit/phpunit (require-dev).');
    }
}
