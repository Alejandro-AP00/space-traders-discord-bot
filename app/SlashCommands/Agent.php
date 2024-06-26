<?php

namespace App\SlashCommands;

use App\Traits\HasAgent;
use App\Traits\HasMessageUtils;
use Discord\Parts\Interactions\Interaction;
use Laracord\Commands\SlashCommand;

class Agent extends SlashCommand
{
    use HasAgent, HasMessageUtils;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'agent';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The agent slash command.';

    /**
     * The command options.
     *
     * @var array
     */
    protected $options = [];

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

    /**
     * Handle the slash command.
     *
     * @param  \Discord\Parts\Interactions\Interaction  $interaction
     */
    public function handle($interaction): void
    {
        $space_traders = $this->getSpaceTraders($interaction);
        $agent = $space_traders->agent();

        $interaction->respondWithMessage(
            $this
                ->message()
                ->authorIcon($interaction->user->avatar)
                ->authorName($agent->symbol)
                ->field('Credits', $agent->credits, false)
                ->field('Starting Faction', $agent->startingFaction->value, false)
                ->field('Head Quarters', $agent->headquarters, false)
                ->field('Ship Count', $agent->shipCount, false)
                ->color('#779ffc')
                ->footerText($agent->accountId)
                ->build()
        );
    }

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [];
    }
}
