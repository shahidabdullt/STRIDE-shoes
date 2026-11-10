<?php

namespace App\Assistant\Tools;

use App\Assistant\ToolContext;

class ViewCartTool implements AssistantTool
{
    public function name(): string
    {
        return 'view_cart';
    }

    public function description(): string
    {
        return 'Show the customer\'s cart: items, sizes, quantities, subtotal, shipping and total.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function rules(): array
    {
        return [];
    }

    public function handle(array $args, ToolContext $context): array
    {
        return Presenter::cart($context->cart());
    }
}
