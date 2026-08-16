<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit\Rules;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Rules\SaudiOrEgyptianPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaudiOrEgyptianPhoneNumberTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_accepts_valid_saudi_and_egyptian_numbers(string $number): void
    {
        $validator = Validator::make(['phone' => $number], ['phone' => [new SaudiOrEgyptianPhoneNumber]]);

        $this->assertFalse($validator->fails(), "expected \"{$number}\" to pass validation");
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_numbers(string $number): void
    {
        $validator = Validator::make(['phone' => $number], ['phone' => [new SaudiOrEgyptianPhoneNumber]]);

        $this->assertTrue($validator->fails(), "expected \"{$number}\" to fail validation");
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validNumbers(): array
    {
        return [
            'Saudi local' => ['0512345678'],
            'Saudi international +966' => ['+966512345678'],
            'Saudi international 00966' => ['00966512345678'],
            'Saudi with separators' => ['+966 51-234-5678'],
            'Egyptian local, prefix 010' => ['01012345678'],
            'Egyptian local, prefix 011' => ['01112345678'],
            'Egyptian local, prefix 012' => ['01212345678'],
            'Egyptian local, prefix 015' => ['01512345678'],
            'Egyptian international +20' => ['+201012345678'],
            'Egyptian international 0020' => ['00201012345678'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidNumbers(): array
    {
        return [
            'too short' => ['05123456'],
            'too long' => ['051234567890'],
            'Saudi wrong leading digit' => ['0612345678'],
            'Egyptian wrong second digit' => ['01312345678'],
            'UK-shaped number' => ['+447911123456'],
            'not numeric' => ['not-a-phone'],
        ];
    }

    /**
     * A non-implicit rule (this one doesn't implement ImplicitRule) is skipped entirely by
     * Laravel's Validator when the value is blank — that's correct here: whether blank is allowed
     * at all is the `nullable`/`required` rule's job in the FormRequest, not this rule's.
     */
    public function test_blank_value_is_not_rejected_by_this_rule_alone(): void
    {
        $validator = Validator::make(['phone' => ''], ['phone' => [new SaudiOrEgyptianPhoneNumber]]);

        $this->assertFalse($validator->fails());
    }
}
