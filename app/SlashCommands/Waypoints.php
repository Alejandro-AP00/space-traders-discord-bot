<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Enums\ShipType;
use AlejandroAPorras\SpaceTraders\Enums\TradeGoodSymbol;
use AlejandroAPorras\SpaceTraders\Enums\WaypointTraitSymbol;
use AlejandroAPorras\SpaceTraders\Enums\WaypointType;
use AlejandroAPorras\SpaceTraders\Resources\ConstructionMaterial;
use AlejandroAPorras\SpaceTraders\Resources\MarketTradeGood;
use AlejandroAPorras\SpaceTraders\Resources\MarketTransaction;
use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use AlejandroAPorras\SpaceTraders\Resources\ShipModule;
use AlejandroAPorras\SpaceTraders\Resources\ShipMount;
use AlejandroAPorras\SpaceTraders\Resources\ShipyardShip;
use AlejandroAPorras\SpaceTraders\Resources\ShipyardTransaction;
use AlejandroAPorras\SpaceTraders\Resources\TradeGood;
use AlejandroAPorras\SpaceTraders\Resources\Waypoint;
use AlejandroAPorras\SpaceTraders\Resources\WaypointModifier;
use AlejandroAPorras\SpaceTraders\Resources\WaypointTrait;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laracord\Commands\SlashCommand;
use Laracord\Discord\Message;

class Waypoints extends SlashCommand
{
    use CanPaginate, HasAgent;

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

        $system_symbol = (new Option($this->discord()))
            ->setName('system')
            ->setDescription('The Symbol of the system the waypoint is at')
            ->setType(Option::STRING)
            ->setRequired(true);

        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        $waypoint_traits = (new Option($this->discord()))
            ->setName('waypoint_traits')
            ->setDescription('The Symbol of the waypoints traits')
            ->setType(Option::STRING)
            ->setAutoComplete(true);

        $waypoint_types = (new Option($this->discord()))
            ->setName('waypoint_types')
            ->setDescription('The Symbol of the waypoints types')
            ->setType(Option::STRING);

        foreach (WaypointType::cases() as $case) {
            $waypoint_types->addChoice(
                (Choice::new($this->discord(), Str::title($case->name), $case->value)),
            );
        }

