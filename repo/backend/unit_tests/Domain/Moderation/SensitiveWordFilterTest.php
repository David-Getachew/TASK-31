<?php

declare(strict_types=1);

namespace Tests\Domain\Moderation;

use CampusLearn\Moderation\SensitiveWordFilter;
use CampusLearn\Moderation\SensitiveWordRule;
use PHPUnit\Framework\TestCase;

final class SensitiveWordFilterTest extends TestCase
{
    public function testSubstringMatchCaseInsensitive(): void
    {
        $filter = new SensitiveWordFilter();
        $result = $filter->inspect('Please do not BlockedWord here.', [
            new SensitiveWordRule('blockedword', 'substring'),
        ]);
        $this->assertTrue($result->isBlocked());
        $this->assertSame('blockedword', $result->matches[0]['term']);
    }

    public function testExactRequiresWordBoundary(): void
    {
        $filter = new SensitiveWordFilter();
        $result = $filter->inspect('superblocked word here', [
            new SensitiveWordRule('blocked', 'exact'),
        ]);
        $this->assertCount(1, $result->matches);
        $this->assertSame(5, $result->matches[0]['start']);
    }

    public function testMultipleMatchesOrderedByStart(): void
    {
        $filter = new SensitiveWordFilter();
        $result = $filter->inspect('alpha beta alpha', [
            new SensitiveWordRule('alpha', 'substring'),
            new SensitiveWordRule('beta', 'substring'),
        ]);
        $this->assertCount(3, $result->matches);
        $this->assertSame(0, $result->matches[0]['start']);
        $this->assertSame(6, $result->matches[1]['start']);
        $this->assertSame(11, $result->matches[2]['start']);
    }

    public function testNoMatchReturnsEmpty(): void
    {
        $filter = new SensitiveWordFilter();
        $result = $filter->inspect('benign content', [
            new SensitiveWordRule('danger', 'substring'),
        ]);
        $this->assertFalse($result->isBlocked());
    }

    public function testInactiveRulesIgnored(): void
    {
        $filter = new SensitiveWordFilter();
        $result = $filter->inspect('blocked here', [
            new SensitiveWordRule('blocked', 'substring', isActive: false),
        ]);
        $this->assertFalse($result->isBlocked());
    }
}
