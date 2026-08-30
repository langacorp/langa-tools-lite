<?php
if (!defined('ABSPATH')) exit;

class Langa_Tools_Client_Auth {
    public static function sign($payload, $secret) {
        return hash_hmac('sha256', $payload, $secret);
    }
}
