<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Data\AuthPayload;

/** @mixin AuthPayload */
final class AuthPayloadResource extends JsonResource
{
    /**
     * The mergeWhen() call below carries an implicit integer key until JsonResource::filter()
     * (invoked by resolve(), not toArray()) unwraps and removes it.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'token' => $this->token,
            'capabilities' => new UserCapabilitiesResource($this->capabilities),
            // Absent (not null) when the account isn't a Provider — a client checks
            // capabilities.is_provider, not "does the provider key exist and is it null".
            // mergeWhen()'s MergeValue is only unwrapped by JsonResource::filter(), which runs
            // inside resolve() — AuthController calls ->resolve($request) on this resource
            // (not ->toArray()) specifically so this works; verified directly via Pest, not
            // assumed, after a plain ->toArray() call site silently dropped this key once.
            $this->mergeWhen($this->provider !== null, fn () => ['provider' => new ProviderResource($this->provider)]),
        ];
    }
}