        $detail_subcommand = (new Option($this->discord()))
            ->setName('detail')
            ->setDescription('Gets the details of a waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($system_symbol)
            ->addOption($waypoint_symbol);

        $list_subcommand = (new Option($this->discord()))
            ->setName('list')
            ->setDescription('List all Waypoints for the current system')
            ->setType(Option::SUB_COMMAND)
            ->addOption($system_symbol)
            ->addOption($waypoint_traits)
            ->addOption($waypoint_types);

        $shipyard_subcommand = (new Option($this->discord()))
            ->setName('shipyard')
            ->setDescription('Gets the shipyard details of a waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($waypoint_symbol);

        $market_subcommand = (new Option($this->discord()))
            ->setName('market')
            ->setDescription('Gets the Market details of a waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($waypoint_symbol);

        $jump_gate_subcommand = (new Option($this->discord()))
            ->setName('jump-gate')
            ->setDescription('Gets the Jump Gate details of a Jump Gate waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($waypoint_symbol);

        $construction_details_subcommand = (new Option($this->discord()))
            ->setName('details')
            ->setDescription('Gets the Construction details of a To Be Constructed waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($waypoint_symbol);

        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship that delivers the contract')
            ->setType(Option::STRING)
            ->setRequired(true);

        $trade_good = (new Option($this->discord()))
            ->setName('trade_good')
            ->setDescription('The trade good to supply for to the Waypoint')
            ->setType(Option::STRING)
            ->setRequired(true)
            ->setAutoComplete(true);

        $units = (new Option($this->discord()))
            ->setName('units')
            ->setDescription('The number of units of the trade good delivered for the waypoint')
            ->setType(Option::NUMBER)
            ->setRequired(true);

        $construction_supply_subcommand = (new Option($this->discord()))
            ->setName('supply')
            ->setDescription('Supply construction materials to a To Be Constructed waypoint')
            ->setType(Option::SUB_COMMAND)
            ->addOption($system_symbol)
            ->addOption($waypoint_symbol)
            ->addOption($ship_symbol)
            ->addOption($trade_good)
            ->addOption($units);

        $construction_subcommand_group = (new Option($this->discord()))
            ->setName('construction')
            ->setDescription('Gets the Construction details of a To Be Constructed waypoint')
            ->setType(Option::SUB_COMMAND_GROUP)
            ->addOption($construction_details_subcommand)
            ->addOption($construction_supply_subcommand);

        return [
            $detail_subcommand,
            $list_subcommand,
            $shipyard_subcommand,
            $market_subcommand,
            $jump_gate_subcommand,
            $construction_subcommand_group,
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
            $this->systemSymbol = $this->value('detail.system');
            $this->waypointSymbol = $this->value('detail.waypoint');
            $this->waypoint($interaction, $this->waypointSymbol);
        }

        if ($action === 'list') {
            $this->systemSymbol = $this->value('list.system');
            $this->waypointTrait = $this->value('list.waypoint_traits');
            $this->waypointType = $this->value('list.waypoint_types');
            $this->waypoints($interaction);
        }

        if ($action === 'shipyard') {
            $this->waypointSymbol = $this->value('shipyard.waypoint');
            $this->shipyard($interaction, $this->waypointSymbol);
        }

        if ($action === 'market') {
            $this->waypointSymbol = $this->value('market.waypoint');
            $this->market($interaction, $this->waypointSymbol);
        }

        if ($action === 'jump-gate') {
            $this->waypointSymbol = $this->value('jump-gate.waypoint');
            $this->jumpGate($interaction, $this->waypointSymbol);
        }

        if ($action === 'construction') {
            $subcommand = $interaction->data->options->first()->options->first()->name;

            if ($subcommand === 'details') {
                $this->waypointSymbol = $this->value('construction.details.waypoint');
                $this->constructionDetails($interaction, $this->waypointSymbol);
            }

            if ($subcommand === 'supply') {
                $this->systemSymbol = $this->value('construction.supply.system');
                $this->waypointSymbol = $this->value('construction.supply.waypoint');
                $this->supplyConstruction($interaction, $this->waypointSymbol, $this->value('construction.supply.ship'), $this->value('construction.supply.trade_good'), $this->value('construction.supply.units'));
            }
        }
    }

    private function waypoints(Interaction $interaction, $page = 1)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $params = [
                'page' => $page,
                'limit' => 1,
            ];

            if (! empty($this->waypointTrait)) {
                $params['traits'] = $this->waypointTrait;
            }

            if (! empty($this->waypointType)) {
                $params['type'] = $this->waypointType;
            }

            $waypoints = $space_traders->waypoints($this->systemSymbol, $params);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->paginate($this->message(), $waypoints, 'No Waypoints found.', 'waypoint', function (Message $message, Waypoint $waypoint) {
            return $message->authorIcon(null)
                ->authorName('System: '.$waypoint->systemSymbol)
                ->title($waypoint->symbol)
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
                ->button('Details', style: Button::STYLE_SECONDARY, route: "waypoint-details:{$waypoint->symbol}");
        });

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    private function waypoint(Interaction $interaction, string $waypoint, $option = 'General')
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $waypoint = $space_traders->waypoint($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this
            ->message()
            ->authorIcon(null)
            ->authorName('System: '.$waypoint->systemSymbol)
            ->title($waypoint->symbol)
            ->select([
                'General',
                'Traits',
                'Modifiers',
            ], route: "waypoint-details:{$waypoint->symbol}")
            ->timestamp();

        if (collect($waypoint->traits)->contains(fn (WaypointTrait $trait) => $trait->symbol === WaypointTraitSymbol::SHIPYARD)) {
            $page->button('Shipyard', route: "waypoint-shipyard:{$waypoint->symbol}");
        }

        if (collect($waypoint->traits)->contains(fn (WaypointTrait $trait) => $trait->symbol === WaypointTraitSymbol::MARKETPLACE)) {
            $page->button('Market', route: "waypoint-market:{$waypoint->symbol}");
        }

        if ($waypoint->type === WaypointType::JUMP_GATE) {
            $page->button('Jump Gate', route: "waypoint-jump-gate:{$waypoint->symbol}");
        }

        if ($waypoint->isUnderConstruction) {
            $page->button('Construction Details', route: "waypoint-construction:{$waypoint->symbol}");
        }

        $page = match ($option) {
            'General' => $page->fields([
                'Type' => $waypoint->type->value,
                'Faction' => $waypoint->faction->symbol->value,
                'Under Construction?' => $waypoint->isUnderConstruction ? 'Yes' : 'No',
                "\u{200B}" => "\u{200B}",
            ], false)
                ->fields([
                    'X' => $waypoint->x,
                    'Y' => $waypoint->y,
                ]),
            'Traits' => $page->fields(
                collect($waypoint->traits)->mapWithKeys(function (WaypointTrait $trait) {
                    return [$trait->name => $trait->description];
                }
                )->toArray(),
                false
            )
                ->field("\u{200B}", "\u{200B}", false),
            'Modifiers' => $page->fields(
                collect($waypoint->modifiers)->mapWithKeys(function (WaypointModifier $trait) {
                    return [$trait->name => $trait->description];
                }
                )->toArray(),
                false
            )
                ->field("\u{200B}", "\u{200B}", false),
        };

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function shipyard(Interaction $interaction, string $waypoint, $pageNumber = 1, $option = 'General')
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $shipyard = $space_traders->shipyard($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($waypoint)
            ->title('Shipyard')
            ->select([
                'General',
                'Transactions',
                'Ships',
            ], route: "waypoint-shipyard:{$waypoint}");

        $page = match ($option) {
            'General' => $page
                ->field('Modification Fee', $shipyard->modificationsFee, false)
                ->field('Ship Types Available', collect($shipyard->shipTypes)->map(fn (ShipType $ship_type) => $ship_type->value)->join("\n"), false),
            'Transactions' => $page
                ->fields(
                    collect($shipyard->transactions)->mapWithKeys(function (ShipyardTransaction $transaction) {
                        return ["{$transaction->shipType->value}" => "Price: {$transaction->price}\nAgent: {$transaction->agentSymbol}"];
                    })->take(20)->toArray(), false),
            'Ships' => $this->paginateFromArray(
                message: $page,
                results: $shipyard->transactions,
                emptyMessage: 'No Ships at Waypoint or Not Docked',
                routeName: "waypoint-shipyard:{$waypoint}",
                callback: function (Message $message, Collection $results) use ($waypoint) {
                    /**
                     * @var $ship ShipyardShip
                     */
                    $ship = $results->first();

                    return $message
                        ->title($ship->type->value)
                        ->fields([
                            'Supply' => $ship->supply->value,
                            'Price' => $ship->purchasePrice,
                        ])
                        ->field('Description', $ship->description, false)
                        ->fields([
                            'Frame' => $ship->frame->symbol->value,
                            'Reactor' => $ship->reactor->symbol->value,
                            'Engine' => $ship->engine->symbol->value,
                            'Modules' => collect($ship->modules)->map(fn (ShipModule $module) => $module->symbol->value)->join("\n"),
                            'Mounts' => collect($ship->mounts)->map(fn (ShipMount $module) => $module->symbol->value)->join("\n"),
                        ])
                        ->button('Purchase Ship', route: "purchase-ship:{$waypoint}:{$ship->type->value}");
                }, page: $pageNumber)
        };

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function market(Interaction $interaction, string $waypoint, $pageNumber = 1, $option = 'Exports')
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $market = $space_traders->market($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($waypoint)
            ->title('Market')
            ->select([
                'Exports',
                'Imports',
                'Exchange',
                'Transactions',
                'Trade Goods',
            ]);

        $page = match ($option) {
            'Exports' => $this->paginateFromArray(
                message: $page,
                results: $market->exports,
                emptyMessage: 'No Exports at Market',
                routeName: "waypoint-market:{$waypoint}",
                callback: function (Message $message, Collection $results) {
                    return $message
                        ->fields($results->mapWithKeys(fn (TradeGood $trade_good) => ["[{$trade_good->symbol->value}]: {$trade_good->name}" => "{$trade_good->description}"])->toArray());
                }, perPage: 10, page: $pageNumber),
            'Imports' => $this->paginateFromArray(
                message: $page,
                results: $market->imports,
                emptyMessage: 'No Imports at Market',
                routeName: "waypoint-market:{$waypoint}",
                callback: function (Message $message, Collection $results) {
                    return $message
                        ->fields($results->mapWithKeys(fn (TradeGood $trade_good) => ["[{$trade_good->symbol->value}]: {$trade_good->name}" => "{$trade_good->description}"])->toArray());
                }, perPage: 10, page: $pageNumber),
            'Exchange' => $this->paginateFromArray(
                message: $page,
                results: $market->exchange,
                emptyMessage: 'No Exchanges at Market',
                routeName: "waypoint-market:{$waypoint}",
                callback: function (Message $message, Collection $results) {
                    return $message
                        ->fields($results->mapWithKeys(fn (TradeGood $trade_good) => ["[{$trade_good->symbol->value}]: {$trade_good->name}" => "{$trade_good->description}"])->toArray());
                }, perPage: 10, page: $pageNumber),
            'Transactions' => $this->paginateFromArray(
                message: $page,
                results: $market->transactions,
                emptyMessage: 'No Transactions at Market',
                routeName: "waypoint-market:{$waypoint}",
                callback: function (Message $message, Collection $results) {
                    /**
                     * @var $result MarketTransaction
                     */
                    $result = $results->first();

                    return $message
                        ->fields([
                            'Ship Symbol' => $result->shipSymbol,
                            'Trade Good' => Str::title($result->tradeSymbol->value),
                            'Transaction Type' => Str::title($result->type->value),
                            'Units' => $result->units,
                            'Price Per Unit' => $result->pricePerUnit,
                            'Total Price' => $result->totalPrice,
                        ])
                        ->timestamp(Date::parse($result->timestamp));
                }, page: $pageNumber),
            'Trade Goods' => $this->paginateFromArray(
                message: $page,
                results: $market->tradeGoods,
                emptyMessage: 'No Transactions at Market',
                routeName: "waypoint-market:{$waypoint}",
                callback: function (Message $message, Collection $results) {
                    /**
                     * @var $result MarketTradeGood
                     */
                    $result = $results->first();

                    return $message
                        ->fields([
                            'Trade Good' => Str::title($result->symbol->value),
                            'Trade Good Type' => Str::title($result->type->value),
                            'Supply Level' => Str::title($result->supply->value),
                            'Activity Level' => Str::title($result->activity->value),
                            'Volume' => $result->tradeVolume,
                            'Purchase Price' => $result->purchasePrice,
                            'Sell Price' => $result->sellPrice,
                        ]);
                }, page: $pageNumber),
        };

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function jumpGate(Interaction $interaction, string $waypoint)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $jump_gate = $space_traders->jumpGate($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorName($waypoint)
            ->title('Jump Gate: '.$jump_gate->symbol)
            ->field('Connections', collect($jump_gate->connections)->join("\n"), false);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function constructionDetails(Interaction $interaction, string $waypoint)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $construction = $space_traders->construction($this->systemSymbol, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($waypoint)
            ->title('Construction: '.$construction->symbol)
            ->fields([
                'Is complete' => "$construction->isComplete",
                "\u{200B}" => "\u{200B}",
                ...collect($construction->materials)->mapWithKeys(function (ConstructionMaterial $material) {
                    $fulfilled = $material->fulfilled ? '✅' : '❌';

                    return ["[{$material->tradeSymbol->value}]: {$fulfilled}" => 'Required: '.$material->required];
                })->toArray(),
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function supplyConstruction(Interaction $interaction, string $waypoint, string $ship, string $good, int $units)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $good = TradeGoodSymbol::from($good);
            $response = $space_traders->supplyConstruction($this->systemSymbol, $waypoint, $ship, $good, $units);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $construction = $response['construction'];
        $cargo = $response['cargo'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName($waypoint)
            ->title('Construction: '.$construction->symbol)
            ->content(
                "Cargo\n".
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields([
                'Is complete' => "$construction->isComplete",
                "\u{200B}" => "\u{200B}",
                ...collect($construction->materials)->mapWithKeys(function (ConstructionMaterial $material) {
                    $fulfilled = $material->fulfilled ? '✅' : '❌';

                    return ["[{$material->tradeSymbol->value}]: {$fulfilled}" => 'Required: '.$material->required];
                })->toArray(),
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    public function purchaseShip(Interaction $interaction, $waypoint, $ship)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->purchaseShip($ship, $waypoint);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $ship = $response['ship'];

        $page = $this->message()
            ->title('Ship Purchased')
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $waypoint,
                'Price' => $response['transaction']->price,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ])
            ->field("\u{200B}", "\u{200B}", false)
            ->fields([
                'Ship' => "\u{200B}",
                'Symbol' => $ship->symbol,
                'Role' => $ship->registration->role->value,
                'Frame' => $ship->frame->symbol->value,
                'Reactor' => $ship->reactor->symbol->value,
                'Engine' => $ship->engine->symbol->value,
                'Modules' => collect($ship->modules)->map(fn (ShipModule $module) => $module->symbol->value)->join("\n"),
                'Mounts' => collect($ship->mounts)->map(fn (ShipMount $module) => $module->symbol->value)->join("\n"),
            ]);

        return $interaction->message?->user_id === $this->discord()->id ? $interaction->updateMessage($page->build()) : $interaction->respondWithMessage($page->build());
    }

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [
            'waypoint:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->waypoints($interaction, $page),
            'waypoint-details:{waypoint}' => fn (Interaction $interaction, string $waypoint) => $this->waypoint($interaction, $waypoint, $interaction->data->values[0] ?? 'General'),
            'waypoint-shipyard:{waypoint}:{page?}' => fn (Interaction $interaction, string $waypoint, $page = 1) => $this->shipyard($interaction, $waypoint, $page, $interaction->data->values[0] ?? 'General'),
            'waypoint-market:{waypoint}:{page?}' => fn (Interaction $interaction, string $waypoint, $page = 1) => $this->market($interaction, $waypoint, $page, $interaction->data->values[0] ?? 'General'),
            'waypoint-jump-gate:{waypoint}' => fn (Interaction $interaction, string $waypoint) => $this->jumpGate($interaction, $waypoint),
            'waypoint-construction:{waypoint}' => fn (Interaction $interaction, string $waypoint) => $this->constructionDetails($interaction, $waypoint),
            'purchase-ship:{waypoint}:{ship}' => fn (Interaction $interaction, string $waypoint, string $ship) => $this->purchaseShip($interaction, $waypoint, $ship),
        ];
    }

    public function autocomplete(): array
    {
        return [
            'list.waypoint_traits' => function ($interaction, $value) {
                return collect(WaypointTraitSymbol::cases())
                    ->filter(function (WaypointTraitSymbol $trait) use ($value) {
                        if ($value === '' || $value === null) {
                            return true;
                        }

                        return str($trait->value)->lower()->contains(str($value)->lower());
                    })
                    ->map(function (WaypointTraitSymbol $trait) {
                        return Choice::new($this->discord, $trait->name, $trait->value);
                    })
                    ->take(25)
                    ->values()
                    ->toArray();
            },
            'construction.supply.trade_good' => function ($interaction, $value) {
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
}
