<?php

namespace App\CommandActions\Ships;

use AlejandroAPorras\SpaceTraders\Enums\Deposits;
use AlejandroAPorras\SpaceTraders\Resources\Ship;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use AlejandroAPorras\SpaceTraders\Resources\ShipModule;
use AlejandroAPorras\SpaceTraders\Resources\ShipMount;
use Carbon\Carbon;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laracord\Discord\Message;

trait DetailShips
{
    public function detailsShipsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $detail_subcommand = (new Option($this->discord()))
            ->setName('detail')
            ->setDescription('Gets the details of a waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        return [
            $detail_subcommand,
        ];
    }

    public function handleDetailsShips(Interaction $interaction)
    {
        $this->shipDetails($interaction, $this->value('detail.ship'));
    }

    public function detailsShipsInteractions(): array
    {
        return [
            'ship-details:{ship}:{page?}' => fn (Interaction $interaction, string $ship, $page = null) => $this->shipDetails($interaction, $ship, $interaction->data->values[0] ?? 'General', $page),
        ];
    }

    public function shipDetails($interaction, $shipSymbol, $option = 'General', $pageNumber = 1)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $ship = $space_traders->ship($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($ship->symbol)
            ->select([
                'General',
                'Nav',
                'Frame',
                'Reactor',
                'Engine',
                'Crew',
                'Mounts',
                'Modules',
                'Cargo',
            ], route: 'ship-details:'.$shipSymbol);

        $page = match ($option) {
            'General' => $this->getGeneral($page, $ship),
            'Nav' => $this->getNav($page, $ship),
            'Frame' => $this->getFrame($page, $ship),
            'Reactor' => $this->getReactor($page, $ship),
            'Engine' => $this->getEngine($page, $ship),
            'Crew' => $this->getCrew($page, $ship),
            'Mounts' => $this->getMounts($page, $ship, $pageNumber),
            'Modules' => $this->getModules($page, $ship, $pageNumber),
            'Cargo' => $this->getCargo($page, $ship),
        };

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    private function getMounts(Message $page, Ship $ship, $pageNumber = 1): Message
    {
        return $this->paginateFromArray(
            message: $page,
            results: $ship->mounts,
            emptyMessage: 'No Mounts',
            routeName: "ship-details:{$ship->symbol}",
            callback: function (Message $message, $results) {
                /**
                 * @var $mount ShipMount
                 */
                $mount = $results[0];

                $deposits = collect($mount->deposits)->map(fn (Deposits $deposit) => "{$deposit->value}")->join("\n");

                return $message
                    ->title('Mount '.$mount->name)
                    ->content($mount->description)
                    ->fields([
                        'Symbol' => $mount->symbol->value,
                        'Strength' => $mount->strength,
                        'Deposits' => $deposits,
                    ])
                    ->field('Requirements', "\u{200B}", false)
                    ->fields([
                        'Power' => $mount->requirements->power,
                        'Crew' => $mount->requirements->crew,
                        'Slots' => $mount->requirements->slots,
                    ]);
            },
            page: $pageNumber
        );
    }

    private function getCargo(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Cargo')
            ->content(
                collect($ship->cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $ship->cargo->capacity,
                'Units' => $ship->cargo->units,
            ]);
    }

    private function getModules(Message $page, Ship $ship, $pageNumber = 1): Message
    {
        return $this->paginateFromArray(
            message: $page,
            results: $ship->modules,
            emptyMessage: 'No modules',
            routeName: "ship-details:{$ship->symbol}",
            callback: function (Message $message, $results) {
                /**
                 * @var $module ShipModule
                 */
                $module = $results[0];

                return $message
                    ->title('Mount '.$module->name)
                    ->content($module->description)
                    ->fields([
                        'Symbol' => $module->symbol->value,
                        'Capacity' => $module->capacity ?? 'N/A',
                        'Range' => $module->range ?? 'N/A',
                    ])
                    ->field('Requirements', "\u{200B}", false)
                    ->fields([
                        'Power' => $module->requirements->power,
                        'Crew' => $module->requirements->crew,
                        'Slots' => $module->requirements->slots,
                    ]);
            },
            page: $pageNumber
        );
    }

    private function getGeneral(Message $page, Ship $ship): Message
    {
        return $page
            ->title('General')
            ->fields([
                'Name' => $ship->registration->name,
                'Role' => Str::title($ship->registration->role->value),
                'Faction' => Str::title($ship->registration->factionSymbol->value),
            ])
            ->fields([
                "\u{200B}" => "\u{200B}",
                'Cooldown' => $ship->cooldown->remainingSeconds === 0 ? 'N/A' : $ship->cooldown->remainingSeconds.'s Remaining',
            ], false);
    }

    private function getNav(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Navigation')
            ->fields([
                'System' => $ship->nav->systemSymbol,
                'Waypoint' => $ship->nav->waypointSymbol,
                'Status' => Str::title($ship->nav->status->value),
                'Flight Mode' => Str::title($ship->nav->flightMode->value),
            ])
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Departure' => $ship->nav->route->origin->symbol.' at '.Carbon::parse($ship->nav->route->departureTime)->toDiscord(),
                'Arrival' => $ship->nav->route->destination->symbol.' at '.Carbon::parse($ship->nav->route->arrival)->toDiscord(),
            ]);
    }

    private function getCrew(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Crew')
            ->fields([
                'Current' => $ship->crew->current,
                'Required' => $ship->crew->required,
                'Capacity' => $ship->crew->capacity,
                'Morale' => $ship->crew->morale,
                'Wages' => $ship->crew->wages,
                'Crew Rotation' => Str::title($ship->crew->rotation->value),
            ]);
    }

    private function getFrame(Message $page, Ship $ship): Message
    {
        return $page
            ->authorName($ship->frame->symbol->value)
            ->title($ship->frame->name)
            ->content(
                $ship->frame->description."\n"."**Requirements**\n".
                "Power: {$ship->frame->requirements->power}, Crew: {$ship->frame->requirements->crew}, Slots: {$ship->frame->requirements->slots}")
            ->fields([
                'Condition' => Number::percentage($ship->frame->condition),
                'Integrity' => Number::percentage($ship->frame->integrity),
                'Module Slots' => $ship->frame->moduleSlots,
                'Mounting Points' => $ship->frame->mountingPoints,
                'Fuel Capacity' => $ship->frame->fuelCapacity,
            ]);
    }

    private function getReactor(Message $page, Ship $ship): Message
    {
        return $page
            ->authorName($ship->reactor->symbol->value)
            ->title($ship->reactor->name)
            ->content(
                $ship->reactor->description."\n"."**Requirements**\n".
                "Power: {$ship->reactor->requirements->power}, Crew: {$ship->reactor->requirements->crew}, Slots: {$ship->reactor->requirements->slots}")
            ->fields([
                'Condition' => Number::percentage($ship->reactor->condition),
                'Integrity' => Number::percentage($ship->reactor->integrity),
                'Power Output' => $ship->reactor->powerOutput,
            ]);
    }

    private function getEngine(Message $page, Ship $ship): Message
    {
        return $page
            ->authorName($ship->engine->symbol->value)
            ->title($ship->engine->name)
            ->content(
                $ship->engine->description."\n"."**Requirements**\n".
                "Power: {$ship->engine->requirements->power}, Crew: {$ship->engine->requirements->crew}, Slots: {$ship->engine->requirements->slots}")
            ->fields([
                'Condition' => Number::percentage($ship->engine->condition),
                'Integrity' => Number::percentage($ship->engine->integrity),
                'Speed' => $ship->engine->speed,
            ]);
    }
}
