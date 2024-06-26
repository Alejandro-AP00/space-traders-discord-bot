<?php

namespace App\SlashCommands;

use App\CommandActions\Ships\DetailShips;
use App\CommandActions\Ships\ListShips;
use App\CommandActions\Ships\ManageMountsShips;
use App\CommandActions\Ships\ManageScanShips;
use App\CommandActions\Ships\RefuelShips;
use App\CommandActions\Ships\RepairShips;
use App\CommandActions\Ships\ScrapShips;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use App\Traits\HasMessageUtils;
use Laracord\Commands\SlashCommand;

class Ships extends SlashCommand
{
    use CanPaginate,
        DetailShips,
        HasAgent,
        HasMessageUtils,
        ListShips,
        ManageMountsShips,
        ManageScanShips,
        RefuelShips,
        RepairShips,
        ScrapShips;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'ships';

    protected $description = 'The ships slash command.';

    protected $permissions = [];

    protected $admin = false;

    protected $hidden = false;

    public function options(): array
    {

        return [
            ...$this->detailsShipsOptions(),
            ...$this->listShipsOptions(),
            ...$this->manageScanShipsOptions(),
            ...$this->manageMountsShipsOptions(),
            ...$this->scrapShipsOptions(),
            ...$this->repairShipsOptions(),
            ...$this->refuelShipsOptions(),
        ];
    }

    /**
     * Handle the slash command.
     *
     * @param  \Discord\Parts\Interactions\Interaction  $interaction
     */
    public function handle($interaction): void
    {
        $action = $interaction->data->options->first()->name;

        match ($action) {
            'list' => $this->handleListShips($interaction),
            'detail' => $this->handleDetailsShips($interaction),
            'scan' => $this->handleManageScanShips($interaction),
            'mounts' => $this->handleManageMountsShips($interaction),
            'scrap' => $this->handleScrapShips($interaction),
            'repair' => $this->handleRepairShips($interaction),
            'refuel' => $this->handleRefuelShips($interaction),
        };
    }

    public function interactions(): array
    {
        return [
            ...$this->listShipsInteractions(),
            ...$this->detailsShipsInteractions(),
            ...$this->manageScanShipsInteractions(),
            ...$this->scrapShipsInteractions(),
            ...$this->repairShipsInteractions(),
        ];
    }
}
