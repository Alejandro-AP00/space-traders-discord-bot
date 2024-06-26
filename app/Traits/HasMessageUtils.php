<?php

namespace App\Traits;

use AlejandroAPorras\SpaceTraders\Resources\Agent;
use AlejandroAPorras\SpaceTraders\Resources\Cooldown;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargo;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use AlejandroAPorras\SpaceTraders\Resources\ShipConditionEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Laracord\Discord\Message;

trait HasMessageUtils
{
    public function cooldown(Message $message, Cooldown $cooldown): Message
    {
        return $message->fields([
            "\u{200B}" => "\u{200B}",
            'Cooldown' => $cooldown->remainingSeconds === 0 ? 'N/A' : Date::parse($cooldown->expiration)->toDiscord(),
        ], false);
    }

    public function cargoDetails(Message $message, ShipCargo $cargo): Message
    {
        return $message->content(
            collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
            }
            )->join("\n")."\n"
        )->fields([
            'Capacity' => $cargo->capacity,
            'Units' => $cargo->units,
        ]);
    }

    public function shipEvent(Message $message, array|Collection $events): Message
    {
        return $message
            ->fields(
                collect($events)
                    ->mapWithKeys(function (ShipConditionEvent $event) {
                        return ["[{$event->symbol->value}]: {$event->component->value} - {$event->name}" => $event->description];
                    })
                    ->toArray()
            );
    }

    public function newBalance(Message $message, Agent $agent): Message
    {
        return $message->footerText('New Credit Balance: '.$agent->credits);
    }
}
