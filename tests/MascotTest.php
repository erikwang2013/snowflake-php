<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Snowflake\Tests;

use Erikwang2013\Snowflake\Mascot;
use Erikwang2013\Snowflake\Snowflake;
use PHPUnit\Framework\TestCase;

class MascotTest extends TestCase
{
    /** Mirrors the SVG forms shipped in docs/pet-expressions/. */
    private const FORMS = [
        'smile',
        'joy',
        'cry',
        'depressed',
        'terrified',
        'angry',
        'coquettish',
        'cute',
        'pleasing',
        'knocking',
        'facepalm',
        'tongue',
    ];

    public function testAllSvgFormsAreExposedInCode(): void
    {
        $this->assertSame(self::FORMS, array_keys(Snowflake::MASCOT_EXPRESSIONS));
        $this->assertSame(self::FORMS, Mascot::names());
    }

    public function testEveryFormHasAMatchingSvg(): void
    {
        $files = glob(__DIR__ . '/../docs/pet-expressions/*.svg') ?: [];

        $this->assertCount(count(self::FORMS), $files);
        foreach (self::FORMS as $form) {
            $this->assertFileExists(__DIR__ . '/../docs/pet-expressions/' . $form . '.svg');
        }
    }

    public function testEveryFormIsATerminalBanner(): void
    {
        foreach (Snowflake::MASCOT_EXPRESSIONS as $form => $art) {
            $this->assertMatchesRegularExpression(
                '/\A[\x09\x0A\x20-\x7E]*\z/',
                $art,
                $form . ' must be pure ASCII'
            );
            $this->assertStringContainsString('snowflake-php', $art, $form . ' must carry the banner');
            $this->assertGreaterThan(6, substr_count($art, "\n"), $form . ' must be multi-line');
            $this->assertSame($art, rtrim($art), $form . ' must not end in blank space');

            $lines = explode("\n", $art);
            $this->assertSame('------' . $this->face($form) . '------', trim($lines[3]), $form . ' face');
        }

        $this->assertCount(
            count(self::FORMS),
            array_unique(Snowflake::MASCOT_EXPRESSIONS),
            'Every form must look different'
        );
    }

    public function testMascotReturnsTheRequestedForm(): void
    {
        $this->assertSame(Snowflake::MASCOT, Snowflake::mascot());
        $this->assertSame(Snowflake::MASCOT_EXPRESSIONS['smile'], Snowflake::mascot('smile'));
        $this->assertSame(Snowflake::MASCOT_EXPRESSIONS['joy'], Snowflake::mascot('joy'));
        $this->assertSame(Mascot::EXPRESSIONS['tongue'], Snowflake::mascot('tongue'));
    }

    public function testUnknownFormIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown mascot expression "dance". Available: smile, joy, cry');

        Snowflake::mascot('dance');
    }

    private function face(string $form): string
    {
        return [
            'smile' => '(^_^)',
            'joy' => '\^o^/',
            'cry' => '(T_T)',
            'depressed' => '(-_-)',
            'terrified' => '(O_O)',
            'angry' => '(>_<)',
            'coquettish' => '(^_~)',
            'cute' => '(^o^)',
            'pleasing' => '(^.^)',
            'knocking' => '(>_O)',
            'facepalm' => '(@_@)',
            'tongue' => '(^q^)',
        ][$form];
    }
}
