<?php

namespace App\CommandActions\Waypoints;

use AlejandroAPorras\SpaceTraders\Enums\ShipType;
use AlejandroAPorras\SpaceTraders\Resources\ShipModule;
use AlejandroAPorras\SpaceTraders\Resources\ShipMount;
use AlejandroAPorras\SpaceTraders\Resources\ShipyardShip;
use AlejandroAPorras\SpaceTraders\Resources\ShipyardTransaction;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Laracord\Discord\Message;

trait ManageShipyardWaypoints
{
    public function manageShipyardWaypointsOptions(): array
    {
        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('shipyard')
                ->setDescription('Gets the shipyard details of a waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleManageShipyardWaypoints(Interaction $interaction): void
    {
        $this->waypointSymbol = $this->value('shipyard.waypoint');
        $this->shipyard($interaction, $this->waypointSymbol);
    }

    public function manageShipyardWaypointsInteractions(): array
    {
        return [
            'waypoint-shipyard:{waypoint}:{page?}' => fn (Interaction $interaction, string $waypoint, $page = 1) => $this->shipyard($interaction, $waypoint, $page, $interaction->data->values[0] ?? 'General'),
            'purchase-ship:{waypoint}:{ship}' => fn (Interaction $interaction, string $waypoint, string $ship) => $this->purchaseShip($interaction, $waypoint, $ship),
        ];
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

    public function purchaseShip(Interaction $interaction, $waypoint, $ship): false|\React\Promise\ExtendedPromiseInterface
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
}
