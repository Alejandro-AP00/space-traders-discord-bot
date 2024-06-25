<?php

namespace App\CommandActions\Navigate;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait ManageDockNavigation
{
    public function manageDockNavigationOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('dock')
                ->setDescription('Commands the ship to dock the current waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
        ];
    }

    public function handleManageDockNavigation(Interaction $interaction, string $shipSymbol): void
    {
        $this->dock($interaction, $shipSymbol);
    }

    public function manageDockNavigationAutocomplete() {}

    public function manageDockNavigationInteractions() {}

    public function dock(Interaction $interaction, string $shipSymbol): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->dockShip($shipSymbol);
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
        )->title('Ship in Dock');

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
