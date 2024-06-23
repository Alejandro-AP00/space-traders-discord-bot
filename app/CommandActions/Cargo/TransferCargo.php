<?php

namespace App\CommandActions\Cargo;

use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait TransferCargo
{
    public function transferCargoOptions(): array
    {
        $from_ship_symbol = (new Option($this->discord()))
            ->setName('from_ship')
            ->setDescription('The symbol of the ship you are transferring from')
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

        $to_ship_symbol = (new Option($this->discord()))
            ->setName('to_ship')
            ->setDescription('The symbol of the ship you are transferring to')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('transfer')
                ->setDescription('Transfer cargo between ships.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($from_ship_symbol)
                ->addOption($to_ship_symbol)
                ->addOption($trade_good)
                ->addOption($units),
        ];
    }

    public function handleTransferCargo(Interaction $interaction): void
    {
        $this->transfer(
            $interaction,
            $this->value('transfer.from_ship'),
            $this->value('transfer.to_ship'),
            $this->value('transfer.trade_good'),
            $this->value('transfer.units'),
        );
    }

    public function transferCargoAutocomplete(): array
    {
        return [
            'transfer.trade_good' => function ($interaction, $value) {
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
            },
        ];
    }

    public function transfer(Interaction $interaction, $fromShipSymbol, $toShipSymbol, $tradeGood, $units)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $tradeGood = TradeGoodSymbol::from($tradeGood);
            $response = $space_traders->transferCargo($fromShipSymbol, $tradeGood, $units, $toShipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];

        $page = $this->message()
            ->authorIcon(null)
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
