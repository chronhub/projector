<?php

declare(strict_types=1);

namespace Chronhub\Projector\Tests\Unit\Support\ReadModel;

use stdClass;
use Chronhub\Projector\Tests\TestCase;
use Chronhub\Projector\Support\ReadModel\InteractWithStack;

final class InteractWithStackTest extends TestCase
{
    /**
     * @test
     */
    public function it_stack_operations(): void
    {
        $instance = $this->interactWithStackInstance();

        $this->assertEmpty($instance->getStack());

        $instance->stack('doSomething', 'someValue', 'anotherValue');
        $stackArray = [
            [
                'doSomething',
                ['someValue', 'anotherValue'],
            ],
        ];

        $this->assertEquals($stackArray, $instance->getStack());
    }

    /**
     * @test
     */
    public function it_persist_operations_and_reset_stack(): void
    {
        $instance = $this->interactWithStackInstance();

        $this->assertEmpty($instance->getStack());

        $instance->stack('doSomething', 'someValue', new stdClass());
        $stackArray = [
            [
                'doSomething',
                ['someValue', new stdClass()],
            ],
        ];

        $this->assertEquals($stackArray, $instance->getStack());
        $this->assertEmpty($instance->getValues());

        $instance->persist();

        $this->assertEquals(['someValue', new stdClass()], $instance->getValues());
        $this->assertEmpty($instance->getStack());
    }

    private function interactWithStackInstance(): object
    {
        return new class() {
            use InteractWithStack;

            private array $values = [];

            protected function doSomething(string $firstValue, stdclass $secondValue): void
            {
                $this->values[]= $firstValue;
                $this->values[]= $secondValue;
            }

            public function getValues(): array
            {
                return $this->values;
            }

            public function getStack(): array
            {
                return $this->stack;
            }
        };
    }
}
