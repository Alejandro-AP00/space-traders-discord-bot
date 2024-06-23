<?php

namespace App\CommandActions\Contracts;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait DetailContracts
{
    public function detailContractsOptions(): array
    {
        $contract_id = (new Option($this->discord()))
            ->setName('contract')
            ->setDescription('The id of the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('detail')
                ->setDescription('Gets the details of a contract')
                ->setType(Option::SUB_COMMAND)
                ->addOption($contract_id),
        ];
    }

    public function handleDetailContracts(Interaction $interaction): void
    {
        $this->contractId = $this->value('detail.contract');
        $this->contract($interaction, $this->contractId);
    }

    public function detailContractsAutocomplete() {}

    public function detailContractsInteractions() {}

    public function contract(Interaction $interaction, $contractId): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $contract = $space_traders->contract($contractId);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->contractPage($this->message(), $contract);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
