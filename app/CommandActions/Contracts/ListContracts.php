<?php

namespace App\CommandActions\Contracts;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait ListContracts
{
    public function listContractsOptions(): array
    {
        return [
            (new Option($this->discord()))
                ->setName('list')
                ->setDescription('List all contracts available to you')
                ->setType(Option::SUB_COMMAND),
        ];
    }

    public function handleListContracts(Interaction $interaction): void
    {
        $this->contracts($interaction);
    }

    public function listContractsAutocomplete() {}

    public function listContractsInteractions(): array
    {
        return [
            'contract:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->contracts($interaction, $page),
        ];
    }

    public function contracts(Interaction $interaction, $page = 1)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $contracts = $space_traders->contracts(['page' => $page, 'limit' => 1]);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->paginate($this->message(), $contracts, "You don't have any contracts", 'contract', fn ($message, $contract) => $this->contractPage($message, $contract));

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }
}
