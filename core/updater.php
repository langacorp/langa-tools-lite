<?php
/**
 * LANGA Tools Lite — Auto-Updater
 *
 * Hooks into WP's native plugin update system.
 * Checks tools.langa.tv for available updates using HMAC-signed requests.
 * Download URLs are time-limited and signed.
 *
 * @since 1.0.31
 */
if (!defined('ABSPATH')) exit;

class Langa_Tools_Lite_Updater {

    private $plugin_file;
    private $slug = 'langa-tools-lite';
    private $version;
    private $cache_key = 'langa_tools_lite_update_check';
    private $cache_ttl = 43200; // 12 hours

    public function __construct($plugin_file, $version) {
        $this->plugin_file = plugin_basename($plugin_file);
        $this->version     = $version;
    }

    public function init() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
        add_action('load-update-core.php', array($this, 'clear_cache'));
    }

    public function check_update($transient) {
        if (empty($transient->checked)) return $transient;

        $remote = $this->get_remote_info();
        if (!$remote || empty($remote['update'])) return $transient;

        $obj = (object) array(
            'slug'         => $this->slug,
            'plugin'       => $this->plugin_file,
            'new_version'  => $remote['new_version'],
            'package'      => $remote['package'],
            'url'          => 'https://tools.langa.tv',
            'tested'       => isset($remote['tested']) ? $remote['tested'] : '',
            'requires'     => isset($remote['requires']) ? $remote['requires'] : '',
            'requires_php' => isset($remote['requires_php']) ? $remote['requires_php'] : '',
            'icons'        => array(
                'default' => 'https://tools.langa.tv/wp-content/plugins/langa-tools-server/assets/images/langa-logo.svg',
            ),
        );

        $transient->response[$this->plugin_file] = $obj;
        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (!isset($args->slug) || $args->slug !== $this->slug) return $result;

        $remote = $this->get_remote_info();
        if (!$remote || empty($remote['update'])) return $result;

        return (object) array(
            'name'          => isset($remote['name']) ? $remote['name'] : 'LANGA Tools Lite',
            'slug'          => $this->slug,
            'version'       => $remote['new_version'],
            'author'        => '<a href="https://langa.tv">LANGA</a>',
            'homepage'      => 'https://tools.langa.tv',
            'requires'      => isset($remote['requires']) ? $remote['requires'] : '',
            'tested'        => isset($remote['tested']) ? $remote['tested'] : '',
            'requires_php'  => isset($remote['requires_php']) ? $remote['requires_php'] : '',
            'download_link' => $remote['package'],
            'sections'      => array(
                'changelog'   => isset($remote['changelog']) && $remote['changelog'] !== ''
                    ? wp_kses_post($remote['changelog'])
                    : '<p>Aggiornamento disponibile. Consulta il changelog su tools.langa.tv.</p>',
                'description' => '<p>LANGA Tools Lite — free UI/UX toolkit per WordPress.</p>',
            ),
        );
    }

    public function post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $response;
        }

        global $wp_filesystem;
        $install_dir = $result['destination'];
        $proper_dir  = WP_PLUGIN_DIR . '/' . $this->slug;

        if ($install_dir !== $proper_dir) {
            $wp_filesystem->move($install_dir, $proper_dir);
            $result['destination'] = $proper_dir;
        }

        $this->clear_cache();
        activate_plugin($this->plugin_file);

        return $response;
    }

    private function get_remote_info() {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) return $cached;

        $site_key = (string) get_option(LANGA_TOOLS_OPTION_SITE_KEY, '');
        $secret   = (string) get_option(LANGA_TOOLS_OPTION_SECRET, '');

        // Lite can work without credentials — check for free updates
        $ts = time();
        $signature = ($site_key !== '' && $secret !== '')
            ? hash_hmac('sha256', $this->slug . ':' . $this->version . ':' . $ts, $secret)
            : '';

        $server = defined('LANGA_TOOLS_FIXED_SERVER_URL')
            ? rtrim(LANGA_TOOLS_FIXED_SERVER_URL, '/')
            : 'https://tools.langa.tv';

        $url = $server . '/wp-json/langa-tools-server/v1/update-check?' . http_build_query(array(
            'plugin'    => $this->slug,
            'version'   => $this->version,
            'site_key'  => $site_key,
            'ts'        => $ts,
            'signature' => $signature,
        ));

        $resp = wp_remote_get($url, array(
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($resp)) return null;

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return null;

        $data = @json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($data)) return null;

        set_transient($this->cache_key, $data, $this->cache_ttl);
        return $data;
    }

    public function clear_cache() {
        delete_transient($this->cache_key);
    }
}
