<?php

namespace App\SlashCommands;

use App\CommandActions\Waypoints\DetailWaypoints;
use App\CommandActions\Waypoints\ListWaypoints;
use App\CommandActions\Waypoints\ManageConstructionWaypoints;
use App\CommandActions\Waypoints\ManageJumpGateWaypoints;
use App\CommandActions\Waypoints\ManageMarketWaypoints;
use App\CommandActions\Waypoints\ManageShipyardWaypoints;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use Laracord\Commands\SlashCommand;

class Waypoints extends SlashCommand
{
    use CanPaginate,
        DetailWaypoints,
        HasAgent,
        ListWaypoints,
        ManageConstructionWaypoints,
        ManageJumpGateWaypoints,
        ManageMarketWaypoints,
        ManageShipyardWaypoints;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'waypoints';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The waypoint slash command.';

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

    private string $systemSymbol;

    private ?string $waypointTrait = null;

    private ?string $waypointType = null;

    private ?string $waypointSymbol = null;

    public function options(): array
    {

        return [
            ...$this->listWaypointsOptions(),
            ...$this->detailWaypointsOptions(),
            ...$this->manageShipyardWaypointsOptions(),
            ...$this->manageMarketWaypointsOptions(),
            ...$this->manageJumpGateWaypointsOptions(),
            ...$this->manageConstructionWaypointOptions(),
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
            'list' => $this->handleListWaypoints($interaction),
            'detail' => $this->handleDetailWaypoints($interaction),
            'shipyard' => $this->handleManageShipyardWaypoints($interaction),
            'jump-gate' => $this->handleManageJumpGateWaypoints($interaction),
            'market' => $this->handleManageMarketWaypoints($interaction),
            'construction' => $this->handleManageConstructionWaypoints($interaction),
        };
    }

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [
            ...$this->listWaypointInteractions(),
            ...$this->detailWaypointInteractions(),
            ...$this->manageShipyardWaypointsInteractions(),
            ...$this->manageMarketWaypointsInteractions(),
            ...$this->manageJumpGateWaypointsInteractions(),
        ];
    }

    public function autocomplete(): array
    {
        return [
            ...$this->listWaypointAutocomplete(),
            ...$this->manageConstructionWaypointAutocomplete(),
        ];
    }
}
