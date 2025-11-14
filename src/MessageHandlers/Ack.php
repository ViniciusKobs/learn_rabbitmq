<?php

namespace Src\MessageHandlers;

class Ack
{
    public static function handle($message) {
        echo "acknowledged";
    }

    public static function handle2($message) {
        echo "acknowledged2";
    }

}