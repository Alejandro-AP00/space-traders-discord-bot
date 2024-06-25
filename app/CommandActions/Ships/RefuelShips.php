<?php

namespace App\CommandActions\Ships;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

trait RefuelShips
{
    public function refuelShipsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $units = (new Option($this->discord()))
            ->setName('units')
            ->setDescription("The amount of fuel to fill in the ship's tanks.")
            ->setType(Option::STRING);

        $from_cargo = (new Option($this->discord()))
            ->setName('from_cargo')
            ->setDescription("Whether to use the FUEL that's in your cargo or not. ")
            ->setType(Option::BOOLEAN);

        return [
            (new Option($this->discord()))
                ->setName('refuel')
                ->setDescription('Refuel your ship by buying fuel from the local market.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($units)
                ->addOption($from_cargo),
        ];
    }

    public function handleRefuelShips(Interaction $interaction): void
    {
        $this->refuel($interaction, $this->value('refuel.ship'), $this->value('refuel.units'), $this->value('refuel.from_cargo'));
    }

    public function refuel(Interaction $interaction, string $shipSymbol, ?string $units, ?bool $fromCargo)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->refuelShip($shipSymbol, $units, $fromCargo);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $transaction = $response['transaction'];
        $fuel = $response['fuel'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->title('Refueled '.$shipSymbol)
            ->fields([
                'Fuel' => $fuel->current.'/'.$fuel->capacity,
                'Fuel Consumed' => isset($fuel->consumed) ? $fuel->consumed->amount.' at '.Date::parse($fuel->consumed->timestamp)->toDiscord() : 'N/A',
            ], false)
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $transaction->totalPrice,
                'Trade' => Str::title($transaction->tradeSymbol->value),
                'Type' => $transaction->type->value,
                'Units' => $transaction->units,
                'Price per unit' => $transaction->pricePerUnit,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
