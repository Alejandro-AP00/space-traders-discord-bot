<?php

namespace App\CommandActions\Ships;

use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait ScrapShips
{
    public function scrapShipsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('scrap')
                ->setDescription('Get the amount of value that will be returned when scrapping a ship.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
        ];
    }

    public function handleScrapShips(Interaction $interaction): void
    {
        $this->scrapValue($interaction, $this->value('scrap.ship'));
    }

    public function scrapShipsInteractions(): array
    {
        return [
            'confirm-scrap:{ship}' => fn (Interaction $interaction, string $ship) => $this->confirmScrap($interaction, $ship),
        ];
    }

    public function confirmScrap(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->scrapShip($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->title('Scrapped '.$shipSymbol)
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $transaction->totalPrice,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ]);

        $page = $this->newBalance($page, $response['agent']);

        return $page->editOrReply($interaction);
    }

    public function scrapValue(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->scrapShipValue($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->title('Scrap '.$shipSymbol.'?')
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $transaction->totalPrice,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ])
            ->button('Confirm Scrap Ship', style: Button::STYLE_DANGER, route: 'confirm-scrap:'.$shipSymbol);

        return $page->editOrReply($interaction);
    }
}
