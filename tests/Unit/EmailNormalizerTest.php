<?php

namespace Tests\Unit;

use App\Support\EmailNormalizer;
use PHPUnit\Framework\TestCase;

class EmailNormalizerTest extends TestCase
{
    public function test_it_trims_and_lowercases_email_addresses(): void
    {
        $this->assertSame(
            'person@example.com',
            EmailNormalizer::normalize('  Person@Example.COM  '),
        );
    }

    public function test_it_returns_null_for_empty_values(): void
    {
        $this->assertNull(EmailNormalizer::normalize(null));
        $this->assertNull(EmailNormalizer::normalize('   '));
    }
}
