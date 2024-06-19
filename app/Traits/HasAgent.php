<?php

namespace App\Traits;

use AlejandroAPorras\SpaceTraders\SpaceTraders;
use App\Models\User;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;

trait HasAgent
{
    public function getSpaceTraders(Message|Interaction $source)
    {
        $discord_user = $source instanceof Message ? $source->author : $source->user;

        $user = User::updateOrCreate([
            'discord_id' => $discord_user->id,
        ], [
            'username' => $discord_user->username,
        ]);

        if (empty($user->token)) {
            $message = $this->message()->color('#779ffc')->content('You have not created an agent');

            if ($source instanceof Message) {
                $message->send($source);
            }

            if ($source instanceof Interaction) {
                $source->respondWithMessage(
                    $message->build()
                );
            }
        }

        return new SpaceTraders($user->token);
    }

    public function sendError(\Exception $exception, Message|Interaction $source)
    {

        $message = $this->message()->color('#eb4034')
            ->authorName($exception->getCode())
            ->content($exception->getMessage());

        if (property_exists($exception, 'data') && is_array($exception->data)) {
            foreach ($exception->data as $key => $value) {
                $message->field($key, (string) $value);
            }
        }

        if ($source instanceof Message) {
            $message->send($source);
        }

        if ($source instanceof Interaction) {
            $source->respondWithMessage(
                $message->build()
            );
        }
    }
}
