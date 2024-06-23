<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Resources\ShipNav;
use App\CommandActions\Navigate\ManageDockNavigation;
use App\CommandActions\Navigate\ManageFlightModeNavigation;
use App\CommandActions\Navigate\ManageJumpNavigation;
use App\CommandActions\Navigate\ManageOrbitNavigation;
use App\CommandActions\Navigate\ManageWarpNavigation;
use App\CommandActions\Navigate\ManageWaypointNavigation;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use Carbon\Carbon;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Str;
use Laracord\Commands\SlashCommand;
use Laracord\Discord\Message;

class Navigate extends SlashCommand
{
    use CanPaginate,
        HasAgent,
        ManageDockNavigation,
        ManageFlightModeNavigation,
        ManageJumpNavigation,
        ManageOrbitNavigation,
        ManageWarpNavigation,
        ManageWaypointNavigation;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'navigate';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The navigate slash command.';

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
            ...$this->manageOrbitNavigationOptions(),
            ...$this->manageDockNavigationOptions(),
            ...$this->manageWaypointNavigationOptions(),
            ...$this->manageWarpNavigationOptions(),
            ...$this->manageJumpNavigationOptions(),
            ...$this->manageFlightModeNavigationOptions(),
        ];
    }

    /**
     * Handle the slash command.
     *
     * @param  Interaction  $interaction
     */
    public function handle($interaction): void
    {
        $action = $interaction->data->options->first()->name;
        $shipSymbol = $this->value($action.'.ship');

        match ($action) {
            'orbit' => $this->handleManageOrbitNavigation($interaction, $shipSymbol),
            'dock' => $this->handleManageDockNavigation($interaction, $shipSymbol),
            'to' => $this->handleManageWaypointNavigation($interaction, $shipSymbol),
            'warp' => $this->handleManageWarpNavigation($interaction, $shipSymbol),
            'jump' => $this->handleManageJumpNavigation($interaction, $shipSymbol),
            'patch' => $this->handleManageFlightModeNavigation($interaction, $shipSymbol),
        };
    }

    private function getNav(Message $page, ShipNav $nav): Message
    {
        return $page
            ->fields([
                'System' => $nav->systemSymbol,
                'Waypoint' => $nav->waypointSymbol,
                'Status' => Str::title($nav->status->value),
                'Flight Mode' => Str::title($nav->flightMode->value),
            ])
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Departure' => $nav->route->origin->symbol.' at '.Carbon::parse($nav->route->departureTime)->toDiscord(),
                'Arrival' => $nav->route->destination->symbol.' at '.Carbon::parse($nav->route->arrival)->toDiscord(),
            ]);
    }
}
