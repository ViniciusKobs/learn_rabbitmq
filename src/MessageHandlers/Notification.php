<?php

namespace Src\MessageHandlers;

class Notification
{
    public static function send($message) {
        echo $message;
    }

    public static function receive($message) {
        echo "message " . $message . " received";
    }

}