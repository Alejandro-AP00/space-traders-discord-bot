<?php

namespace App\CommandActions\Waypoints;

use AlejandroAPorras\SpaceTraders\Resources\MarketTradeGood;
use AlejandroAPorras\SpaceTraders\Resources\MarketTransaction;
use AlejandroAPorras\SpaceTraders\Resources\TradeGood;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laracord\Discord\Message;

trait ManageMarketWaypoints
{
    public function manageMarketWaypointsOptions(): array
    {
        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        $system_symbol = (new Option($this->discord()))
            ->setName('system')
            ->setDescription('The Symbol of the system the waypoint is at')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('market')
                ->setDescription('Gets the Market details of a waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($system_symbol)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleManageMarketWaypoints(Interaction $interaction): void
    {
        $this->waypointSymbol = $this->value('market.waypoint');
        $this->systemSymbol = $this->value('shipyard.system');
        $this->market($interaction, $this->waypointSymbol);
    }

    public function manageMarketWaypointsInteractions(): array
    {
        return [
            'waypoint-market:{waypoint}:{page?}' => fn (Interaction $interaction, string $waypoint, $page = 1) => $this->market($interaction, $waypoint, $page, $interaction->data->values[0] ?? 'Exports'),
        ];
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
}
