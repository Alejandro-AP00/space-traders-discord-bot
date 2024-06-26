<?php

namespace App\CommandActions\Ships;

use AlejandroAPorras\SpaceTraders\Resources\Ship;
use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Str;
use Laracord\Discord\Message;

trait ListShips
{
    public function listShipsOptions(): array
    {
        return [
            (new Option($this->discord()))
                ->setName('list')
                ->setDescription('List all Ships you own')
                ->setType(Option::SUB_COMMAND),
        ];
    }

    public function handleListShips(Interaction $interaction): void
    {
        $this->ship($interaction);
    }

    public function ship(Interaction $interaction, $page = 1): false|\React\Promise\ExtendedPromiseInterface
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $ships = $space_traders->ships(['page' => $page, 'limit' => 1]);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->paginate($this->message(), $ships, "You don't have any ships", 'ship', function (Message $message, Ship $item) {
            $message = $message->authorIcon(null)
                ->authorName($item->symbol)
                ->fields([
                    'Name' => $item->registration->name,
                    'Role' => Str::title($item->registration->role->value),
                    'Faction' => Str::title($item->registration->factionSymbol->value),
                ])
                ->button('Details', style: Button::STYLE_SECONDARY, route: 'ship-details:'.$item->symbol);

            return $this->cooldown($message, $item->cooldown);
        });

        return $page->editOrReply($interaction);
    }

    public function listShipsInteractions(): array
    {
        return [
            'ship:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->ship($interaction, $page),
        ];
    }
}
