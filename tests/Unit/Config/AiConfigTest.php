<?php

declare(strict_types=1);

describe('config/ai.php', function (): void {
    it('has a provider key', function (): void {
        expect(config()->has('ai.provider'))->toBeTrue();
    });

    it('has an api_key key', function (): void {
        expect(config()->has('ai.api_key'))->toBeTrue();
    });

    it('has a model key', function (): void {
        expect(config()->has('ai.model'))->toBeTrue();
    });
});
