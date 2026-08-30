<?php
add_filter('404_template', function() {
    if (function_exists('_langa_render_404')) {
        _langa_render_404();
        exit;
    }
}, 1);
