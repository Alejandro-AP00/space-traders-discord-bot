<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Enums\Deposits;
use AlejandroAPorras\SpaceTraders\Resources\Ship;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use AlejandroAPorras\SpaceTraders\Resources\ShipModule;
use AlejandroAPorras\SpaceTraders\Resources\ShipMount;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use Carbon\Carbon;
use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laracord\Commands\SlashCommand;
use Laracord\Discord\Message;

class Ships extends SlashCommand
{
    use CanPaginate, HasAgent;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'ships';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The ships slash command.';

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

    protected string $shipSymbol;

    public function options(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $detail_subcommand = (new Option($this->discord()))
            ->setName('detail')
            ->setDescription('Gets the details of a waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $list_subcommand = (new Option($this->discord()))
            ->setName('list')
            ->setDescription('List all Ships you own')
            ->setType(Option::SUB_COMMAND);

        return [
            $detail_subcommand,
            $list_subcommand,
            // Scan
            // - Chart
            // - Survey
            // - Systems
            // - Waypoints
            // - Ships
            // Cargo
            // - Refine
            // - Extract
            // - Extract Survey {Survey Id}
            // - Siphon
            // - Jettison
            // - Sell
            // - Purchase
            // - Refuel
            // - Transfer
            // Negotiate
            // Mounts
            // - List
            // - Remove
            // - Install
            // Scrap
            // - Value
            // - Execute
            // Repair
            // - Value
            // - Execute
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

        if ($action === 'detail') {
            $this->shipSymbol = $this->value('detail.ship');
            $this->shipDetails($interaction, $this->shipSymbol);
        }

        if ($action === 'list') {
            $this->ship($interaction);
        }

    }

    public function ship(Interaction $interaction, $page = 1)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $ships = $space_traders->ships(['page' => $page, 'limit' => 1]);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->paginate($this->message(), $ships, "You don't have any ships", 'ship', function (Message $message, Ship $item) {
            return $message->authorIcon(null)
                ->authorName($item->symbol)
                ->fields([
                    'Name' => $item->registration->name,
                    'Role' => Str::title($item->registration->role->value),
                    'Faction' => Str::title($item->registration->factionSymbol->value),
                ])
                ->fields([
                    "\u{200B}" => "\u{200B}",
                    'Cooldown' => $item->cooldown->remainingSeconds === 0 ? 'N/A' : $item->cooldown->remainingSeconds.'s Remaining',
                ], false)
                ->button('Details', style: Button::STYLE_SECONDARY, route: 'ship-details:'.$item->symbol);
        });

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function shipDetails($interaction, $shipSymbol, $option = 'General')
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $ship = $space_traders->ship($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($ship->symbol)
            ->select([
                'General',
                'Nav',
                'Frame',
                'Reactor',
                'Engine',
                'Crew',
                'Mounts',
                'Modules',
                'Cargo',
            ], route: 'ship-details:'.$shipSymbol);

        $page = match ($option) {
            'General' => $this->getGeneral($page, $ship),
            'Nav' => $this->getNav($page, $ship),
            'Frame' => $this->getFrame($page, $ship),
            'Reactor' => $this->getReactor($page, $ship),
            'Engine' => $this->getEngine($page, $ship),
            'Crew' => $this->getCrew($page, $ship),
            'Mounts' => $this->getMounts($page, $ship),
            'Modules' => $this->getModules($page, $ship),
            'Cargo' => $this->getCargo($page, $ship),
        };

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [
            'ship:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->ship($interaction, $page),
            'ship-details:{ship}' => fn (Interaction $interaction, string $ship) => $this->shipDetails($interaction, $ship, $interaction->data->values[0] ?? 'General'),
        ];
    }

    private function getMounts(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Mounts')
            ->field("\u{200B}", "\u{200B}", false)
            ->content(collect($ship->mounts)->map(function (ShipMount $mount) {
                $deposits = collect($mount->deposits)->map(fn (Deposits $deposit) => "- {$deposit->value}")->join("\n");

                return "**[{$mount->symbol->value} - {$mount->name}]** (Strength: {$mount->strength})\n".
                    "{$mount->description}\n".
                    "**Deposits**\n".
                    $deposits.
                    "**Requirements**\n".
                    "Power: {$mount->requirements->power}, Crew: {$mount->requirements->crew}, Slots: {$mount->requirements->slots}";
            })->join("\n\n"));
    }

    private function getCargo(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Cargo')
            ->content(
                collect($ship->cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $ship->cargo->capacity,
                'Units' => $ship->cargo->units,
            ]);
    }

    private function getModules(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Modules')
            ->field("\u{200B}", "\u{200B}", false)
            ->content(collect($ship->modules)->map(function (ShipModule $module) {
                return "**[{$module->symbol->value} - {$module->name}]** (Capacity: {$module->capacity}) (Range: {$module->range}) \n".
                    "{$module->description}\n".
                    "**Requirements**\n".
                    "Power: {$module->requirements->power}, Crew: {$module->requirements->crew}, Slots: {$module->requirements->slots}";
            })->join("\n\n"));
    }

    private function getGeneral(Message $page, Ship $ship): Message
    {
        return $page
            ->title('General')
            ->fields([
                'Name' => $ship->registration->name,
                'Role' => Str::title($ship->registration->role->value),
                'Faction' => Str::title($ship->registration->factionSymbol->value),
            ])
            ->fields([
                "\u{200B}" => "\u{200B}",
                'Cooldown' => $ship->cooldown->remainingSeconds === 0 ? 'N/A' : $ship->cooldown->remainingSeconds.'s Remaining',
            ], false);
    }

    private function getNav(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Navigation')
            ->fields([
                'System' => $ship->nav->systemSymbol,
                'Waypoint' => $ship->nav->waypointSymbol,
                'Status' => Str::title($ship->nav->status->value),
                'Flight Mode' => Str::title($ship->nav->flightMode->value),
            ])
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Departure' => $ship->nav->route->origin->symbol.' at '.Carbon::parse($ship->nav->route->departureTime)->toDiscord(),
                'Arrival' => $ship->nav->route->destination->symbol.' at '.Carbon::parse($ship->nav->route->arrival)->toDiscord(),
            ]);
    }

