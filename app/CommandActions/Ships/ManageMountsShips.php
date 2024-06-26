<?php

namespace App\CommandActions\Ships;

use AlejandroAPorras\SpaceTraders\Resources\ShipCargoItem;
use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Facades\Date;

trait ManageMountsShips
{
    public function manageMountsShipsOptions(): array
    {
        $ship_symbol = (new Option($this->discord()))
            ->setName('ship')
            ->setDescription('The symbol of the ship')
            ->setType(Option::STRING)
            ->setRequired(true);

        $mount_symbol = (new Option($this->discord()))
            ->setName('mount')
            ->setDescription('The symbol of the mount to install or remove')
            ->setType(Option::STRING)
            ->setRequired(true);

        $install_subcommand = (new Option($this->discord()))
            ->setName('install')
            ->setDescription('Install a mount')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol)
            ->addOption($mount_symbol);

        $remove_subcommand = (new Option($this->discord()))
            ->setName('remove')
            ->setDescription('Remove a mount')
            ->setType(Option::SUB_COMMAND)
            ->addOption($ship_symbol)
            ->addOption($mount_symbol);

        return [
            (new Option($this->discord()))
                ->setName('mounts')
                ->setDescription('Perform scan actions with the ship')
                ->setType(Option::SUB_COMMAND_GROUP)
                ->addOption($install_subcommand)
                ->addOption($remove_subcommand),
        ];
    }

    public function handleManageMountsShips(Interaction $interaction): void
    {
        $subcommand = $interaction->data->options->first()->options->first()->name;

        match ($subcommand) {
            'install' => $this->install($interaction, $this->value('mounts.install.ship'), $this->value('mounts.install.mount')),
            'remove' => $this->remove($interaction, $this->value('mounts.remove.ship'), $this->value('mounts.remove.mount')),
        };
    }

    public function install(Interaction $interaction, string $shipSymbol, string $mountSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->installShipMount($shipSymbol, $mountSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->title($shipSymbol)
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $response['transaction']->price,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ])
            ->button('Ship Details', style: Button::STYLE_SECONDARY, route: 'ship-details:'.$shipSymbol);

        return $page->editOrReply($interaction);
    }

    public function remove(Interaction $interaction, string $shipSymbol, string $mountSymbol)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $response = $space_traders->removeShipMount($shipSymbol, $mountSymbol);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $cargo = $response['cargo'];
        $transaction = $response['transaction'];

        $page = $this->message()
            ->authorIcon(null)
            ->authorName('New Credit Balance: '.$response['agent']->credits)
            ->title($shipSymbol)
            ->content(
                collect($cargo->inventory)->map(function (ShipCargoItem $cargoItem) {
                    return vsprintf('- [**%s** - %s]: %s', [$cargoItem->symbol->value, $cargoItem->name, $cargoItem->units]);
                }
                )->join("\n")."\n"
            )
            ->fields([
                'Capacity' => $cargo->capacity,
                'Units' => $cargo->units,
            ])
            ->fields([
                'Transaction' => "\u{200B}",
                'Waypoint' => $transaction->waypointSymbol,
                'Price' => $response['transaction']->price,
                'Date' => Date::parse($response['transaction']->timestamp)->toDiscord(),
            ])
            ->button('Ship Details', style: Button::STYLE_SECONDARY, route: 'ship-details:'.$shipSymbol);

        return $page->editOrReply($interaction);
    }
}
