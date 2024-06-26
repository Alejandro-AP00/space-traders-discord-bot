<?php

namespace App\CommandActions\Cargo;

use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use AlejandroAPorras\SpaceTraders\Resources\ShipConditionEvent;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait ExtractCargo
{
    public function extractCargoOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('extract')
                ->setDescription('Extract resources')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
            //            (new Option($this->discord()))
            //                ->setName('extract-survey')
            //                ->setDescription('Extract resources')
            //                ->setType(Option::SUB_COMMAND)
            //                ->addOption($ship_symbol),
            (new Option($this->discord()))
                ->setName('siphon')
                ->setDescription('Siphon resources')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
        ];
    }

    public function handleExtractCargo(Interaction $interaction): void
    {
        $action = $interaction->data->options->first()->name;

        match ($action) {
            'siphon' => $this->siphon($interaction, $this->value('siphon.ship')),
            'extract' => $this->extract($interaction, $this->value('extract.ship')),
        };
    }

    public function extract(Interaction $interaction, string $shipSymbol): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->extractResources($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $extraction = $response['extraction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Extracted Resources')
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields(["\u{200B}" => "\u{200B}"], false)
            ->fields([
                'Yield Type' => $extraction->yield->symbol->value,
                'Yield Amount' => $extraction->yield->units,
            ])
            ->fields([
                "\u{200B}" => "\u{200B}",
                'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            ], false)
            ->fields(["\u{200B}" => "\u{200B}"], false)
            ->fields(collect($response['events'])->mapWithKeys(function (ShipConditionEvent $event) {
                return ["[{$event->symbol->value}]: {$event->component->value} - {$event->name}" => $event->description];
            })->toArray());

        return $page->editOrReply($interaction);
    }

    //    public function extractWithSurvey() {}

    public function siphon(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->siphonResources($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $extraction = $response['siphon'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Siphoned Resources')
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- **%s**: %s', [$cargoItem->symbol->value, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields(["\u{200B}" => "\u{200B}"], false)
            ->fields([
                'Yield Type' => $extraction->yield->symbol->value,
                'Yield Amount' => $extraction->yield->units,
            ])
            ->fields([
                "\u{200B}" => "\u{200B}",
                'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            ], false)
            ->fields(["\u{200B}" => "\u{200B}"], false)
            ->fields(collect($response['events'])->mapWithKeys(function (ShipConditionEvent $event) {
                return ["[{$event->symbol->value}]: {$event->component->value} - {$event->name}" => $event->description];
            })->toArray());

        return $page->editOrReply($interaction);
    }
}
