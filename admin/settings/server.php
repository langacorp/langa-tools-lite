<?php
if (!defined('ABSPATH')) exit;

/** Signed POST to server to validate current key/secret */
function langa_tools_client_validate_credentials($site_key, $secret) {
  $server = rtrim((string) LANGA_TOOLS_FIXED_SERVER_URL, '/');
  $endpoint = $server . '/wp-json/langa-tools-server/v1/license/check';

  $payload_arr = array(
    'site_url' => home_url(),
    'ts'       => time(),
    'nonce'    => wp_generate_password(12, false, false),
  );
  $payload = wp_json_encode($payload_arr);
  $sig = Langa_Tools_Client_Auth::sign($payload, $secret);

  $resp = wp_remote_post($endpoint, array(
    'timeout' => 12,
    'body' => array(
      'site_key'  => $site_key,
      'payload'   => $payload,
      'signature' => $sig,
    ),
  ));

  if (is_wp_error($resp)) {
    $last = get_option(LANGA_TOOLS_OPTION_LICENSE_LAST, array());
    if (!is_array($last)) $last = array();
    $last['status'] = 'invalid';
    update_option(LANGA_TOOLS_OPTION_LICENSE_LAST, $last);
    return array('ok'=>false,'http'=>0,'body'=>'','error'=>$resp->get_error_message(),'used'=>$endpoint,'status'=>'invalid','reason'=>'server_unreachable');
  }

  $code = (int) wp_remote_retrieve_response_code($resp);
  $body = (string) wp_remote_retrieve_body($resp);
  $json = json_decode($body, true);

  $status = is_array($json) && !empty($json['status']) ? (string)$json['status'] : '';
  $reason = is_array($json) && isset($json['reason']) ? (string)$json['reason'] : '';
  if ($status !== 'valid' && $status !== 'inactive' && $status !== 'invalid') {
    $status = ($code >= 200 && $code < 300) ? 'valid' : 'invalid';
    if ($reason === '') $reason = 'bad_response';
  }

  return array(
    'ok'     => ($status === 'valid'),
    'http'   => $code,
    'body'   => $body,
    'error'  => '',
    'used'   => $endpoint,
    'status' => $status,
    'reason' => $reason,
  );
}
