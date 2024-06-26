<?php

namespace App\CommandActions\Ships;

use AlejandroAPorras\SpaceTraders\Resources\ScannedShip;
use AlejandroAPorras\SpaceTraders\Resources\ScannedSystem;
use AlejandroAPorras\SpaceTraders\Resources\ScannedWaypoint;
use AlejandroAPorras\SpaceTraders\Resources\Survey;
use AlejandroAPorras\SpaceTraders\Resources\SurveyDeposit;
use AlejandroAPorras\SpaceTraders\Resources\WaypointTrait;
use Carbon\Carbon;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laracord\Discord\Message;

trait ManageScanShips
{
    protected string $shipSymbol;

    public function manageScanShipsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $chart_subcommand = (new Option($this->discord()))
            ->setName('chart')
            ->setDescription('Command a ship to chart the waypoint at its current location.')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $survey_subcommand = (new Option($this->discord()))
            ->setName('survey')
            ->setDescription('Create surveys on a waypoint that can be extracted such as asteroid fields')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $systems_subcommand = (new Option($this->discord()))
            ->setName('systems')
            ->setDescription('Scan for nearby systems')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $waypoints_subcommand = (new Option($this->discord()))
            ->setName('waypoints')
            ->setDescription('Scan for nearby waypoints')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        $ships_subcommand = (new Option($this->discord()))
            ->setName('ships')
            ->setDescription('Scan for nearby ships')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol);

        return [
            (new Option($this->discord()))
                ->setName('scan')
                ->setDescription('Perform scan actions with the ship')
                ->setType(Option::SUB_COMMAND_GROUP)
                ->addOption($chart_subcommand)
                ->addOption($survey_subcommand)
                ->addOption($systems_subcommand)
                ->addOption($waypoints_subcommand)
                ->addOption($ships_subcommand),
        ];
    }

    public function handleManageScanShips(Interaction $interaction): void
    {
        $subcommand = $interaction->data->options->first()->options->first()->name;

        $this->shipSymbol = $this->value('scan.'.$subcommand.'.ship');

        match ($subcommand) {
            'chart' => $this->chart($interaction, $this->shipSymbol),
            'survey' => $this->survey($interaction, $this->shipSymbol),
            'systems' => $this->systems($interaction, $this->shipSymbol),
            'waypoints' => $this->waypoints($interaction, $this->shipSymbol),
            'ships' => $this->ships($interaction, $this->shipSymbol),
        };
    }

    public function manageScanShipsInteractions(): array
    {
        return [
            'survey:{page?}' => fn (Interaction $interaction, $page = null) => $this->survey($interaction, $this->shipSymbol, $page),
            'systems:{page?}' => fn (Interaction $interaction, $page = null) => $this->systems($interaction, $this->shipSymbol, $page),
            'waypoints:{page?}' => fn (Interaction $interaction, $page = null) => $this->waypoints($interaction, $this->shipSymbol, $page),
            'ships:{page?}' => fn (Interaction $interaction, $page = null) => $this->ships($interaction, $this->shipSymbol, $page),
        ];
    }

    public function manageScanShipAutocomplete() {}

    public function chart(Interaction $interaction, string $shipSymbol): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->chartShip($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }
        $waypoint = $response['waypoint'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($shipSymbol)
            ->title('Charted'.$response['chart']->waypointSymbol)
            ->footerText($response['chart']->submittedBy)
            ->fields([
                'Type' => $waypoint->type->value,
                'Faction' => $waypoint->faction->symbol->value,
                'Under Construction?' => $waypoint->isUnderConstruction ? 'Yes' : 'No',
                "\u{200B}" => "\u{200B}",
            ], false)
            ->fields([
                'X' => $waypoint->x,
                'Y' => $waypoint->y,
            ])
            ->timestamp(Date::parse($response['chart']->submittedOn));

        return $page->editOrReply($interaction);
    }

    public function survey(Interaction $interaction, string $shipSymbol, $pageNumber = 1): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->createSurvey($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }
        $message = $this->message()->fields([
            "\u{200B}" => "\u{200B}",
            'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
        ], false);

        $page = $this->paginateFromArray(
            $message,
            $response['surveys'],
            'No surveys',
            'survey',
            function (Message $message, $items) use ($shipSymbol) {
                /**
                 * @var $survey Survey
                 */
                $survey = $items[0];

                return $message
                    ->authorIcon(null)
                    ->authorName($shipSymbol)
                    ->title($survey->symbol)
                    ->content(collect($survey->deposits)->map(function (SurveyDeposit $deposit) {
                        return $deposit->symbol;
                    })->join("\n"))
                    ->fields([
                        'Signature' => $survey->signature,
                        'Size' => $survey->size->value,
                        'Expiration' => Date::parse($survey->expiration)->toDiscord(),
                    ]);
            },
            page: $pageNumber
        );

        return $page->editOrReply($interaction);
    }

    public function systems(Interaction $interaction, string $shipSymbol, $pageNumber = 1): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->scanSystems($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }
        $message = $this->message()->fields([
            'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            "\u{200B}" => "\u{200B}",
        ], false);

        $page = $this->paginateFromArray(
            $message,
            $response['systems'],
            'No systems',
            'systems',
            function (Message $message, $items) {
                /**
                 * @var $system ScannedSystem
                 */
                $system = $items[0];

                return $message
                    ->authorIcon(null)
                    ->authorName($system->sectorSymbol)
                    ->title($system->symbol)
                    ->fields([
                        'Type' => $system->type->value,
                        'Distance' => $system->distance,
                    ])
                    ->field("\u{200B}", "\u{200B}", false)
                    ->fields([
                        'x' => $system->x,
                        'y' => $system->y,
                    ]);
            },
            page: $pageNumber
        );

        return $page->editOrReply($interaction);
    }

    public function waypoints(Interaction $interaction, string $shipSymbol, $pageNumber = 1): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->scanWaypoints($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }
        $message = $this->message()->fields([
            'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            "\u{200B}" => "\u{200B}",
        ], false);

        $page = $this->paginateFromArray(
            $message,
            $response['waypoints'],
            'No waypoints',
            'waypoints',
            function (Message $message, $items) {
                /**
                 * @var $waypoint ScannedWaypoint
                 */
                $waypoint = $items[0];

                return $message
                    ->authorIcon(null)
                    ->authorName('System: '.$waypoint->systemSymbol)
                    ->title($waypoint->symbol)
                    ->fields([
                        'Type' => $waypoint->type->value,
                        'Faction' => $waypoint->faction->symbol->value,
                        'Orbitals' => collect($waypoint->orbitals)->map(fn ($orbital) => $orbital->symbol)->join(', '),
                        "\u{200B}" => "\u{200B}",
                    ], false)
                    ->fields([
                        'x' => $waypoint->x,
                        'y' => $waypoint->y,
                    ])
                    ->field("\u{200B}", "\u{200B}", false)
                    ->field('Traits', "\u{200B}", false)
                    ->fields(
                        collect($waypoint->traits)->mapWithKeys(function (WaypointTrait $trait) {
                            return [$trait->name => $trait->description];
                        }
                        )->toArray(),
                        false
                    );
            },
            page: $pageNumber
        );

        return $page->editOrReply($interaction);
    }

    public function ships(Interaction $interaction, string $shipSymbol, $pageNumber = 1): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->scanShips($shipSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }
        $message = $this->message()->fields([
            'Cooldown' => $response['cooldown']->remainingSeconds === 0 ? 'N/A' : $response['cooldown']->remainingSeconds.'s Remaining',
            "\u{200B}" => "\u{200B}",
        ], false);

        $page = $this->paginateFromArray(
            $message,
            $response['ships'],
            'No ships',
            'ships',
            function (Message $message, $items) {
                /**
                 * @var $ship ScannedShip
                 */
                $ship = $items[0];

                return $message
                    ->authorIcon(null)
                    ->title($ship->symbol)
                    ->fields([
                        'Name' => $ship->registration->name,
                        'Role' => Str::title($ship->registration->role->value),
                        'Faction' => Str::title($ship->registration->factionSymbol->value),
                    ])
                    ->field("\u{200B}", "\u{200B}", false)
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
            },
            page: $pageNumber
        );

        return $page->editOrReply($interaction);
    }
}
