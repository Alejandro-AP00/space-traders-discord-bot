<?php

namespace App\CommandActions\Cargo;

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

        $extraction = $response['extraction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Extracted Resources');

        $page = $this->cargoDetails($page, $response['cargo']);

        $page = $page
            ->fields(["\u{200B}" => "\u{200B}"], false)
            ->fields([
                'Yield Type' => $extraction->yield->symbol->value,
                'Yield Amount' => $extraction->yield->units,
            ])
            ->fields(["\u{200B}" => "\u{200B}"], false);

        $page = $this->cooldown($page, $response['cooldown']);
        $page = $this->shipEvent($page, $response['events']);

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

        $extraction = $response['siphon'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Siphoned Resources');

        $page = $this->cargoDetails($page, $response['cargo']);

        $page = $page
            ->fields(["\u{200B}" => "\u{200B}"], false)
            ->fields([
                'Yield Type' => $extraction->yield->symbol->value,
                'Yield Amount' => $extraction->yield->units,
            ])
            ->fields(["\u{200B}" => "\u{200B}"], false);

        $page = $this->cooldown($page, $response['cooldown']);
        $page = $this->shipEvent($page, $response['events']);

        return $page->editOrReply($interaction);
    }
}
