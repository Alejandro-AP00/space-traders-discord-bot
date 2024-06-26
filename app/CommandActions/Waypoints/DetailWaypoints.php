<?php

namespace App\CommandActions\Waypoints;

use AlejandroAPorras\SpaceTraders\Enums\WaypointTraitSymbol;
use AlejandroAPorras\SpaceTraders\Enums\WaypointType;
use AlejandroAPorras\SpaceTraders\Resources\WaypointModifier;
use AlejandroAPorras\SpaceTraders\Resources\WaypointTrait;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait DetailWaypoints
{
    public function detailWaypointsOptions(): array
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

        return [
            (new Option($this->discord()))
                ->setName('detail')
                ->setDescription('Gets the details of a waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($system_symbol)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleDetailWaypoints(Interaction $interaction): void
    {
        $this->systemSymbol = $this->value('detail.system');
        $this->waypointSymbol = $this->value('detail.waypoint');
        $this->waypoint($interaction, $this->waypointSymbol);
    }

    public function detailWaypointInteractions(): array
    {
        return [
            'waypoint-details:{waypoint}' => fn (Interaction $interaction, string $waypoint) => $this->waypoint($interaction, $waypoint, $interaction->data->values[0] ?? 'General'),
        ];
    }

    private function waypoint(Interaction $interaction, string $waypoint, $option = 'General')
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $waypoint = $space_traders->waypoint($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this
            ->message()
            ->authorIcon(null)
            ->authorName('System: '.$waypoint->systemSymbol)
            ->title($waypoint->symbol)
            ->select([
                'General',
                'Traits',
                'Modifiers',
            ], route: "waypoint-details:{$waypoint->symbol}")
            ->timestamp();

        if (collect($waypoint->traits)->contains(fn (WaypointTrait $trait) => $trait->symbol === WaypointTraitSymbol::SHIPYARD)) {
            $page->button('Shipyard', route: "waypoint-shipyard:{$waypoint->symbol}");
        }

        if (collect($waypoint->traits)->contains(fn (WaypointTrait $trait) => $trait->symbol === WaypointTraitSymbol::MARKETPLACE)) {
            $page->button('Market', route: "waypoint-market:{$waypoint->symbol}");
        }

        if ($waypoint->type === WaypointType::JUMP_GATE) {
            $page->button('Jump Gate', route: "waypoint-jump-gate:{$waypoint->symbol}");
        }

        if ($waypoint->isUnderConstruction) {
            $page->button('Construction Details', route: "waypoint-construction:{$waypoint->symbol}");
        }

        $page = match ($option) {
            'General' => $page->fields([
                'Type' => $waypoint->type->value,
                'Faction' => $waypoint->faction->symbol->value,
                'Under Construction?' => $waypoint->isUnderConstruction ? 'Yes' : 'No',
                "\u{200B}" => "\u{200B}",
            ], false)
                ->fields([
                    'X' => $waypoint->x,
                    'Y' => $waypoint->y,
                ]),
            'Traits' => $page->fields(
                collect($waypoint->traits)->mapWithKeys(function (WaypointTrait $trait) {
                    return [$trait->name => $trait->description];
                }
                )->toArray(),
                false
            )
                ->field("\u{200B}", "\u{200B}", false),
            'Modifiers' => $page->fields(
                collect($waypoint->modifiers)->mapWithKeys(function (WaypointModifier $trait) {
                    return [$trait->name => $trait->description];
                }
                )->toArray(),
                false
            )
                ->field("\u{200B}", "\u{200B}", false),
        };

        return $page->editOrReply($interaction);
    }
}
