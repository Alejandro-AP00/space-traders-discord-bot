<?php

namespace App\CommandActions\Cargo;

use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait TradeWithCargo
{
    public function tradeCargoOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $trade_good = (new Option($this->discord()))
            ->setName('trade_good')
            ->setDescription('The trade good to buy, sell or exchange')
            ->setType(Option::STRING)
            ->setRequired(true)
            ->setAutoComplete(true);

        $units = (new Option($this->discord()))
            ->setName('units')
            ->setDescription('The number of units of the trade good to buy, sell or exchange')
            ->setType(Option::NUMBER)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('sell')
                ->setDescription('Sell cargo in your ship to a market that trades this cargo.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($trade_good)
                ->addOption($units),
            (new Option($this->discord()))
                ->setName('buy')
                ->setDescription('Purchase cargo from a market.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($trade_good)
                ->addOption($units),
        ];
    }

    public function handleTradeCargo(Interaction $interaction): void
    {
        $action = $interaction->data->options->first()->name;

        match ($action) {
            'sell' => $this->sell(
                $interaction,
                $this->value('sell.ship'),
                $this->value('deliver.trade_good'),
                $this->value('deliver.units')
            ),
            'buy' => $this->buy(
                $interaction,
                $this->value('buy.ship'),
                $this->value('deliver.trade_good'),
                $this->value('deliver.units')
            ),
        };
    }

    public function tradeCargoAutocomplete(): array
    {
        $callback = function ($interaction, $value) {
            return collect(TradeGoodSymbol::cases())
                ->filter(function (TradeGoodSymbol $trait) use ($value) {
                    if ($value === '' || $value === null) {
                        return true;
                    }

                    return str($trait->value)->lower()->contains(str($value)->lower());
                })
                ->map(function (TradeGoodSymbol $trait) {
                    return Choice::new($this->discord, $trait->name, $trait->value);
                })
                ->take(25)
                ->values();
        };

        return [
            'sell.trade_good' => $callback,
            'buy.trade_good' => $callback,
        ];
    }

    public function sell(Interaction $interaction, string $shipSymbol, string $tradeGood, int $units)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $tradeGood = TradeGoodSymbol::from($tradeGood);
            $response = $space_traders->purchaseCargo($shipSymbol, $tradeGood, $units);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $response['transaction']->price,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function buy(Interaction $interaction, $shipSymbol, $tradeGood, $units)
    {

        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $tradeGood = TradeGoodSymbol::from($tradeGood);
            $response = $space_traders->sellCargo($shipSymbol, $tradeGood, $units);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $response['transaction']->price,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
