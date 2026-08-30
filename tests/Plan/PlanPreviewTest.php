<?php

declare(strict_types=1);

namespace Yaup\Tests\Plan;

use PHPUnit\Framework\TestCase;
use Yaup\Plan\PlanPreview;

final class PlanPreviewTest extends TestCase
{
    public function testRendersFallbackForPlanWithoutChanges(): void
    {
        $preview = (new PlanPreview())->render([
            'status' => 'draft',
            'summary' => 'Only a summary.',
        ]);

        self::assertStringContainsString('Status: draft', $preview);
        self::assertStringContainsString('Summary: Only a summary.', $preview);
        self::assertStringContainsString('Intended changes:', $preview);
        self::assertStringContainsString('- No changes listed.', $preview);
    }

    public function testRendersUnknownChangeShapesAsJson(): void
    {
        $preview = (new PlanPreview())->render([
            'changes' => [
                ['owner' => 'team', 'files' => ['README.md']],
            ],
        ]);

        self::assertStringContainsString('- {"owner":"team","files":["README.md"]}', $preview);
    }
}
