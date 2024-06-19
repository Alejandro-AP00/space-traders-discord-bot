<?php

namespace App\SlashCommands;

use AlejandroAPorras\SpaceTraders\Enums\FactionSymbol;
use AlejandroAPorras\SpaceTraders\SpaceTraders;
use App\Models\User;
use Discord\Parts\Interactions\Command\Choice;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\Interactions\Interaction;
use Laracord\Commands\SlashCommand;

class Register extends SlashCommand
{
    /**
     * The command name.
     *
     * @var string
     */
    protected $name = 'register';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'The register slash command.';

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
     * @param  Interaction  $interaction
     * @return void
     */
    public function handle($interaction)
    {
        $user = User::updateOrCreate([
            'discord_id' => $interaction->user->id,
        ], [
            'username' => $interaction->user->username,
        ]);

        $faction = FactionSymbol::from($this->value('faction'));

        $response = (new SpaceTraders(''))->register($faction, $this->value('agent'));
        $user->token = $response['token'];
        $user->save();
        $agent = $response['agent'];

        $interaction->respondWithMessage(
            $this
                ->message()
                ->title($agent->symbol)
                ->field('Credits', $agent->credits, false)
                ->field('Starting Faction', $agent->startingFaction->value, false)
                ->field('Head Quarters', $agent->headquarters, false)
                ->field('Ship Count', $agent->shipCount, false)
                ->color('#779ffc')
                ->footerText($agent->accountId)
                ->build()
        );
    }

    public function options(): array
    {
        $faction_option = (new Option($this->discord()))
            ->setName('faction')
            ->setDescription('The faction to create your agent with')
            ->setType(Option::STRING)
            ->setRequired(true);

        foreach (FactionSymbol::cases() as $case) {
            $faction_option->addChoice(
                (Choice::new($this->discord(), $case->name, $case->value)),
            );
        }

        $agent_option = (new Option($this->discord()))
            ->setName('agent')
            ->setDescription('The agent to create your agent with')
            ->setType(Option::STRING)
            ->setRequired(true);

        return [
            $faction_option,
            $agent_option,
        ];
    }
}
