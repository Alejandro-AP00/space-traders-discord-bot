<?php

namespace App\CommandActions\Waypoints;

use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;

trait ManageJumpGateWaypoints
{
    public function manageJumpGateWaypointsOptions(): array
    {
        $waypoint_symbol = (new Option($this->discord()))
            ->setName('waypoint')
            ->setDescription('The Symbol of the waypoints')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            (new Option($this->discord()))
                ->setName('jump-gate')
                ->setDescription('Gets the Jump Gate details of a Jump Gate waypoint')
                ->setType(Option::SUB_COMMAND)
                ->addOption($waypoint_symbol),
        ];
    }

    public function handleManageJumpGateWaypoints(Interaction $interaction): void
    {
        $this->waypointSymbol = $this->value('jump-gate.waypoint');
        $this->jumpGate($interaction, $this->waypointSymbol);
    }

    public function manageJumpGateWaypointsInteractions(): array
    {
        return [
            'waypoint-jump-gate:{waypoint}' => fn (Interaction $interaction, string $waypoint) => $this->jumpGate($interaction, $waypoint),
        ];
    }

    public function jumpGate(Interaction $interaction, string $waypoint): false|\React\Promise\ExtendedPromiseInterface
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

        return $page->editOrReply($interaction);
    }
}
