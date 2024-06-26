<?php

namespace App\CommandActions\Contracts;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait NegotiateContracts
{
    public function negotiateContractsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship who should negotiate the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('negotiate')
                ->setDescription('Negotiate a contract using a ship')
                ->setType(Option::SUB_COMMAND)
                ->addOption($ship_symbol),
        ];
    }

    public function handleNegotiateContracts(Interaction $interaction): void
    {
        $this->negotiate($interaction, $this->value('negotiate.ship'));
    }

    public function negotiate(Interaction $interaction, string $shipSymbol): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->negotiateContract($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->contractPage($this->message(), $response['contract']);

        return $page->editOrReply($interaction);
    }
}
