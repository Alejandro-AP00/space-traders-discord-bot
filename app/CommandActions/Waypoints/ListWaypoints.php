<?php

namespace App\CommandActions\Waypoints;

use AlejandroAPorras\SpaceTraders\Enums\WaypointTraitSymbol;
use AlejandroAPorras\SpaceTraders\Enums\WaypointType;
use AlejandroAPorras\SpaceTraders\Resources\Waypoint;
use Discord\Builders\Components\Button;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Str;
use Laracord\Discord\Message;

trait ListWaypoints
{
    public function listWaypointsOptions(): array
    {
        $system_symbol = (new Option($this->discord()))
            ->setName('system')
            ->setDescription('The Symbol of the system the waypoint is at')
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
        $list_subcommand = (new Option($this->discord()))
            ->setName('list')
            ->setDescription('List all Waypoints for the current system')
            ->setType(Option::SUB_COMMAND)
            ->addOption($system_symbol)
            ->addOption($waypoint_traits)
            ->addOption($waypoint_types);

        return [
            $list_subcommand,
        ];
    }

    public function handleListWaypoints(Interaction $interaction): void
    {
        $this->systemSymbol = $this->value('list.system');
        $this->waypointTrait = $this->value('list.waypoint_traits');
        $this->waypointType = $this->value('list.waypoint_types');
        $this->waypoints($interaction);
    }

    public function listWaypointAutocomplete(): array
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
        ];
    }

    public function listWaypointInteractions(): array
    {
        return [
            'waypoint:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->waypoints($interaction, $page),
        ];
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

        return $page->editOrReply($interaction);
    }
}
