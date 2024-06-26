<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Resources\ContractDeliverGood;
use App\CommandActions\Contracts\AcceptContracts;
use App\CommandActions\Contracts\DeliverContracts;
use App\CommandActions\Contracts\DetailContracts;
use App\CommandActions\Contracts\FulfillContracts;
use App\CommandActions\Contracts\ListContracts;
use App\CommandActions\Contracts\NegotiateContracts;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use App\Traits\HasMessageUtils;
use Illuminate\Support\Facades\Date;
use Laracord\Commands\SlashCommand;
use Laracord\Discord\Message;

class Contracts extends SlashCommand
{
    use AcceptContracts,
        CanPaginate,
        DeliverContracts,
        DetailContracts,
        FulfillContracts,
        HasAgent,
        HasMessageUtils,
        ListContracts,
        NegotiateContracts;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'contracts';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The contracts slash command.';

    /**
     * The permissions required to use the command.
     *
     * @var array
     */
    protected $permissions = [];

    /**
     * Indicates whether the command requires admin permissions.
     *
     * @var bool
     */
    protected $admin = false;

    /**
     * Indicates whether the command should be displayed in the commands list.
     *
     * @var bool
     */
    protected $hidden = false;

    public string $contractId;

    public function options(): array
    {
        return [
            ...$this->listContractsOptions(),
            ...$this->detailContractsOptions(),
            ...$this->acceptContractsOptions(),
            ...$this->deliverContractsOptions(),
            ...$this->fulfillContractsOptions(),
            ...$this->negotiateContractsOptions(),
        ];
    }

    /**
     * Handle the slash command.
     *
     * @param  \Discord\Parts\Interactions\Interaction  $interaction
     * @return void
     */
    public function handle($interaction)
    {
        $action = $interaction->data->options->first()->name;

        match ($action) {
            'list' => $this->handleListContracts($interaction),
            'detail' => $this->handleDetailContracts($interaction),
            'accept' => $this->handleAcceptContracts($interaction),
            'deliver' => $this->handleDeliverContracts($interaction),
            'fulfill' => $this->handleFulfillContracts($interaction),
            'negotiate' => $this->handleNegotiateContracts($interaction),
        };
    }

    public function interactions(): array
    {
        return [
            ...$this->listContractsInteractions(),
            ...$this->acceptContractsInteractions(),
            ...$this->fulfillContractsInteractions(),
        ];
    }

    public function autocomplete(): array
    {
        return [
            ...$this->deliverContractsAutocomplete(),
        ];
    }

    public function contractPage(Message $message, $contract)
    {
        return $message
            ->authorIcon(null)
            ->footerText($contract->id)
            ->title(str($contract->type->value)->title().' Contract')
            ->fields([
                'Accepted' => $contract->accepted ? 'Yes' : 'No',
                'Fulfilled' => $contract->fulfilled ? 'Yes' : 'No',
                'Faction' => $contract->factionSymbol->value,
            ])
            ->fields([
                'Deadline to Accept' => Date::parse($contract->deadlineToAccept)->toDiscord(),
                'Deadline to Fulfill' => Date::parse($contract->terms->deadline)->toDiscord(),
            ])
            ->content(
                collect($contract->terms->deliver)->map(function (ContractDeliverGood $good) {
                    return vsprintf('- **%s**: %s/%s', [str($good->tradeSymbol->value)->title(),  $good->unitsFulfilled, $good->unitsRequired]);
                }
                )->join("\n")."\n"
            )
            ->button('Accept', disabled: $contract->accepted, route: "contract-accept:{$contract->id}")
            ->button('Fulfill', disabled: $contract->fulfilled, route: "contract-fulfill:{$contract->id}");
    }
}
