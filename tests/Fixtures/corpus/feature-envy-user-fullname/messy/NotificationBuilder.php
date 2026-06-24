<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * Assembles notification copy for a user across several channels.
 */
final class NotificationBuilder
{
    public function welcome(User $user): string
    {
        $name = $user->getFirstName().' '.$user->getLastName();

        return 'Welcome aboard, '.$name.'!';
    }

    public function formalGreeting(User $user): string
    {
        $title = $user->getProfile()->getTitle();
        $name = $user->getFirstName().' '.$user->getLastName();

        return 'Dear '.$title.' '.$name.',';
    }

    public function emailSubject(User $user): string
    {
        return 'A message for '.$user->getFirstName().' '.$user->getLastName();
    }

    public function signatureLine(User $user, NotificationChannel $channel): string
    {
        $title = $user->getProfile()->getTitle();
        $role = $user->getProfile()->getJobRole();
        $name = $user->getFirstName().' '.$user->getLastName();

        return $title.' '.$name.', '.$role.' ('.$channel->value.')';
    }
}
