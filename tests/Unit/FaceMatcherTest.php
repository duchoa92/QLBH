<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FaceMatcher;
use Tests\TestCase;

class FaceMatcherTest extends TestCase
{
    public function test_sface_uses_native_cosine_and_requires_exactly_128_values(): void
    {
        $first = array_fill(0, FaceMatcher::SFACE_SIZE, 0.0);
        $same = array_fill(0, FaceMatcher::SFACE_SIZE, 0.0);
        $other = array_fill(0, FaceMatcher::SFACE_SIZE, 0.0);
        $first[0] = $same[0] = 1.0;
        $other[1] = 1.0;

        $this->assertSame(1.0, FaceMatcher::similarity($first, $same, FaceMatcher::SFACE));
        $this->assertSame(0.0, FaceMatcher::similarity($first, $other, FaceMatcher::SFACE));
        $this->assertFalse(FaceMatcher::validDescriptor(array_fill(0, 127, 0.0), FaceMatcher::SFACE));
    }

    public function test_vectors_from_different_engines_are_not_accepted_as_sface(): void
    {
        $humanSized = array_fill(0, 1024, 0.1);

        $this->assertTrue(FaceMatcher::validDescriptor($humanSized, FaceMatcher::HUMAN));
        $this->assertFalse(FaceMatcher::validDescriptor($humanSized, FaceMatcher::SFACE));
        $this->assertSame(0.0, FaceMatcher::similarity($humanSized, $humanSized, FaceMatcher::SFACE));
    }
}
