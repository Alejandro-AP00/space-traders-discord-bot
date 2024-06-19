<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use AlejandroAPorras\SpaceTraders\Resources\ContractDeliverGood;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;
use Laracord\Commands\SlashCommand;

class Contracts extends SlashCommand
{
    use CanPaginate, HasAgent;

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

    /**
     * Handle the slash command.
     *
     * @param  \Discord\Parts\Interactions\Interaction  $interaction
     * @return void
     */
    public function handle($interaction)
    {
        $action = $interaction->data->options->first()->name;

        if ($action === 'list') {
            $this->contracts($interaction);
        }
        if ($action === 'detail') {
            $this->contractId = $this->value('detail.contract');
            $this->contract($interaction, $this->contractId);
        }
        if ($action === 'accept') {
            $this->contractId = $this->value('accept.contract');
            $this->acceptContract($interaction, $this->contractId);
        }
        if ($action === 'deliver') {
            $this->contractId = $this->value('deliver.contract');
            $this->deliverContract(
                $interaction,
                $this->contractId,
                $this->value('deliver.ship'),
                $this->value('deliver.trade_good'),
                $this->value('deliver.units')
            );
        }
        if ($action === 'fulfill') {
            $this->contractId = $this->value('fulfill.contract');
            $this->fulfillContract($interaction, $this->contractId);
        }
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

    public function contract(Interaction $interaction, $contractId)
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

    public function acceptContract(Interaction $interaction, $contractId)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->acceptContract($contractId);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->contractPage($this->message(), $response['contract'])
            ->authorName('New Credit Balance: '.$response['agent']->credits);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function deliverContract(Interaction $interaction, string $contractId, string $shipSymbol, $tradeGood, $units)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $tradeGood = TradeGoodSymbol::from($tradeGood);
            $response = $space_traders->deliverContract($contractId, $shipSymbol, $tradeGood, $units);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }
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

    public function contractPage($message, $contract)
    {
        return $message
            ->authorIcon(null)
            ->authorName(null)
            ->footerText($contract->id)
            ->title(str($contract->type->value)->title().' Contract')
            ->fields([
                'Accepted' => $contract->accepted ? 'Yes' : 'No',
                'Fulfilled' => $contract->fulfilled ? 'Yes' : 'No',
                'Faction' => $contract->factionSymbol->value,
            ])
            ->fields([
                'Expires at' => Date::parse($contract->expiration)->format('Y-m-d H:i:s'),
                'Deadline to Accept' => Date::parse($contract->deadlineToAccept)->format('Y-m-d H:i:s'),
                'Deadline to Fulfill' => Date::parse($contract->terms->deadline)->format('Y-m-d H:i:s'),
            ])
            ->content(
                collect($contract->terms->deliver)->map(function (ContractDeliverGood $good) {
                    return vsprintf('- **%s**: %s/%s', [str($good->tradeSymbol->value)->title(),  $good->unitsFulfilled, $good->unitsRequired]);
                }
                )->join("\n")."\n"
            )
            ->button('Accept', route: 'contract-accept:{contract}');
    }

    public function autocomplete(): array
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

    public function options(): array
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

        $detail_subcommand = (new Option($this->discord()))
            ->setName('detail')
            ->setDescription('Gets the details of a contract')
            ->setType(Option::SUB_COMMAND)
            ->addOption($contract_id);

        $list_subcommand = (new Option($this->discord()))
            ->setName('list')
            ->setDescription('List all contracts available to you')
            ->setType(Option::SUB_COMMAND);

        $accept_subcommand = (new Option($this->discord()))
            ->setName('accept')
            ->setDescription('Accept a contract')
            ->setType(Option::SUB_COMMAND)
            ->addOption($contract_id);

        $deliver_subcommand = (new Option($this->discord()))
            ->setName('deliver')
            ->setDescription('Deliver details of a contract')
            ->setType(Option::SUB_COMMAND)
            ->addOption($contract_id)
            ->addOption($ship_symbol)
            ->addOption($trade_good)
            ->addOption($units);

        $fulfill_subcommand = (new Option($this->discord()))
            ->setName('fulfill')
            ->setDescription('Fulfill a contract')
            ->setType(Option::SUB_COMMAND)
            ->addOption($contract_id);

        return [
            $list_subcommand,
            $detail_subcommand,
            $accept_subcommand,
            $deliver_subcommand,
            $fulfill_subcommand,
            // Negotiate {Ship}
        ];
    }

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [
            'contract:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->contracts($interaction, $page),
            'contract-accept:{contract}' => fn (Interaction $interaction, string $contractId) => $this->acceptContract($interaction, $contractId),
        ];
    }
}
