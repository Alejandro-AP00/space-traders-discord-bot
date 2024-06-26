<?php

namespace App\CommandActions\Navigate;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Str;

trait ManageJumpNavigation
{
    public function manageJumpNavigationOptions(): array
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
                ->setName('jump')
                ->setDescription('Jumps the ship to the specified waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleManageJumpNavigation(Interaction $interaction, string $shipSymbol): void
    {
        $waypoint = $this->value('jump.waypoint');
        $this->jump($interaction, $shipSymbol, $waypoint);
    }

    public function manageJumpNavigationAutocomplete() {}

    public function manageJumpNavigationInteractions() {}

    public function jump(Interaction $interaction, string $shipSymbol, string $waypointSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->jumpShip($shipSymbol, $waypointSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this
            ->getNav($this->message(), $response['nav'])
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Jumping to '.$waypointSymbol)
            ->fields([
                "\u{200B}" => "\u{200B}",
                'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            ], false)
            ->fields([
                'Ship Symbol' => $response['transaction']->shipSymbol,
                'Trade Good' => Str::title($response['transaction']->tradeSymbol->value),
                'Transaction Type' => Str::title($response['transaction']->type->value),
                'Units' => $response['transaction']->units,
                'Price Per Unit' => $response['transaction']->pricePerUnit,
                'Total Price' => $response['transaction']->totalPrice,
            ]);

        return $page->editOrReply($interaction);
    }
}
