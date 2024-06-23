<?php

namespace App\CommandActions\Contracts;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait FulfillContracts
{
    public function fulfillContractsOptions(): array
    {
        $contract_id = (new Option($this->discord()))
            ->setName('contract')
            ->setDescription('The id of the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('fulfill')
                ->setDescription('Fulfill a contract')
                ->setType(Option::SUB_COMMAND)
                ->addOption($contract_id),
        ];
    }

    public function handleFulfillContracts(Interaction $interaction): void
    {
        $this->contractId = $this->value('fulfill.contract');
        $this->fulfillContract($interaction, $this->contractId);
    }

    public function fulfillContractsAutocomplete() {}

    public function fulfillContractsInteractions(): array
    {
        return [
            'contract-fulfill:{contract}' => fn (Interaction $interaction, string $contractId) => $this->fulfillContract($interaction, $contractId),
        ];
    }

    public function fulfillContract(Interaction $interaction, $contractId)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->fulfillContract($contractId);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->contractPage($this->message(), $response['contract'])
            ->authorName('New Credit Balance: '.$response['agent']->credits);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
