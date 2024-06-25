<?php

namespace App\CommandActions\Navigate;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait ManageWarpNavigation
{
    public function manageWarpNavigationOptions(): array
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
                ->setName('warp')
                ->setDescription('Warps the ship to the specified waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleManageWarpNavigation(Interaction $interaction, string $shipSymbol): void
    {
        $waypoint = $this->value('warp.waypoint');
        $this->warp($interaction, $shipSymbol, $waypoint);
    }

    public function manageWarpNavigationAutocomplete() {}

    public function manageWarpNavigationInteractions() {}

    public function warp(Interaction $interaction, string $shipSymbol, string $waypointSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->warpShip($shipSymbol, $waypointSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $fuel = $response['fuel'];

        $page = $this->getNav($this->message(), $response['nav'])
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Warping to '.$waypointSymbol)
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Fuel' => $fuel->current.'/'.$fuel->capacity,
                'Fuel Consumed' => isset($fuel->consumed) ? $fuel->consumed->amount.' at '.Date::parse($fuel->consumed->timestamp)->toDiscord() : 'N/A',
            ], false);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
