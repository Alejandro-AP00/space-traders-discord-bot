<?php

namespace App\CommandActions\Contracts;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait AcceptContracts
{
    public function acceptContractsOptions(): array
    {
        $contract_id = (new Option($this->discord()))
            ->setName('contract')
            ->setDescription('The id of the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('accept')
                ->setDescription('Accept a contract')
                ->setType(Option::SUB_COMMAND)
                ->addOption($contract_id),
        ];
    }

    public function handleAcceptContracts(Interaction $interaction): void
    {
        $this->contractId = $this->value('accept.contract');
        $this->acceptContract($interaction, $this->contractId);
    }

    public function acceptContractsAutocomplete() {}

    public function acceptContractsInteractions(): array
    {
        return [
            'contract-accept:{contract}' => fn (Interaction $interaction, string $contractId) => $this->acceptContract($interaction, $contractId),
        ];
    }

    public function acceptContract(Interaction $interaction, $contractId)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->acceptContract($contractId);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->contractPage($this->message(), $response['contract']);
        $page = $this->newBalance($page, $response['agent']);

        return $page->editOrReply($interaction);
    }
}
