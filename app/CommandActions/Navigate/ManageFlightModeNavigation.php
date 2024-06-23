<?php

namespace App\CommandActions\Navigate;

use AlejandroAPorras\SpaceTraders\Enums\ShipNavFlightMode;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Str;

trait ManageFlightModeNavigation
{
    public function manageFlightModeNavigationOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $flight_mode_symbol = (new Option($this->discord()))
            ->setName('flight_mode')
            ->setDescription('The Symbol of the Ship Navigation Flight Mode')
            ->setType(Option::STRING)
            ->setRequired(true);

        foreach (ShipNavFlightMode::cases() as $case) {
            $flight_mode_symbol->addChoice(
                (Choice::new($this->discord(), Str::title($case->name), $case->value)),
            );
        }

        return [
            (new Option($this->discord()))
                ->setName('patch')
                ->setDescription('Patches the navigation of the ship')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($flight_mode_symbol),
        ];
    }

    public function handleManageFlightModeNavigation(Interaction $interaction, string $shipSymbol)
    {
        $flight_mode = $this->value('patch.flight-mode');
        $this->patch($interaction, $shipSymbol, $flight_mode);
    }

    public function manageFlightModeNavigationAutocomplete() {}

    public function manageFlightModeNavigationInteractions() {}

    public function patch(Interaction $interaction, string $shipSymbol, string $flightMode): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $flightMode = ShipNavFlightMode::from($flightMode);
            $response = $space_traders->patchShipNav($shipSymbol, $flightMode);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->getNav($this->message(), $response['nav']);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
