<?php

declare(strict_types=1);

use AmoDocGenerator\DocumentDataBuilder;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testEntryScriptsExist(): void
    {
        $this->assertFileExists(__DIR__ . '/../api/generate.php');
        $this->assertFileExists(__DIR__ . '/../api/prefill.php');
        $this->assertFileExists(__DIR__ . '/../oauth.php');
    }

    public function testAutoloaderLoadsProjectClasses(): void
    {
        $instance = new DocumentDataBuilder();
        $this->assertInstanceOf(DocumentDataBuilder::class, $instance);
    }
}

