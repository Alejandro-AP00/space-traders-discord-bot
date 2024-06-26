<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Resources\System;
use App\Traits\CanPaginate;
use App\Traits\HasAgent;
use App\Traits\HasMessageUtils;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Illuminate\Support\Str;
use Laracord\Commands\SlashCommand;
use Laracord\Discord\Message;

class Systems extends SlashCommand
{
    use CanPaginate, HasAgent, HasMessageUtils;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'systems';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The systems slash command.';

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

    public string $systemSymbol;

    public function options(): array
    {
        $system_symbol = (new Option($this->discord()))
            ->setName('system')
            ->setDescription('The Symbol of the system')
            ->setType(Option::STRING)
            ->setRequired(true);

        $detail_subcommand = (new Option($this->discord()))
            ->setName('detail')
            ->setDescription('Gets the details of a system')
            ->setType(Option::SUB_COMMAND)
            ->addOption($system_symbol);

        $list_subcommand = (new Option($this->discord()))
            ->setName('list')
            ->setDescription('List all Systems')
            ->setType(Option::SUB_COMMAND);

        return [
            //            $detail_subcommand,
            $list_subcommand,
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

        //        if ($action === 'detail') {
        //            $this->systemSymbol = $this->value('detail.system');
        //            $this->system($interaction, $this->systemSymbol);
        //        }

        if ($action === 'list') {
            $this->systems($interaction);
        }
    }

    public function systems(Interaction $interaction, $page = 1)
    {
        $space_traders = $this->getSpaceTraders($interaction);
        try {
            $waypoints = $space_traders->systems(['page' => $page, 'limit' => 1]);
        } catch (\Exception $exception) {
            $this->sendError($exception, $interaction);

            return false;
        }

        $page = $this->paginate($this->message(), $waypoints, "There's no systems", 'system', function (Message $message, System $system) {
            return $message
                ->authorIcon(null)
                ->authorName('Sector: '.$system->sectorSymbol)
                ->title($system->symbol)
                ->field('Type', Str::title($system->type->value), false)
                ->fields([
                    'x' => $system->x,
                    'y' => $system->y,
                ])
                ->field("\u{200B}", "\u{200B}", false)
                ->fields([
                    'Waypoints' => count($system->waypoints),
                    'Factions' => count($system->factions),
                ]);
        });

        return $page->editOrReply($interaction);
    }

    public function system(Interaction $interaction, string $systemSymbol) {}

    /**
     * The command interaction routes.
     */
    public function interactions(): array
    {
        return [
            'system:{page?}' => fn (Interaction $interaction, ?string $page = null) => $this->systems($interaction, $page),
            //            'system-details:{system}' => fn (Interaction $interaction, string $waypoint) => $this->system($interaction, $waypoint, $interaction->data->values[0] ?? 'General'),
        ];
    }
}
