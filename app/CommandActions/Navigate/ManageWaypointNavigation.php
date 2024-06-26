<?php

namespace App\CommandActions\Navigate;

use AlejandroAPorras\SpaceTraders\Resources\ShipConditionEvent;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait ManageWaypointNavigation
{
    public function manageWaypointNavigationOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('to')
                ->setDescription('Navigates the ship to the specified waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleManageWaypointNavigation(Interaction $interaction, string $shipSymbol): void
    {
        $waypoint = $this->value('to.waypoint');
        $this->to($interaction, $shipSymbol, $waypoint);
    }

    public function manageWaypointNavigationAutocomplete() {}

    public function manageWaypointNavigationInteractions() {}

    public function to(Interaction $interaction, string $shipSymbol, string $waypointSymbol): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->navigateShip($shipSymbol, $waypointSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $fuel = $response['fuel'];

        $page = $this->getNav($this->message(), $response['nav'])
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Navigating to '.$waypointSymbol)
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Fuel' => $fuel->current.'/'.$fuel->capacity,
                'Fuel Consumed' => isset($fuel->consumed) ? $fuel->consumed->amount.' at '.Date::parse($fuel->consumed->timestamp)->toDiscord() : 'N/A',
            ], false)
            ->field("\u{200B}", "\u{200B}", false)
            ->fields(collect($response['events'])->mapWithKeys(function (ShipConditionEvent $event) {
                return ["[{$event->symbol->value}]: {$event->component->value} - {$event->name}" => $event->description];
            })->toArray());

        return $page->editOrReply($interaction);
    }
}