    private function getCrew(Message $page, Ship $ship): Message
    {
        return $page
            ->title('Crew')
            ->fields([
                'Current' => $ship->crew->current,
                'Required' => $ship->crew->required,
                'Capacity' => $ship->crew->capacity,
                'Morale' => $ship->crew->morale,
                'Wages' => $ship->crew->wages,
                'Crew Rotation' => Str::title($ship->crew->rotation->value),
            ]);
    }

    private function getFrame(Message $page, Ship $ship): Message
    {
        return $page
            ->authorName($ship->frame->symbol->value)
            ->title($ship->frame->name)
            ->content(
                $ship->frame->description."\n"."**Requirements**\n".
                "Power: {$ship->frame->requirements->power}, Crew: {$ship->frame->requirements->crew}, Slots: {$ship->frame->requirements->slots}")
            ->fields([
                'Condition' => Number::percentage($ship->frame->condition),
                'Integrity' => Number::percentage($ship->frame->integrity),
                'Module Slots' => $ship->frame->moduleSlots,
                'Mounting Points' => $ship->frame->mountingPoints,
                'Fuel Capacity' => $ship->frame->fuelCapacity,
            ]);
    }

    private function getReactor(Message $page, Ship $ship): Message
    {
        return $page
            ->authorName($ship->reactor->symbol->value)
            ->title($ship->reactor->name)
            ->content(
                $ship->reactor->description."\n"."**Requirements**\n".
                "Power: {$ship->reactor->requirements->power}, Crew: {$ship->reactor->requirements->crew}, Slots: {$ship->reactor->requirements->slots}")
            ->fields([
                'Condition' => Number::percentage($ship->reactor->condition),
                'Integrity' => Number::percentage($ship->reactor->integrity),
                'Power Output' => $ship->reactor->powerOutput,
            ]);
    }

    private function getEngine(Message $page, Ship $ship): Message
    {
        return $page
            ->authorName($ship->engine->symbol->value)
            ->title($ship->engine->name)
            ->content(
                $ship->engine->description."\n"."**Requirements**\n".
                "Power: {$ship->engine->requirements->power}, Crew: {$ship->engine->requirements->crew}, Slots: {$ship->engine->requirements->slots}")
            ->fields([
                'Condition' => Number::percentage($ship->engine->condition),
                'Integrity' => Number::percentage($ship->engine->integrity),
                'Speed' => $ship->engine->speed,
            ]);
    }
}
