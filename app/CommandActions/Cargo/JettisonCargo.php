<?php

namespace App\CommandActions\Cargo;

use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait JettisonCargo
{
    public function jettisonCargoOptions(): array
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
                ->setName('jettison')
                ->setDescription("Jettison cargo from your ship's cargo hold.")
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($trade_good)
                ->addOption($units),
        ];
    }

    public function handleJettisonCargo(Interaction $interaction): void
    {

        $this->jettison(
            $interaction,
            $this->value('jettison.ship'),
            $this->value('jettison.trade_good'),
            $this->value('jettison.units'),
        );
    }

    public function jettisonCargoAutocomplete(): array
    {
        return [
            'jettison.trade_good' => function ($interaction, $value) {
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

    public function jettison(Interaction $interaction, string $shipSymbol, string $tradeGood, string $units)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $tradeGood = TradeGoodSymbol::from($tradeGood);
            $response = $space_traders->jettisonCargo($shipSymbol, $tradeGood, $units);
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
