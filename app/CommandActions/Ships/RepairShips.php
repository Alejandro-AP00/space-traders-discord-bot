<?php

namespace App\CommandActions\Ships;

use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait RepairShips
{
    public function repairShipsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('repair')
                ->setDescription('Get the amount of value that will be consumed when repairing a ship.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
        ];
    }

    public function handleRepairShips(Interaction $interaction): void
    {
        $this->repairValue($interaction, $this->value('repair.ship'));
    }

    public function repairShipsInteractions(): array
    {
        return [
            'confirm-repair:{ship}' => fn (Interaction $interaction, string $ship) => $this->confirmRepair($interaction, $ship),
        ];
    }

    public function confirmRepair(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->repairShip($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->title('Repaired '.$shipSymbol)
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $transaction->totalPrice,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ]);

        return $page->editOrReply($interaction);
    }

    public function repairValue(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->repairShipValue($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->title('Repair '.$shipSymbol.'?')
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $transaction->totalPrice,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ])
            ->button('Confirm Repair Ship', style: Button::STYLE_SUCCESS, route: 'confirm-repair:'.$shipSymbol);

        return $page->editOrReply($interaction);
    }
}
