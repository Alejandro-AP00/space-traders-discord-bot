<?php

namespace App\CommandActions\Navigate;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait ManageOrbitNavigation
{
    public function manageOrbitNavigationOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('orbit')
                ->setDescription('Commands the ship to orbit the current waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
        ];
    }

    public function handleManageOrbitNavigation(Interaction $interaction, string $shipSymbol)
    {
        $this->orbit($interaction, $shipSymbol);
    }

    public function manageOrbitNavigationAutocomplete() {}

    public function manageOrbitNavigationInteractions() {}

    public function orbit(Interaction $interaction, string $shipSymbol): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->orbitShip($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->getNav(
            $this
                ->message()
                ->authorIcon(null)
                ->authorName($shipSymbol),
            $response['nav']
        )->title('Ship in Orbit');

        return $page->editOrReply($interaction);
    }
}
