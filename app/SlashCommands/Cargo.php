<?php

namespace App\SlashCommands;

use App\CommandActions\Cargo\ExtractCargo;
use App\CommandActions\Cargo\JettisonCargo;
use App\CommandActions\Cargo\RefineCargo;
use App\CommandActions\Cargo\TradeWithCargo;
use App\CommandActions\Cargo\TransferCargo;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use App\Traits\HasMessageUtils;
use Discord\Parts\Interactions\Interaction;
use Laracord\Commands\SlashCommand;

class Cargo extends SlashCommand
{
    use CanPaginate,
        ExtractCargo,
        HasAgent,
        HasMessageUtils,
        JettisonCargo,
        RefineCargo,
        TradeWithCargo,
        TransferCargo;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'cargo';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The cargo slash command.';

    /**
     * The command options.
     *
     * @var array
     */
    protected $options = [];

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

    public function options(): array
    {
        return [
            ...$this->extractCargoOptions(),
            ...$this->tradeCargoOptions(),
            ...$this->refineCargoOptions(),
            ...$this->transferCargoOptions(),
            ...$this->jettisonCargoOptions(),
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
            'siphon', 'extract' => $this->handleExtractCargo($interaction),
            'sell', 'buy' => $this->handleTradeCargo($interaction),
            'refine' => $this->handleRefineCargo($interaction),
            'transfer' => $this->handleTransferCargo($interaction),
            'jettison' => $this->handleJettisonCargo($interaction),
        };
    }

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [];
    }

    public function autocomplete(): array
    {
        return [
            ...$this->tradeCargoAutocomplete(),
            ...$this->transferCargoAutocomplete(),
            ...$this->jettisonCargoAutocomplete(),
        ];
    }
}
