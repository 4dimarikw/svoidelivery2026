<?php

namespace Tests\Unit\Rules;

use Infrastructure\Rules\CronExpressionRule;
use Tests\TestCase;

class CronExpressionRuleTest extends TestCase
{
    private function fails(mixed $value): bool
    {
        $failed = false;
        (new CronExpressionRule)->validate('schedule', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_valid_expression_passes(): void
    {
        $this->assertFalse($this->fails('*/5 * * * *'));
        $this->assertFalse($this->fails('0 3 * * 1'));
    }

    public function test_garbage_fails(): void
    {
        $this->assertTrue($this->fails('не расписание'));
        $this->assertTrue($this->fails('* * * *'));
    }

    public function test_non_string_fails(): void
    {
        $this->assertTrue($this->fails(123));
        $this->assertTrue($this->fails(null));
    }
}
