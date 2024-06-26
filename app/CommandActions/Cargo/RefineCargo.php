<?php

namespace App\CommandActions\Cargo;

use AlejandroAPorras\SpaceTraders\Enums\ProduceType;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use AlejandroAPorras\SpaceTraders\Resources\ShipRefineGood;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait RefineCargo
{
    public function refineCargoOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $produce_symbol = (new Option($this->discord()))
            ->setName('produce')
            ->setDescription('The type of good to produce out of the refining process.')
            ->setType(Option::STRING)
            ->setRequired(true);

        foreach (ProduceType::cases() as $produceType) {
            $produce_symbol->addChoice(Choice::new($this->discord(), $produceType->name, $produceType->value));
        }

        return [
            (new Option($this->discord()))
                ->setName('refine')
                ->setDescription('Attempt to refine the raw materials on your ship.')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol)
                ->addOption($produce_symbol),
        ];
    }

    public function handleRefineCargo(Interaction $interaction): void
    {

        $this->refine($interaction, $this->value('refine.ship'), $this->value('refine.produce'));
    }

    public function refine(Interaction $interaction, $shipSymbol, $produceSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $produceSymbol = ProduceType::from($produceSymbol);
            $response = $space_traders->refineShip($shipSymbol, $produceSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Produced' => collect($response['produced'])->map(fn (ShipRefineGood $shipRefineGood) => "[{$shipRefineGood->tradeSymbol->value}]: {$shipRefineGood->units}")->join("\n"),
                'Consumed' => collect($response['consumed'])->map(fn (ShipRefineGood $shipRefineGood) => "[{$shipRefineGood->tradeSymbol->value}]: {$shipRefineGood->units}")->join("\n"),
            ])
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

        return $page->editOrReply($interaction);
    }
}
