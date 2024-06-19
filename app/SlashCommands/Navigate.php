<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Enums\ShipNavFlightMode;
use AlejandroAPorras\SpaceTraders\Resources\ShipConditionEvent;
use AlejandroAPorras\SpaceTraders\Resources\ShipNav;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use Carbon\Carbon;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laracord\Commands\SlashCommand;
use Laracord\Discord\Message;

class Navigate extends SlashCommand
{
    use CanPaginate, HasAgent;

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
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        $flight_mode_symbol = (new Option($this->discord()))
            ->setName('flight_mode')
            ->setDescription('The Symbol of the Ship Navigation Flight Mode')
            ->setType(Option::STRING)
            ->setRequired(true);

        foreach (ShipNavFlightMode::cases() as $case) {
            $flight_mode_symbol->addChoice(
                (Choice::new($this->discord(), Str::title($case->name), $case->value)),
            );
        }

        $orbit_subcommand = (new Option($this->discord()))
            ->setName('orbit')
            ->setDescription('Commands the ship to orbit the current waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $dock_subcommand = (new Option($this->discord()))
            ->setName('dock')
            ->setDescription('Commands the ship to dock the current waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $to_subcommand = (new Option($this->discord()))
            ->setName('to')
            ->setDescription('Navigates the ship to the specified waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol)
            ->addOption($waypoint_symbol);

        $warp_subcommand = (new Option($this->discord()))
            ->setName('warp')
            ->setDescription('Warps the ship to the specified waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol)
            ->addOption($waypoint_symbol);

        $jump_subcommand = (new Option($this->discord()))
            ->setName('jump')
            ->setDescription('Jumps the ship to the specified waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol)
            ->addOption($waypoint_symbol);

        $patch_subcommand = (new Option($this->discord()))
            ->setName('patch')
            ->setDescription('Patches the navigation of the ship')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol)
            ->addOption($flight_mode_symbol);

        return [
            $orbit_subcommand,
            $dock_subcommand,
            $to_subcommand,
            $warp_subcommand,
            $jump_subcommand,
            $patch_subcommand,
        /**
         * Orbit
         * Dock
         * To
         * Warp
         * Jump
         * Patch
         */
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
        $shipSymbol = $this->value($action.'.ship');

        if ($action === 'orbit') {
            $this->orbit($interaction, $shipSymbol);
        }
        if ($action === 'dock') {
            $this->dock($interaction, $shipSymbol);
        }
        if ($action === 'to') {
            $waypoint = $this->value($action.'.waypoint');
            $this->to($interaction, $shipSymbol, $waypoint);
        }
        if ($action === 'warp') {
            $waypoint = $this->value($action.'.waypoint');
            $this->warp($interaction, $shipSymbol, $waypoint);
        }
        if ($action === 'jump') {
            $waypoint = $this->value($action.'.waypoint');
            $this->jump($interaction, $shipSymbol, $waypoint);
        }
        if ($action === 'patch') {
            $flight_mode = $this->value($action.'.flight-mode');
            $this->patch($interaction, $shipSymbol, $flight_mode);
        }
    }

    public function orbit(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->orbitShip($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->getNav($this->message(), $response['nav'])->title('Ship in Orbit');

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function dock(Interaction $interaction, string $shipSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->dockShip($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->getNav($this->message(), $response['nav'])->title('Ship in Dock');

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function to(Interaction $interaction, string $shipSymbol, string $waypointSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->navigateShip($shipSymbol, $waypointSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $fuel = $response['fuel'];

        $page = $this->getNav($this->message(), $response['nav'])
            ->title('Navigating to '.$waypointSymbol)
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Fuel' => $fuel->current.'/'.$fuel->capacity,
                'Fuel Consumed' => isset($fuel->consumed) ? $fuel->consumed->amount.' at '.Date::parse($fuel->consumed->timestamp)->toDiscord() : 'N/A',
            ], false)
            ->field("\u{200B}", "\u{200B}", false)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->fields(collect($response['events'])->mapWithKeys(function (ShipConditionEvent $event) {
                return ["[{$event->symbol->value}]: {$event->component->value} - {$event->name}" => $event->description];
            })->toArray());

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function warp(Interaction $interaction, string $shipSymbol, string $waypointSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->warpShip($shipSymbol, $waypointSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $fuel = $response['fuel'];

        $page = $this->getNav($this->message(), $response['nav'])
            ->title('Warping to '.$waypointSymbol)
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Fuel' => $fuel->current.'/'.$fuel->capacity,
                'Fuel Consumed' => isset($fuel->consumed) ? $fuel->consumed->amount.' at '.Date::parse($fuel->consumed->timestamp)->toDiscord() : 'N/A',
            ], false);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function jump(Interaction $interaction, string $shipSymbol, string $waypointSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->jumpShip($shipSymbol, $waypointSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this
            ->getNav($this->message(), $response['nav'])
            ->title('Jumping to '.$waypointSymbol)
            ->fields([
                "\u{200B}" => "\u{200B}",
                'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            ], false)
            ->fields([
                'Ship Symbol' => $response['transaction']->shipSymbol,
                'Trade Good' => Str::title($response['transaction']->tradeSymbol->value),
                'Transaction Type' => Str::title($response['transaction']->type->value),
                'Units' => $response['transaction']->units,
                'Price Per Unit' => $response['transaction']->pricePerUnit,
                'Total Price' => $response['transaction']->totalPrice,
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function patch(Interaction $interaction, string $shipSymbol, string $flightMode)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $flightMode = ShipNavFlightMode::from($flightMode);
            $response = $space_traders->patchShipNav($shipSymbol, $flightMode);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->getNav($this->message(), $response['nav']);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
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
