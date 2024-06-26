<?php

namespace App\CommandActions\Waypoints;

use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use AlejandroAPorras\SpaceTraders\Resources\ConstructionMaterial;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait ManageConstructionWaypoints
{
    public function manageConstructionWaypointOptions(): array
    {
        $system_symbol = (new Option($this->discord()))
            ->setName('system')
            ->setDescription('The Symbol of the system the waypoint is at')
            ->setType(Option::STRING)
            ->setRequired(true);

        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        $construction_details_subcommand = (new Option($this->discord()))
            ->setName('details')
            ->setDescription('Gets the Construction details of a To Be Constructed waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($waypoint_symbol);

        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship that delivers the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        $trade_good = (new Option($this->discord()))
            ->setName('trade_good')
            ->setDescription('The trade good to supply for to the Waypoint')
            ->setType(Option::STRING)
            ->setRequired(true)
            ->setAutoComplete(true);

        $units = (new Option($this->discord()))
            ->setName('units')
            ->setDescription('The number of units of the trade good delivered for the waypoint')
            ->setType(Option::NUMBER)
            ->setRequired(true);

        $construction_supply_subcommand = (new Option($this->discord()))
            ->setName('supply')
            ->setDescription('Supply construction materials to a To Be Constructed waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($system_symbol)
            ->addOption($waypoint_symbol)
            ->addOption($ship_symbol)
            ->addOption($trade_good)
            ->addOption($units);

        return [
            (new Option($this->discord()))
                ->setName('construction')
                ->setDescription('Gets the Construction details of a To Be Constructed waypoint')
                ->setType(Option::SUB_COMMAND_GROUP)
                ->addOption($construction_details_subcommand)
                ->addOption($construction_supply_subcommand),
        ];
    }

    public function handleManageConstructionWaypoints(Interaction $interaction): void
    {
        $subcommand = $interaction->data->options->first()->options->first()->name;

        if ($subcommand === 'details') {
            $this->waypointSymbol = $this->value('construction.details.waypoint');
            $this->constructionDetails($interaction, $this->waypointSymbol);
        }

        if ($subcommand === 'supply') {
            $this->systemSymbol = $this->value('construction.supply.system');
            $this->waypointSymbol = $this->value('construction.supply.waypoint');
            $this->supplyConstruction($interaction, $this->waypointSymbol, $this->value('construction.supply.ship'), $this->value('construction.supply.trade_good'), $this->value('construction.supply.units'));
        }
    }

    public function manageConstructionWaypointInteractions(): array
    {
        return [
            'waypoint-construction:{waypoint}' => fn (Interaction $interaction, string $waypoint) => $this->constructionDetails($interaction, $waypoint),
        ];
    }

    public function manageConstructionWaypointAutocomplete(): array
    {
        return [
            'construction.supply.trade_good' => function ($interaction, $value) {
                return collect(TradeGoodSymbol::cases())
                    ->filter(function (TradeGoodSymbol $trait) use ($value) {
                        if ($value === '' || $value === null) {
                            return true;
                        }

                        return str($trait->value)->lower()->contains(str($value)->lower());
                    })
                    ->map(function (TradeGoodSymbol $trait) {
                        return Choice::new($this->discord, $trait->name, $trait->value);
                    })
                    ->take(25)
                    ->values();
            },
        ];
    }

    public function constructionDetails(Interaction $interaction, string $waypoint): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $construction = $space_traders->construction($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($waypoint)
            ->title('Construction: '.$construction->symbol)
            ->fields([
                'Is complete' => "$construction->isComplete",
                "\u{200B}" => "\u{200B}",
                ...collect($construction->materials)->mapWithKeys(function (ConstructionMaterial $material) {
                    $fulfilled = $material->fulfilled ? '✅' : '❌';

                    return ["[{$material->tradeSymbol->value}]: {$fulfilled}" => 'Required: '.$material->required];
                })->toArray(),
            ]);

        return $page->editOrReply($interaction);
    }

    public function supplyConstruction(Interaction $interaction, string $waypoint, string $ship, string $good, int $units): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $good = TradeGoodSymbol::from($good);
            $response = $space_traders->supplyConstruction($this->systemSymbol, $waypoint, $ship, $good, $units);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $construction = $response['construction'];
        $cargo = $response['cargo'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($waypoint)
            ->title('Construction: '.$construction->symbol)
            ->content(
                "Cargo\n".
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields([
                'Is complete' => "$construction->isComplete",
                "\u{200B}" => "\u{200B}",
                ...collect($construction->materials)->mapWithKeys(function (ConstructionMaterial $material) {
                    $fulfilled = $material->fulfilled ? '✅' : '❌';

                    return ["[{$material->tradeSymbol->value}]: {$fulfilled}" => 'Required: '.$material->required];
                })->toArray(),
            ]);

        return $page->editOrReply($interaction);
    }
}
