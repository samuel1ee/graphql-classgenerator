<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Tests;

use Aksonov\GraphqlGenerator\Utils\DescriptionProcessor;
use PHPUnit\Framework\TestCase;

final class DescriptionProcessorTest extends TestCase
{
    public function testSingleShortLine(): void
    {
        $result = DescriptionProcessor::processDescription('Hello world');
        $this->assertSame(['Hello world'], $result);
    }

    public function testMultilineSplit(): void
    {
        $result = DescriptionProcessor::processDescription("First line\nSecond line");
        $this->assertSame(['First line', 'Second line'], $result);
    }

    public function testLongLineIsWordWrapped(): void
    {
        $words = array_fill(0, 15, 'word');
        $input = implode(' ', $words); // "word word word ..." (15 words × 5 chars = ~82 chars with spaces)
        $result = DescriptionProcessor::processDescription($input, 30);

        $this->assertGreaterThan(1, count($result));
        foreach ($result as $line) {
            $this->assertLessThanOrEqual(30, mb_strlen($line), "Line '$line' exceeds 30 chars");
        }
        // Reassembled content should equal original words
        $this->assertSame($input, implode(' ', $result));
    }

    public function testEmptyLinesAreFiltered(): void
    {
        $result = DescriptionProcessor::processDescription("\n\n  \n\nHello\n\n");
        $this->assertSame(['Hello'], $result);
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $result = DescriptionProcessor::processDescription('');
        $this->assertSame([], $result);
    }
}
