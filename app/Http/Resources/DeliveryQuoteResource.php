<?php

namespace App\Http\Resources;

use App\DTOs\DeliveryQuoteDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryQuoteResource extends JsonResource
{
    public function __construct(
        private DeliveryQuoteDTO $quote
    ) {
        parent::__construct($quote);
    }

    public function toArray(Request $request): array
    {
        return $this->quote->toArray();
    }
}
