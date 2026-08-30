<?php
if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('langa_tools_client_checkin')) {
        wp_schedule_event(time(), 'hourly', 'langa_tools_client_checkin');
    }
});

add_action('langa_tools_client_checkin', ['Langa_Tools_Client_API', 'checkin']);