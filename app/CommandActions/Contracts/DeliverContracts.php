<?php

namespace App\CommandActions\Contracts;

use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait DeliverContracts
{
    public function deliverContractsOptions(): array
    {
        $contract_id = (new Option($this->discord()))
            ->setName('contract')
            ->setDescription('The id of the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship that delivers the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        $trade_good = (new Option($this->discord()))
            ->setName('trade_good')
            ->setDescription('The trade good for the contract')
            ->setType(Option::STRING)
            ->setRequired(true)
            ->setAutoComplete(true);

        $units = (new Option($this->discord()))
            ->setName('units')
            ->setDescription('The number of units of the trade good delivered for the contract')
            ->setType(Option::NUMBER)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('deliver')
                ->setDescription('Deliver details of a contract')
                ->setType(Option::SUB_COMMAND)
                ->addOption($contract_id)
                ->addOption($ship_symbol)
                ->addOption($trade_good)
                ->addOption($units),
        ];
    }

    public function handleDeliverContracts(Interaction $interaction): void
    {
        $this->contractId = $this->value('deliver.contract');
        $this->deliverContract(
            $interaction,
            $this->contractId,
            $this->value('deliver.ship'),
            $this->value('deliver.trade_good'),
            $this->value('deliver.units')
        );
    }

    public function deliverContractsAutocomplete(): array
    {
        return [
            'deliver.trade_good' => function ($interaction, $value) {
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

    public function deliverContractsInteractions() {}

    public function deliverContract(Interaction $interaction, string $contractId, string $shipSymbol, $tradeGood, $units): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $tradeGood = TradeGoodSymbol::from($tradeGood);
            $response = $space_traders->deliverContract($contractId, $shipSymbol, $tradeGood, $units);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->contractPage($this->message(), $response['contract']);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
