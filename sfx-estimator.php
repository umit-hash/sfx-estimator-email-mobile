<?php
/**
 * Plugin Name: SacramentoFix — Instant Estimate
 * Plugin URI: https://sacramentofix.com/estimate/
 * Description: A multi-step instant estimate wizard for repair shops. Type → Make → Series → Model → Issues → Contact, with pricing from Local JSON or Google Sheets.
 * Version: 1.6.10
 * Author: UmitSahin
 * Author URI: https://sacramentofix.com/
 * Text Domain: sfx-estimator
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */
/*
Plugin Name: SacramentoFix — Instant Estimate Wizard
Description: Multi-step repair estimate tool with explicit Series step, Media Library images, and Google Sheet support. Shortcode: [sfx_estimator]
Version: 1.6.10
Author: UmitSahin
Author URI: https://sacramentofix.com/
License: GPL-2.0+
*/
if (!defined('ABSPATH')) exit;

define('SFX_ESTIMATOR_VERSION', '1.6.10');
define('SFX_ESTIMATOR_SLUG', 'sfx-estimator');
define('SFX_ESTIMATOR_PATH', plugin_dir_path(__FILE__));
define('SFX_ESTIMATOR_URL', plugin_dir_url(__FILE__));

function sfx_estimator_sms_debug_enabled() {
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }
    $default = (defined('SFX_ESTIMATOR_SMS_DEBUG') ? (bool) SFX_ESTIMATOR_SMS_DEBUG : (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG));
    $enabled = (bool) apply_filters('sfx_estimator_sms_debug_enabled', $default);
    return $enabled;
}

function sfx_estimator_debug_log($message, array $context = array()) {
    if (!sfx_estimator_sms_debug_enabled()) {
        return;
    }
    if (!empty($context)) {
        $encoded = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);
        if ($encoded && $encoded !== '[]') {
            $message .= ' ' . $encoded;
        }
    }
    error_log('[sfx_estimator] ' . $message);
}

function sfx_estimator_mask_phone_for_log($number) {
    $number = preg_replace('/\s+/', '', (string) $number);
    if ($number === '') {
        return '';
    }
    $len = strlen($number);
    $prefix_len = $number[0] === '+' ? 3 : 2;
    $suffix_len = 2;
    if ($len <= ($prefix_len + $suffix_len)) {
        return substr($number, 0, 1) . str_repeat('*', max(0, $len - 1));
    }
    $prefix = substr($number, 0, $prefix_len);
    $suffix = substr($number, -$suffix_len);
    $masked_mid = str_repeat('*', max(0, $len - ($prefix_len + $suffix_len)));
    return $prefix . $masked_mid . $suffix;
}

function sfx_estimator_trim_for_log($text, $limit = 400) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $text = preg_replace('/\s+/', ' ', $text);
    if (strlen($text) > $limit) {
        $text = substr($text, 0, $limit) . '…';
    }
    return $text;
}

function sfx_estimator_default_email_template() {
    return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Fast Repair Estimate</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin:0; padding:0; background:#f7f7f7; }
    .container { width:100%; background:#f7f7f7; }
    .wrapper { width:100%; max-width:600px; margin:0 auto; background:#ffffff; }
    .pad { padding:24px; font-family:Arial,Helvetica,sans-serif; color:#222222; line-height:1.5; }
    h1 { font-size:22px; margin:0 0 12px 0; }
    .price { font-size:20px; font-weight:700; margin:8px 0 16px 0; }
    ul { padding-left:18px; margin:12px 0; }
    .btn { display:inline-block; text-decoration:none; }
    .footer { font-size:12px; color:#666666; padding:16px 24px; }
    @media (prefers-color-scheme: dark) {
      body { background:#050505; }
      .wrapper { background:#121212; }
      .pad, .footer { color:#e5e5e5; }
    }
  </style>
</head>
<body>
  <div style="display:none;max-height:0;overflow:hidden;">
    Walk in today - many fixes in 15-60 minutes. Directions inside.
  </div>
  <table class="container" role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center">
        <table class="wrapper" role="presentation" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pad">
              <h1>Hi {first_name|there}, your estimate is ready</h1>
              <div>Device: <strong>{device_heading}</strong></div>
              <div>Issue: <strong>{issues}</strong></div>
              <div class="price">Estimated total: {price_total_formatted}</div>
              <p>{turnaround_sentence_html}</p>
              <ul>
                <li><strong>{warranty}</strong> for peace of mind</li>
                <li>Expert technicians for iPhone, iPad, MacBook, Samsung and PS5</li>
                <li><strong>Walk in</strong> - no appointment needed</li>
                <li>Transparent, upfront pricing</li>
              </ul>
              {issue_disclaimer_html}
              <table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
                <tr>
                  <td bgcolor="#1a73e8" style="border-radius:6px;">
                    <a href="{maps_short_email}" class="btn" style="font-size:16px; line-height:1; padding:14px 22px; color:#ffffff; display:inline-block;" aria-label="Get directions to Fast Repair">
                      Get Directions
                    </a>
                  </td>
                </tr>
              </table>
              <p style="font-size:12px; margin:0 0 18px 0;">
                Directions link: <a href="{maps_short_email}" style="color:#1a73e8; text-decoration:none;">{maps_short_email}</a>
              </p>
              <p>
                Need help? Call us at <a href="tel:{store_phone_e164}">{store_phone}</a>. {store_hours_summary|Walk in anytime - we are ready for you.}
              </p>
              <p>Visit us: {store_address_line1}, {store_city}, {store_region} {store_postal}</p>
              <p style="font-size:12px; color:#666666;">
                Estimate ID: {estimate_id} • Valid until {estimate_expires_local}
                <br>Note: Pricing may adjust for liquid damage or additional issues found at intake. Sales tax applies.
              </p>
            </td>
          </tr>
          <tr>
            <td class="footer">
              <div><strong>{store_name}</strong> • {store_address_line1}, {store_city}, {store_region} {store_postal}</div>
              <div><a href="{store_website}" style="color:#1a73e8;">{store_website}</a> • {store_phone}</div>
              <div style="margin-top:8px;">You are receiving this email because you requested an estimate from {store_name}. Reply if you have any questions.</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function sfx_estimator_default_options() {
    $default_sms = <<<'SMS'
Hi {first_name}! {business_name} estimate {price_total_formatted} for {make} {model} ({issues}). Open 7 days: {store_hours}. Visit {store_address} or call {store_phone}. Warranty: {warranty}. Details: {store_website}. Walk-ins welcome. Ticket {estimate_ref}. Reply STOP to opt out.
SMS;

    $default_email = sfx_estimator_default_email_template();

    return array(
        'business_name' => get_bloginfo('name') ?: 'Fast Repair',
        'store_phone'   => '(916) 477-5995',
        'store_phone_e164' => '+19164775995',
        'default_country_code' => '',
        'store_address' => '4424 Freeport Blvd, Suite 4, Sacramento, CA 95822',
        'store_address_line1' => '4424 Freeport Blvd, Suite 4',
        'store_city'    => 'Sacramento',
        'store_region'  => 'CA',
        'store_postal'  => '95822',
        'store_hours'   => 'Mon-Sat 9:00 AM-7:30 PM | Sun 10:00 AM-7:30 PM',
        'store_website' => 'https://sacramentofix.com',
        'email_from'    => get_bloginfo('admin_email'),
        'reply_to'      => '',
        'email_to'      => get_bloginfo('admin_email'),
        'twilio_sid'    => '',
        'twilio_token'  => '',
        'twilio_from'   => '',
        'warranty_text' => '3-month parts & labor warranty',
        'sms_template'  => $default_sms,
        'email_subject'=> 'Your Fast Repair estimate - ready today + 3-month warranty',
        'email_template'=> $default_email,
        'store_maps_destination' => '4424 Freeport Blvd, Suite 4, Sacramento, CA 95822',
        'store_timezone' => 'America/Los_Angeles',
        'turnaround_default' => '15–60 minutes',
        'estimate_valid_hours' => 72,
        'sales_tax_rate' => 0.0,
        'store_hours_schedule' => array(),
        'utm_source' => 'estimator',
        'utm_campaign' => 'instant_estimate',
        'price_matrix'  => array(),
        'tile_images'   => array(),
        'make_images'   => array(),
        'series_images' => array(),
        'model_images'  => array(),
        'show_breadcrumbs' => false,
        'data_source'   => 'local',
        'sheet_csv_url' => '',
        'apps_script_url' => '',
        'apps_script_token' => '',
        'sync_last'     => 0
    );
}

function sfx_estimator_guess_country_code_from_number($number) {
    $digits = preg_replace('/\D/', '', (string) $number);
    if ($digits === '') {
        return '';
    }
    // NANP-style (US/CA) numbers usually arrive as 11 digits starting with 1.
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '1';
    }
    // Heuristic for non-NANP: prefer a 2–3 digit country code when the number is longer.
    if (strlen($digits) >= 13) {
        return substr($digits, 0, 3);
    }
    if (strlen($digits) >= 11) {
        return substr($digits, 0, 2);
    }
    return '';
}

function sfx_estimator_get_default_country_code() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $code = '';
    $opt = get_option('sfx_estimator_options');
    if (is_array($opt)) {
        $code = isset($opt['default_country_code']) ? preg_replace('/\D/', '', (string) $opt['default_country_code']) : '';
        if ($code === '') {
            foreach (array('twilio_from', 'store_phone_e164', 'store_phone') as $key) {
                if (!empty($opt[$key])) {
                    $guess = sfx_estimator_guess_country_code_from_number($opt[$key]);
                    if ($guess !== '') {
                        $code = $guess;
                        break;
                    }
                }
            }
        }
    }
    if ($code === '') {
        $code = '1';
    }
    $filtered = preg_replace('/\D/', '', (string) apply_filters('sfx_estimator_default_country_code', $code));
    if ($filtered === '') {
        $filtered = '1';
    }
    $cached = $filtered;
    return $cached;
}

function sfx_estimator_sanitize_e164($value) {
    $value = trim((string) $value);
    if ($value === '') {
        sfx_estimator_debug_log('sanitize_e164_skipped_empty_input');
        return '';
    }
    $stripped = preg_replace('/[^0-9+]/', '', $value);
    if ($stripped === '') {
        sfx_estimator_debug_log('sanitize_e164_no_digits', array('input' => sfx_estimator_mask_phone_for_log($value)));
        return '';
    }
    // Handle numbers that already include a leading plus sign.
    if ($stripped[0] === '+') {
        $digits = preg_replace('/[^0-9]/', '', substr($stripped, 1));
    } else {
        $digits = preg_replace('/\D/', '', $stripped);
    }
    if ($digits === '') {
        sfx_estimator_debug_log('sanitize_e164_digits_empty', array('input' => sfx_estimator_mask_phone_for_log($value)));
        return '';
    }
    $default_country = sfx_estimator_get_default_country_code();
    // If the number starts with 00 (international dialing prefix), convert to +.
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    $removed_trunk_zero = false;
    if ($digits !== '' && $digits[0] === '0' && strlen($digits) >= 9) {
        // Strip local trunk prefix (e.g., 0) so we can prepend the default country.
        $digits = ltrim($digits, '0');
        $removed_trunk_zero = true;
    }
    // Auto-prepend a default country code when users omit it (e.g. 10-digit NANP numbers).
    if (($removed_trunk_zero && $default_country !== '') || strlen($digits) === 10) {
        if ($default_country !== '') {
            $digits = $default_country . $digits;
        }
    } elseif (strlen($digits) === 11 && $digits[0] === '1') {
        // Common case where the leading 1 was provided without plus.
        // Nothing to change; keep as-is.
    }
    $result = '+' . $digits;
    sfx_estimator_debug_log('sanitize_e164_success', array(
        'input' => sfx_estimator_mask_phone_for_log($value),
        'result' => sfx_estimator_mask_phone_for_log($result),
        'length' => strlen($digits),
        'default_country' => $default_country,
        'trunk_stripped' => $removed_trunk_zero,
    ));
    return $result;
}

function sfx_estimator_prepare_twilio_sender($raw_from) {
    $raw_from = trim((string) $raw_from);
    $sid_pattern = '/^MG[A-Z0-9]{32}$/i';
    if ($raw_from !== '' && preg_match($sid_pattern, $raw_from)) {
        return array(
            'field' => 'MessagingServiceSid',
            'value' => $raw_from,
            'masked' => $raw_from,
            'is_sanitized' => true,
            'type' => 'messaging_service',
        );
    }
    $sanitized = $raw_from !== '' ? sfx_estimator_sanitize_e164($raw_from) : '';
    $value = $sanitized ?: $raw_from;
    return array(
        'field' => 'From',
        'value' => $value,
        'masked' => sfx_estimator_mask_phone_for_log($value),
        'is_sanitized' => (bool) $sanitized,
        'type' => 'number',
    );
}

function sfx_estimator_validate_timezone($tz) {
    $tz = trim((string) $tz);
    if ($tz === '') {
        return 'UTC';
    }
    try {
        new DateTimeZone($tz);
        return $tz;
    } catch (Exception $e) {
        return 'UTC';
    }
}

function sfx_estimator_format_device_heading($make, $model) {
    $make = trim((string) $make);
    $model = trim((string) $model);
    if ($model === '' && $make === '') {
        return '';
    }
    if ($model === '') {
        return $make;
    }
    $normalized_model = preg_replace('/\s+/', ' ', $model);
    if ($make !== '' && stripos($normalized_model, $make) === 0) {
        return $normalized_model;
    }
    return trim($make . ' ' . $normalized_model);
}

function sfx_estimator_get_ticket_meta() {
    static $meta = null;
    if (null !== $meta) {
        return $meta;
    }

    $option_key = 'sfx_estimator_ticket_offset';
    $seed = (int) apply_filters('sfx_estimator_ticket_start_number', 4118);
    $stored = get_option($option_key, null);

    if (!is_array($stored) || !isset($stored['offset'], $stored['seed']) || (int) $stored['seed'] !== $seed) {
        global $wpdb;
        $table = $wpdb->prefix . 'sfx_quotes';
        $current_max = (int) $wpdb->get_var("SELECT MAX(id) FROM {$table}");
        if ($current_max <= 0) {
            $offset = $seed - 1;
        } else {
            $offset = $seed - $current_max;
        }
        $stored = array(
            'offset' => $offset,
            'seed'   => $seed,
        );
        update_option($option_key, $stored);
    }

    $meta = array(
        'offset' => (int) apply_filters('sfx_estimator_quote_offset', (int) ($stored['offset'] ?? 0)),
        'ticket_prefix' => apply_filters('sfx_estimator_ticket_prefix', 'QR-67-'),
        'estimate_prefix' => apply_filters('sfx_estimator_estimate_prefix', 'EST-'),
        'seed'   => $seed,
    );

    if (!is_string($meta['ticket_prefix'])) {
        $meta['ticket_prefix'] = (string) $meta['ticket_prefix'];
    }
    if (!is_string($meta['estimate_prefix'])) {
        $meta['estimate_prefix'] = (string) $meta['estimate_prefix'];
    }
    $meta['prefix'] = $meta['ticket_prefix'];

    return $meta;
}

function sfx_estimator_display_quote_id($quote_id) {
    $quote_id = (int) $quote_id;
    $meta = sfx_estimator_get_ticket_meta();

    if ($quote_id <= 0) {
        return $meta['ticket_prefix'] . max(0, $quote_id);
    }

    $number = (int) $meta['offset'] + $quote_id;
    if ($number <= 0) {
        $number = $quote_id;
    }

    return $meta['ticket_prefix'] . $number;
}

function sfx_estimator_generate_estimate_id($quote_id) {
    $quote_id = (int) $quote_id;
    $meta = sfx_estimator_get_ticket_meta();
    if ($quote_id <= 0) {
        return $meta['estimate_prefix'] . max(0, $quote_id);
    }
    $number = (int) $meta['offset'] + $quote_id;
    if ($number <= 0) {
        $number = $quote_id;
    }
    return $meta['estimate_prefix'] . $number;
}

function sfx_estimator_format_iso8601($timestamp, $timezone) {
    $timezone = sfx_estimator_validate_timezone($timezone);
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    $dt = new DateTime('@' . intval($timestamp));
    $dt->setTimezone($tz);
    return $dt->format('c');
}

function sfx_estimator_format_local_time($timestamp, DateTimeZone $tz, $format = 'g:i A') {
    $dt = new DateTime('@' . intval($timestamp));
    $dt->setTimezone($tz);
    return $dt->format($format);
}

function sfx_estimator_normalize_schedule(array $schedule) {
    $normalized = array();
    $days = array('sun','mon','tue','wed','thu','fri','sat');
    foreach ($days as $day) {
        $normalized[$day] = array();
    }
    foreach ($schedule as $day => $entry) {
        $key = strtolower(substr($day, 0, 3));
        if (!array_key_exists($key, $normalized)) {
            continue;
        }
        if (is_array($entry)) {
            $normalized[$key] = array(
                'open' => isset($entry['open']) ? trim((string) $entry['open']) : '',
                'close' => isset($entry['close']) ? trim((string) $entry['close']) : '',
                'closed' => !empty($entry['closed']),
            );
        } else {
            $normalized[$key] = array('open' => '', 'close' => '', 'closed' => true);
        }
    }
    return $normalized;
}

function sfx_estimator_compute_hours_summary(array $opt, $timestamp_gmt = null, &$context = null) {
    $schedule = isset($opt['store_hours_schedule']) && is_array($opt['store_hours_schedule'])
        ? $opt['store_hours_schedule']
        : array();
    if (empty($schedule)) {
        if (func_num_args() >= 3) {
            $context = array(
                'summary' => $opt['store_hours'] ?? '',
                'is_open' => null,
                'closes_at' => null,
                'closes_label' => '',
                'opens_at' => null,
                'opens_label' => '',
            );
        }
        return $opt['store_hours'] ?? '';
    }

    $timezone = sfx_estimator_validate_timezone($opt['store_timezone'] ?? 'UTC');
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }

    $now_ts_gmt = $timestamp_gmt !== null ? intval($timestamp_gmt) : current_time('timestamp', true);
    $now_local = new DateTime('@' . $now_ts_gmt);
    $now_local->setTimezone($tz);

    $schedule = sfx_estimator_normalize_schedule($schedule);
    $day_key = strtolower($now_local->format('D'));
    $today = $schedule[$day_key] ?? array();

    $open_today = null;
    $close_today = null;
    $is_closed_today = empty($today) || !empty($today['closed']) || empty($today['open']) || empty($today['close']);

    if (!$is_closed_today) {
        $open_today = DateTime::createFromFormat('Y-m-d H:i', $now_local->format('Y-m-d') . ' ' . $today['open'], $tz) ?: null;
        $close_today = DateTime::createFromFormat('Y-m-d H:i', $now_local->format('Y-m-d') . ' ' . $today['close'], $tz) ?: null;
        if ($open_today && $close_today && $close_today <= $open_today) {
            $close_today->modify('+1 day');
        }
    }

    $summary = '';
    $is_open_now = false;
    $close_ts = null;
    $close_label = '';
    $next_open_ts = null;
    $next_open_label = '';

    if ($open_today && $close_today) {
        $close_ts = $close_today->getTimestamp();
        $close_label = sfx_estimator_format_local_time($close_ts, $tz);
        if ($now_local >= $open_today && $now_local < $close_today) {
            $is_open_now = true;
            $summary = sprintf('Open now until %s', $close_label);
        } elseif ($now_local < $open_today) {
            $next_open_ts = $open_today->getTimestamp();
            $next_open_label = 'today at ' . sfx_estimator_format_local_time($next_open_ts, $tz);
            $summary = sprintf('Opens today at %s', sfx_estimator_format_local_time($next_open_ts, $tz));
        }
    }

    if ($summary === '') {
        for ($i = 1; $i <= 7; $i++) {
            $future = clone $now_local;
            $future->modify('+' . $i . ' day');
            $future_key = strtolower($future->format('D'));
            $entry = $schedule[$future_key] ?? array();
            $is_closed = empty($entry) || !empty($entry['closed']) || empty($entry['open']) || empty($entry['close']);
            if ($is_closed) {
                continue;
            }
            $open_dt = DateTime::createFromFormat('Y-m-d H:i', $future->format('Y-m-d') . ' ' . $entry['open'], $tz);
            if (!$open_dt) {
                continue;
            }
            $next_open_ts = $open_dt->getTimestamp();
            $day_label = $i === 1 ? 'tomorrow' : $future->format('l');
            $next_open_label = $day_label . ' at ' . sfx_estimator_format_local_time($next_open_ts, $tz);
            $summary = sprintf(
                'Closed now - opens %s at %s',
                $day_label,
                sfx_estimator_format_local_time($next_open_ts, $tz)
            );
            break;
        }
    }

    if ($summary === '') {
        $summary = $opt['store_hours'] ?? '';
    }

    if (func_num_args() >= 3) {
        $context = array(
            'summary' => $summary,
            'is_open' => $is_open_now,
            'closes_at' => $close_ts,
            'closes_label' => $close_label,
            'opens_at' => $next_open_ts,
            'opens_label' => $next_open_label,
        );
    }

    return $summary;
}

function sfx_estimator_maps_base_url($opt) {
    $destination = trim($opt['store_maps_destination'] ?? '');
    if ($destination === '') {
        $destination = trim($opt['store_address'] ?? '');
    }
    if ($destination === '') {
        $parts = array(
            trim($opt['store_address_line1'] ?? ''),
            trim($opt['store_city'] ?? ''),
            trim($opt['store_region'] ?? ''),
            trim($opt['store_postal'] ?? ''),
        );
        $destination = trim(implode(', ', array_filter($parts)));
    }
    if ($destination === '') {
        return '';
    }
    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination);
}

function sfx_estimator_generate_short_code($estimate_id, $channel = 'sms') {
    $estimate_id = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $estimate_id));
    if ($estimate_id === '') {
        try {
            $estimate_id = strtoupper(bin2hex(random_bytes(4)));
        } catch (Exception $e) {
            $estimate_id = strtoupper(wp_generate_password(6, false, false));
        }
    }
    $channel_key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $channel));
    if ($channel_key === '' || $channel_key === 'sms') {
        return $estimate_id;
    }
    return $estimate_id . '-' . strtoupper($channel_key);
}

function sfx_estimator_store_short_link($estimate_id, $channel, $target_url) {
    global $wpdb;
    $code = sfx_estimator_generate_short_code($estimate_id, $channel);
    $table = $wpdb->prefix . 'sfx_links';
    $channel_key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $channel));
    if ($channel_key === '') {
        $channel_key = 'sms';
    }
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s AND channel = %s LIMIT 1", $code, $channel_key), ARRAY_A);
    if ($existing) {
        if ($existing['target_url'] !== $target_url) {
            $wpdb->update($table, array('target_url' => $target_url), array('id' => intval($existing['id'])), array('%s'), array('%d'));
        }
        $id = (int) $existing['id'];
    } else {
        $wpdb->insert($table, array(
            'code' => $code,
            'estimate_id' => $estimate_id,
            'channel' => $channel_key,
            'target_url' => $target_url,
        ), array('%s','%s','%s','%s'));
        $id = (int) $wpdb->insert_id;
    }
    return array(
        'id' => $id,
        'code' => $code,
        'channel' => $channel_key,
        'target_url' => $target_url,
        'short_url' => home_url('/d/' . rawurlencode($code)),
        'estimate_id' => $estimate_id,
    );
}

function sfx_estimator_find_short_link($code) {
    global $wpdb;
    $table = $wpdb->prefix . 'sfx_links';
    $code = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $code));
    if ($code === '') {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s LIMIT 1", $code), ARRAY_A);
}

function sfx_estimator_record_link_click(array $link, $channel = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'sfx_link_clicks';
    $channel_key = $channel ? preg_replace('/[^a-z0-9]/', '', strtolower($channel)) : $link['channel'];
    $wpdb->insert($table, array(
        'link_id' => intval($link['id']),
        'estimate_id' => $link['estimate_id'],
        'channel' => $channel_key,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : '',
        'source_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ), array('%d','%s','%s','%s','%s'));
    sfx_estimator_cancel_followups($link['estimate_id']);
    $quote_id = sfx_estimator_find_quote_id_by_estimate($link['estimate_id']);
    if ($quote_id) {
        sfx_estimator_update_followup_stage($quote_id, 'converted');
    }
}

function sfx_estimator_find_quote_id_by_estimate($estimate_id) {
    if (!$estimate_id) {
        return 0;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sfx_quotes';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE estimate_id = %s LIMIT 1", $estimate_id));
}

function sfx_estimator_cancel_followups($estimate_id) {
    $quote_id = sfx_estimator_find_quote_id_by_estimate($estimate_id);
    if (!$quote_id) {
        return;
    }
    wp_clear_scheduled_hook('sfx_estimator_sms_followup', array($quote_id, 'nudge'));
    wp_clear_scheduled_hook('sfx_estimator_sms_followup', array($quote_id, 'final'));
}

function sfx_estimator_schedule_sms_followups($quote_id, $estimate_id) {
    $quote_id = intval($quote_id);
    if ($quote_id <= 0 || !$estimate_id) {
        return;
    }
    if (!apply_filters('sfx_estimator_enable_followups', true, $quote_id, $estimate_id)) {
        return;
    }
    $base = current_time('timestamp', true);
    if (!wp_next_scheduled('sfx_estimator_sms_followup', array($quote_id, 'nudge'))) {
        wp_schedule_single_event($base + DAY_IN_SECONDS, 'sfx_estimator_sms_followup', array($quote_id, 'nudge'));
    }
    if (!wp_next_scheduled('sfx_estimator_sms_followup', array($quote_id, 'final'))) {
        wp_schedule_single_event($base + (3 * DAY_IN_SECONDS), 'sfx_estimator_sms_followup', array($quote_id, 'final'));
    }
}

function sfx_estimator_followup_allowed($estimate_id, $stage) {
    global $wpdb;
    $table = $wpdb->prefix . 'sfx_link_clicks';
    $click_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE estimate_id = %s", $estimate_id));
    $allowed = ($click_count === 0);
    return (bool) apply_filters('sfx_estimator_followup_allowed', $allowed, $estimate_id, $stage, array('click_count' => $click_count));
}

function sfx_estimator_update_followup_stage($quote_id, $stage) {
    global $wpdb;
    $table = $wpdb->prefix . 'sfx_quotes';
    $wpdb->update($table, array(
        'followup_stage' => $stage,
        'followup_updated' => gmdate('Y-m-d H:i:s', current_time('timestamp', true)),
    ), array('id' => intval($quote_id)), array('%s','%s'), array('%d'));
}

add_action('sfx_estimator_sms_followup', 'sfx_estimator_handle_sms_followup', 10, 2);
function sfx_estimator_handle_sms_followup($quote_id, $stage) {
    $quote_id = intval($quote_id);
    if ($quote_id <= 0) {
        return;
    }
    $stage_key = ($stage === 'final') ? 'final' : 'nudge';

    global $wpdb;
    $table = $wpdb->prefix . 'sfx_quotes';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $quote_id), ARRAY_A);
    if (!$row) {
        return;
    }

    $estimate_id = $row['estimate_id'];
    if (!$estimate_id) {
        $estimate_id = sfx_estimator_generate_estimate_id($quote_id);
    }

    $current_stage = $row['followup_stage'];
    if ($stage_key === 'nudge' && in_array($current_stage, array('nudge_sent', 'final_sent', 'suppressed', 'converted'), true)) {
        return;
    }
    if ($stage_key === 'final' && in_array($current_stage, array('final_sent', 'suppressed', 'converted'), true)) {
        return;
    }

    if (!sfx_estimator_followup_allowed($estimate_id, $stage_key)) {
        sfx_estimator_cancel_followups($estimate_id);
        sfx_estimator_update_followup_stage($quote_id, 'suppressed');
        return;
    }

    $opt = get_option('sfx_estimator_options');
    if (!is_array($opt)) {
        $opt = array();
    }
    $opt = wp_parse_args($opt, sfx_estimator_default_options());
    if (empty($opt['twilio_sid']) || empty($opt['twilio_token']) || empty($opt['twilio_from'])) {
        return;
    }

    if (!in_array($row['notify'], array('sms', 'both'), true)) {
        return;
    }

    $to_number = sfx_estimator_sanitize_e164($row['phone']);
    if (!$to_number) {
        return;
    }

    $issues_list = array();
    if (!empty($row['issues'])) {
        $issues_list = array_map('trim', explode(',', $row['issues']));
        $issues_list = array_filter($issues_list, function ($value) {
            return $value !== '';
        });
    }

    $context_input = array(
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'phone' => $row['phone'],
        'email' => $row['email'],
        'device_type' => $row['device_type'],
        'make' => $row['make'],
        'series' => $row['series'],
        'model' => $row['model'],
        'issues' => $issues_list,
        'subtotal' => (float) $row['estimate_total'],
    );

    $context = sfx_estimator_prepare_estimate_context($quote_id, $context_input, $opt);
    $payload = $context['payload'];
    $message = sfx_estimator_build_sms_message($payload, $stage_key === 'nudge' ? 'nudge' : 'final');

    $sender = sfx_estimator_prepare_twilio_sender($opt['twilio_from']);
    if ($sender['value'] === '') {
        return;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($opt['twilio_sid']) . '/Messages.json';
    $body = array('To' => $to_number, 'Body' => $message);
    $body[$sender['field']] = $sender['value'];
    $args = array(
        'body' => $body,
        'headers' => array('Authorization' => 'Basic ' . base64_encode($opt['twilio_sid'] . ':' . $opt['twilio_token'])),
        'timeout' => 20,
    );
    $resp = wp_remote_post($url, $args);
    if (is_wp_error($resp)) {
        error_log('Twilio SMS follow-up error: ' . $resp->get_error_message());
        return;
    }

    if ($stage_key === 'final') {
        sfx_estimator_cancel_followups($estimate_id);
    }
    sfx_estimator_update_followup_stage($quote_id, $stage_key . '_sent');
}

function sfx_estimator_maybe_upgrade_email_template(&$opt, $persist = true) {
    if (!is_array($opt)) {
        return false;
    }
    if (!isset($opt['email_template']) || !is_string($opt['email_template'])) {
        return false;
    }
    $updated = false;
    $tpl = $opt['email_template'];

    $legacy_markers = array(
        'padding:32px 0;',
        '.container{ width:100% !important;',
        'box-shadow:0 20px 40px rgba(15,37,74,0.12);'
    );

    $needs_reset = false;
    if (stripos($tpl, '<html') === false) {
        $needs_reset = true;
    }
    if (!$needs_reset && stripos($tpl, '<style') !== false) {
        $needs_reset = true;
    }
    if (!$needs_reset && stripos($tpl, '<style') === false && preg_match('/^\s*body\s*\{[^\}]+\}/i', $tpl)) {
        $needs_reset = true;
    }
    if (!$needs_reset) {
        foreach ($legacy_markers as $marker) {
            if (strpos($tpl, $marker) !== false) {
                $needs_reset = true;
                break;
            }
        }
    }

    if ($needs_reset) {
        $opt['email_template'] = sfx_estimator_default_email_template();
        $tpl = $opt['email_template'];
        $updated = true;
    }

    if (strpos($tpl, '<!--<![endif]-->') !== false || strpos($tpl, '<!--[if !mso]><!-- -->') !== false) {
        $opt['email_template'] = str_replace(array('<!--[if !mso]><!-- -->', '<!--<![endif]-->'), '', $opt['email_template']);
        $updated = true;
    }

    if (strpos($tpl, 'super-happy-customers.png') !== false) {
        $new_block = <<<HTML
<h3 style="margin:0 0 14px 0; font-size:17px; color:#111827;">Happy customer stories</h3>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-radius:18px; overflow:hidden; border:1px solid #e2e8f0;">
                  <tr>
                    <td style="padding:0;">
                      <img src="https://sacramentofix.com/wp-content/uploads/2025/11/Happy-Customer-While-Getting-Their-Device-Fixed@0.3x.png" alt="Fast Repair technician helping a smiling customer" width="600" style="display:block; width:100%; height:auto;">
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:18px 22px; font-size:14px; line-height:1.7; color:#0f172a; background-color:#f9fafb;">
                      <p style="margin:0 0 12px 0;"><strong>"Crystal-clear screen again in under an hour."</strong> — Alex R.</p>
                      <p style="margin:0 0 12px 0;"><strong>"They recovered the photos I thought were gone forever."</strong> — Sophia M.</p>
                      <p style="margin:0;">Best shop in Sacramento for honest diagnostics, quick turnarounds, and a friendly team you can trust.</p>
                    </td>
                  </tr>
                </table>
HTML;
        $tpl_updated = preg_replace(
            '/<h3[^>]*>Happy customer stories<\/h3>\s*<table[^>]*>.*?super-happy-customers\.png.*?<\/table>/is',
            $new_block,
            $tpl,
            1,
            $replace_count
        );
        if ($replace_count > 0 && is_string($tpl_updated)) {
            $opt['email_template'] = $tpl_updated;
            $tpl = $tpl_updated;
            $updated = true;
        } else {
        $opt['email_template'] = str_replace('super-happy-customers.png', 'Happy-Customer-While-Getting-Their-Device-Fixed@0.3x.png', $opt['email_template']);
        $updated = true;
    }
    }

    if (strpos($tpl, 'Happy-Customer-While-Getting-Their-Cell-Phone-Fixed.png') !== false) {
        $opt['email_template'] = str_replace(
            'Happy-Customer-While-Getting-Their-Cell-Phone-Fixed.png',
            'Happy-Customer-While-Getting-Their-Device-Fixed@0.3x.png',
            $opt['email_template']
        );
        $tpl = $opt['email_template'];
        $updated = true;
    }

    if (!empty($opt['sms_template']) && strpos($opt['sms_template'], '{estimate_ref}') === false) {
        $needle = 'Reply STOP to opt out.';
        if (strpos($opt['sms_template'], $needle) !== false) {
            $opt['sms_template'] = str_replace($needle, 'Ticket {estimate_ref}. ' . $needle, $opt['sms_template']);
        } else {
            $opt['sms_template'] .= ' Ticket {estimate_ref}.';
        }
        $updated = true;
    }

    $legacy_badge_pattern = '/<table[^>]*margin:0 0 24px 0;[^>]*>.*?Fixed Right the First Time.*?<\/table>/is';
    $modern_badge_block = <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px 0;">
                  <tr>
                    <td align="center" style="padding:0;">
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                        <tr>
                          <td align="center" style="padding:0; font-size:0;">
                            <!--[if mso]>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                              <tr>
                                <td width="184" style="padding:0 8px 12px;" valign="top">
                            <![endif]-->
                            <div style="display:inline-block; width:32%; max-width:184px; min-width:92px; vertical-align:top; padding:0 2px 12px;">
                              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:16px; background-color:#eef2ff; border:1px solid #c7d2fe;">
                                <tr>
                                  <td style="padding:18px 16px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
                                    <div style="margin:0 auto 10px; width:52px; height:52px; line-height:52px; border-radius:50%; background-color:#dbeafe; font-size:24px; color:#1e3a8a;">&#9889;</div>
                                    <p style="margin:0 0 6px 0; font-size:15px; font-weight:700; color:#111827;">Lightning Fast</p>
                                    <p style="margin:0; font-size:13px; line-height:1.6; color:#4b5563;">Most devices ready in under 60 minutes.</p>
                                  </td>
                                </tr>
                              </table>
                            </div>
                            <!--[if mso]></td><td width="184" style="padding:0 8px 12px;" valign="top"><![endif]-->
                            <div style="display:inline-block; width:32%; max-width:184px; min-width:92px; vertical-align:top; padding:0 2px 12px;">
                              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:16px; background-color:#ecfdf5; border:1px solid #bbf7d0;">
                                <tr>
                                  <td style="padding:18px 16px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
                                    <div style="margin:0 auto 10px; width:52px; height:52px; line-height:52px; border-radius:50%; background-color:#d1fae5; font-size:24px; color:#047857;">&#128176;</div>
                                    <p style="margin:0 0 6px 0; font-size:15px; font-weight:700; color:#047857;">Transparent Pricing</p>
                                    <p style="margin:0; font-size:13px; line-height:1.6; color:#047857;">Every estimate includes parts, labor, and tax upfront.</p>
                                  </td>
                                </tr>
                              </table>
                            </div>
                            <!--[if mso]></td><td width="184" style="padding:0 8px 12px;" valign="top"><![endif]-->
                            <div style="display:inline-block; width:32%; max-width:184px; min-width:92px; vertical-align:top; padding:0 2px 12px;">
                              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:16px; background-color:#fef2f2; border:1px solid #fecaca;">
                                <tr>
                                  <td style="padding:18px 16px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
                                    <div style="margin:0 auto 10px; width:52px; height:52px; line-height:52px; border-radius:50%; background-color:#fee2e2; font-size:24px; color:#b91c1c;">&#128737;</div>
                                    <p style="margin:0 0 6px 0; font-size:15px; font-weight:700; color:#b91c1c;">Warranty Guaranteed</p>
                                    <p style="margin:0; font-size:13px; line-height:1.6; color:#b91c1c;">Repairs include our {warranty} for peace of mind.</p>
                                  </td>
                                </tr>
                              </table>
                            </div>
                            <!--[if mso]></td></tr></table><![endif]-->
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
HTML;
    $tpl_with_badges = preg_replace($legacy_badge_pattern, $modern_badge_block, $tpl, 1, $badge_replacements);
    if ($badge_replacements > 0 && is_string($tpl_with_badges)) {
        $opt['email_template'] = $tpl_with_badges;
        $tpl = $tpl_with_badges;
        $updated = true;
    }

    $open_hours_snippet = '<strong>Open:</strong> {store_hours}';
    if (strpos($tpl, $open_hours_snippet) !== false) {
        $opt['email_template'] = str_replace(
            $open_hours_snippet,
            '<strong>Hours:</strong> {store_hours}',
            $opt['email_template']
        );
        $tpl = $opt['email_template'];
        $updated = true;
    }

    $old_walkins = 'Walk-ins welcome—no appointment needed. Reply to this email if you\'d like us to hold parts or confirm turnaround time.';
    $new_walkins = 'Walk in anytime during business hours — no appointment needed! Most repairs are completed in under an hour, so your device will be ready faster than you think.';
    if (strpos($tpl, $old_walkins) !== false) {
        $opt['email_template'] = str_replace($old_walkins, $new_walkins, $opt['email_template']);
        $tpl = $opt['email_template'];
        $updated = true;
    }

    $reply_sentence = ' Reply to this email if you\'d like us to hold parts or confirm turnaround time.';
    if (strpos($tpl, $reply_sentence) !== false) {
        $opt['email_template'] = str_replace($reply_sentence, '', $opt['email_template']);
        $tpl = $opt['email_template'];
        $updated = true;
    }

    $old_third_review = 'Best shop in Sacramento for honest diagnostics, quick turnarounds, and a friendly team you can trust.';
    $new_third_review = '<strong>"Best shop in Sacramento. Prices were right and the repair was done right."</strong> — Daniel K.';
    if (strpos($tpl, $old_third_review) !== false) {
        $opt['email_template'] = str_replace($old_third_review, $new_third_review, $opt['email_template']);
        $tpl = $opt['email_template'];
        $updated = true;
    }

    if (strpos($tpl, 'Estimate #{quote_id}') !== false || strpos($tpl, 'Estimate #') !== false) {
        $opt['email_template'] = str_replace('Estimate #{quote_id}', 'Estimate {estimate_ref}', $opt['email_template']);
        $opt['email_template'] = str_replace('Estimate #' . '{quote_id}', 'Estimate {estimate_ref}', $opt['email_template']);
        $tpl = $opt['email_template'];
        $updated = true;
    }

    if (isset($opt['email_subject']) && is_string($opt['email_subject']) && strpos($opt['email_subject'], '{make} {model}') !== false) {
        $opt['email_subject'] = str_replace('{make} {model}', '{device_heading}', $opt['email_subject']);
        $updated = true;
    }

    if ($updated && $persist) {
        update_option('sfx_estimator_options', $opt);
    }
    return $updated;
}

function sfx_estimator_allowed_email_template_tags() {
    static $allowed = null;
    if (null !== $allowed) {
        return $allowed;
    }

    $allowed = wp_kses_allowed_html('post');

    $allowed['html'] = array(
        'lang'    => true,
        'xmlns'   => true,
        'xmlns:v' => true,
        'xmlns:o' => true,
    );
    $allowed['head'] = array();
    $allowed['title'] = array();
    $allowed['meta'] = array(
        'charset'    => true,
        'content'    => true,
        'http-equiv' => true,
        'name'       => true,
    );
    $allowed['style'] = array(
        'media' => true,
        'type'  => true,
    );

    if (!isset($allowed['body'])) {
        $allowed['body'] = array();
    }
    $allowed['body']['class'] = true;
    $allowed['body']['style'] = true;

    $email_tags = array(
        'table','tbody','thead','tfoot','tr','td','th',
        'div','span','p','a','img','h1','h2','h3','h4','h5','h6',
        'ul','ol','li','section'
    );

    foreach ($email_tags as $tag) {
        if (!isset($allowed[$tag])) {
            $allowed[$tag] = array();
        }
        $allowed[$tag]['class'] = true;
        $allowed[$tag]['style'] = true;
        $allowed[$tag]['align'] = true;
        $allowed[$tag]['width'] = true;
        $allowed[$tag]['height'] = true;
        $allowed[$tag]['valign'] = true;
        $allowed[$tag]['bgcolor'] = true;
        $allowed[$tag]['id'] = true;
    }

    if (isset($allowed['table'])) {
        $allowed['table']['cellpadding'] = true;
        $allowed['table']['cellspacing'] = true;
        $allowed['table']['border'] = true;
        $allowed['table']['summary'] = true;
        $allowed['table']['role'] = true;
    }

    if (isset($allowed['td'])) {
        $allowed['td']['colspan'] = true;
        $allowed['td']['rowspan'] = true;
        $allowed['td']['role'] = true;
    }

    if (isset($allowed['th'])) {
        $allowed['th']['colspan'] = true;
        $allowed['th']['rowspan'] = true;
        $allowed['th']['role'] = true;
    }

    if (isset($allowed['a'])) {
        $allowed['a']['target'] = true;
        $allowed['a']['rel'] = true;
        $allowed['a']['name'] = true;
    }

    if (isset($allowed['img'])) {
        $allowed['img']['style'] = true;
        $allowed['img']['class'] = true;
        $allowed['img']['border'] = true;
        $allowed['img']['alt'] = true;
        $allowed['img']['height'] = true;
        $allowed['img']['width'] = true;
        $allowed['img']['src'] = true;
    }

    return $allowed;
}

register_activation_hook(__FILE__, 'sfx_estimator_activate');
function sfx_estimator_activate() {
    global $wpdb;
    $table = $wpdb->prefix . 'sfx_quotes';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        phone VARCHAR(40) NULL,
        email VARCHAR(200) NULL,
        notify VARCHAR(20) NULL,
        device_type VARCHAR(100) NULL,
        make VARCHAR(100) NULL,
        series VARCHAR(150) NULL,
        model VARCHAR(150) NULL,
        issues TEXT NULL,
        notes TEXT NULL,
        estimate_total DECIMAL(10,2) NULL,
        estimate_tax DECIMAL(10,2) NULL,
        estimate_grand_total DECIMAL(10,2) NULL,
        estimate_ref VARCHAR(60) NULL,
        estimate_id VARCHAR(60) NULL,
        shortlink_sms VARCHAR(200) NULL,
        shortlink_email VARCHAR(200) NULL,
        followup_stage VARCHAR(20) NULL,
        followup_updated DATETIME NULL,
        source_ip VARCHAR(45) NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) {$charset_collate};";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $links_table = $wpdb->prefix . 'sfx_links';
    $sql_links = "CREATE TABLE {$links_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(120) NOT NULL,
        estimate_id VARCHAR(60) NOT NULL,
        channel VARCHAR(40) NOT NULL,
        target_url TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code_channel (code, channel)
    ) {$charset_collate};";
    dbDelta($sql_links);

    $clicks_table = $wpdb->prefix . 'sfx_link_clicks';
    $sql_clicks = "CREATE TABLE {$clicks_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        link_id BIGINT UNSIGNED NOT NULL,
        estimate_id VARCHAR(60) NOT NULL,
        channel VARCHAR(40) NOT NULL,
        user_agent TEXT NULL,
        source_ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY link_channel (link_id, channel)
    ) {$charset_collate};";
    dbDelta($sql_clicks);

    if (!get_option('sfx_estimator_options')) {
        add_option('sfx_estimator_options', sfx_estimator_default_options());
    }

    // Remove any legacy cron
    wp_clear_scheduled_hook('sfx_estimator_cron_sync');
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'sfx_estimator_deactivate');
function sfx_estimator_deactivate(){
    wp_clear_scheduled_hook('sfx_estimator_cron_sync');
    wp_clear_scheduled_hook('sfx_estimator_sms_followup');
    flush_rewrite_rules();
}


function sfx_estimator_admin_notice($msg, $type='success'){
    printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), wp_kses_post($msg));
}
add_action('admin_menu', function() {
    add_menu_page('Estimator','SacramentoFix Estimator','manage_options','sfx-estimator-admin','sfx_estimator_render_admin','dashicons-calculator',58);
});

function sfx_estimator_admin_enqueue($hook){
    if ($hook !== 'toplevel_page_sfx-estimator-admin') return;
    wp_enqueue_media();
    wp_enqueue_script('sfx-estimator-admin', SFX_ESTIMATOR_URL.'assets/js/sfx-estimator-admin.js', array('jquery'), SFX_ESTIMATOR_VERSION, true);
    wp_localize_script('sfx-estimator-admin', 'SFXEstimatorAdmin', array(
        'nonce' => wp_create_nonce('sfx_estimator_admin'),
        'placeholder' => SFX_ESTIMATOR_URL.'assets/css/placeholder.png'
    ));
    wp_enqueue_style('sfx-estimator-css', SFX_ESTIMATOR_URL . 'assets/css/sfx-estimator.css', array(), SFX_ESTIMATOR_VERSION);
}
add_action('admin_enqueue_scripts', 'sfx_estimator_admin_enqueue');

function sfx_estimator_render_admin() {
    if (!current_user_can('manage_options')) return;
    $opt = get_option('sfx_estimator_options');
    if (!is_array($opt)) $opt = array();
    $opt = wp_parse_args($opt, sfx_estimator_default_options());
    sfx_estimator_maybe_upgrade_email_template($opt);
    if (!isset($opt['make_images']) || !is_array($opt['make_images'])) $opt['make_images'] = array();
    $message = '';

    if (isset($_POST['sfx_estimator_test'])) {
        check_admin_referer('sfx_estimator_test', 'sfx_estimator_test_nonce');
        $test = sfx_estimator_sync_from_source(true);
        if ($test['ok']) $message .= '<div class="updated"><p>Test succeeded: fetched '.intval($test['rows']).' rows.</p></div>';
        else $message .= '<div class="error"><p>Test failed: '.esc_html($test['message']).'</p></div>';
    }
    if (isset($_POST['sfx_estimator_sync'])) {
        check_admin_referer('sfx_estimator_sync', 'sfx_estimator_sync_nonce');
        $res = sfx_estimator_sync_from_source(false);
        if ($res['ok']) $message .= '<div class="updated"><p>Synced from source (' . esc_html($res['source']) . '): '.intval($res['rows']).' rows.</p></div>';
        else $message .= '<div class="error"><p>Sync failed: '.esc_html($res['message']).'</p></div>';
    }
    if (isset($_POST['sfx_estimator_load_extended'])) {
        check_admin_referer('sfx_estimator_load_extended');
        $path = SFX_ESTIMATOR_PATH . 'assets/data/price_matrix_extended_2025-10.json';
        if (file_exists($path)) {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $opt['price_matrix'] = $decoded;
                update_option('sfx_estimator_options', $opt);
                $message .= '<div class="updated"><p>Loaded extended catalog (Oct 2025).</p></div>';
            } else {
                $message .= '<div class="error"><p>Could not decode built-in extended catalog.</p></div>';
            }
        } else {
            $message .= '<div class="error"><p>Built-in extended catalog file missing.</p></div>';
        }
    }
    if (isset($_GET['sfx_estimator_export']) && check_admin_referer('sfx_estimator_export')) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="price_matrix_export.json"');
        echo wp_json_encode($opt['price_matrix'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_POST['sfx_estimator_save'])) {

        // Tolerant nonce verification to avoid "The link you followed has expired."
        $nonce_ok = false;
        if (isset($_POST['sfx_estimator_save_nonce'])) {
            $nonce_ok = wp_verify_nonce($_POST['sfx_estimator_save_nonce'], 'sfx_estimator_save');
        }
        if (!$nonce_ok && isset($_POST['sfx_estimator_admin_nonce'])) {
            $nonce_ok = wp_verify_nonce($_POST['sfx_estimator_admin_nonce'], 'sfx_estimator_admin');
        }
        if (!$nonce_ok && !current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'sfx-estimator'));
        }

        $opt['business_name'] = sanitize_text_field($_POST['business_name'] ?? '');
        $opt['store_phone']   = sanitize_text_field($_POST['store_phone'] ?? '');
        $opt['store_phone_e164'] = sfx_estimator_sanitize_e164($_POST['store_phone_e164'] ?? ($opt['store_phone_e164'] ?? ''));
        $country_code_raw = sanitize_text_field($_POST['default_country_code'] ?? ($opt['default_country_code'] ?? ''));
        $country_code_digits = preg_replace('/\D/', '', $country_code_raw);
        $opt['default_country_code'] = $country_code_digits !== '' ? $country_code_digits : '';
        $opt['store_address'] = sanitize_text_field($_POST['store_address'] ?? '');
        $opt['store_address_line1'] = sanitize_text_field($_POST['store_address_line1'] ?? ($opt['store_address_line1'] ?? ''));
        $opt['store_city']    = sanitize_text_field($_POST['store_city'] ?? ($opt['store_city'] ?? ''));
        $opt['store_region']  = sanitize_text_field($_POST['store_region'] ?? ($opt['store_region'] ?? ''));
        $opt['store_postal']  = sanitize_text_field($_POST['store_postal'] ?? ($opt['store_postal'] ?? ''));
        $opt['store_hours']   = sanitize_textarea_field($_POST['store_hours'] ?? ($opt['store_hours'] ?? ''));
        $opt['store_website'] = esc_url_raw($_POST['store_website'] ?? ($opt['store_website'] ?? ''));
        $opt['store_maps_destination'] = sanitize_text_field($_POST['store_maps_destination'] ?? ($opt['store_maps_destination'] ?? ''));
        $opt['store_timezone'] = sfx_estimator_validate_timezone($_POST['store_timezone'] ?? ($opt['store_timezone'] ?? 'America/Los_Angeles'));
        $opt['turnaround_default'] = sanitize_text_field($_POST['turnaround_default'] ?? ($opt['turnaround_default'] ?? ''));
        $opt['estimate_valid_hours'] = max(1, intval($_POST['estimate_valid_hours'] ?? ($opt['estimate_valid_hours'] ?? 72)));
        $opt['sales_tax_rate'] = max(0, floatval($_POST['sales_tax_rate'] ?? ($opt['sales_tax_rate'] ?? 0)));
        $opt['utm_source'] = sanitize_key($_POST['utm_source'] ?? ($opt['utm_source'] ?? 'estimator'));
        $opt['utm_campaign'] = sanitize_key($_POST['utm_campaign'] ?? ($opt['utm_campaign'] ?? 'instant_estimate'));
        $schedule_raw = isset($_POST['store_hours_schedule']) ? wp_unslash($_POST['store_hours_schedule']) : '';
        if ($schedule_raw !== '') {
            $decoded = json_decode($schedule_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $opt['store_hours_schedule'] = $decoded;
            } else {
                $message .= '<div class="notice notice-warning"><p>Store hours schedule JSON could not be parsed. Keeping previous value.</p></div>';
            }
        } elseif (!is_array($opt['store_hours_schedule'])) {
            $opt['store_hours_schedule'] = array();
        }
        $opt['email_from']    = sanitize_email($_POST['email_from'] ?? '');
        $opt['reply_to']      = sanitize_email($_POST['reply_to'] ?? '');
        $opt['email_to']      = sanitize_email($_POST['email_to'] ?? '');

        $opt['warranty_text'] = sanitize_text_field($_POST['warranty_text'] ?? ($opt['warranty_text'] ?? ''));
        $opt['twilio_sid']    = sanitize_text_field($_POST['twilio_sid'] ?? '');
        $opt['twilio_token']  = sanitize_text_field($_POST['twilio_token'] ?? '');
        $opt['twilio_from']   = sanitize_text_field($_POST['twilio_from'] ?? '');
        $opt['sms_template']  = sanitize_textarea_field($_POST['sms_template'] ?? $opt['sms_template']);
        $opt['email_subject'] = sanitize_text_field($_POST['email_subject'] ?? $opt['email_subject']);
        // Unsash to prevent backslashes (\\) showing in sent emails when quotes are used.
        if (isset($_POST['email_template'])) {
            $email_template = wp_unslash($_POST['email_template']);
            if (current_user_can('unfiltered_html')) {
                $opt['email_template'] = $email_template;
            } else {
                $opt['email_template'] = wp_kses($email_template, sfx_estimator_allowed_email_template_tags());
            }
        }

        $opt['show_breadcrumbs'] = isset($_POST['show_breadcrumbs']) ? true : false;

        // Tile images
        $opt['tile_images'] = array();
        if (!empty($_POST['tile_images']) && is_array($_POST['tile_images'])) {
            foreach ($_POST['tile_images'] as $k => $v) {
                $id = intval($v);
                if ($id > 0) $opt['tile_images'][$k] = $id;
            }
        }

        // Brand images
        $make_map = array();
        if (!empty($_POST['make_type']) && is_array($_POST['make_type'])) {
            $types  = $_POST['make_type'];
            $labels = $_POST['make_label'] ?? array();
            $ids    = $_POST['make_image'] ?? array();
            foreach ($types as $i => $raw_type) {
                $type  = sanitize_text_field($raw_type);
                $label = isset($labels[$i]) ? sanitize_text_field($labels[$i]) : '';
                $id    = isset($ids[$i]) ? intval($ids[$i]) : 0;
                if ($type && $label && $id > 0) {
                    if (!isset($make_map[$type])) $make_map[$type] = array();
                    $make_map[$type][$label] = $id;
                }
            }
        }
        $opt['make_images'] = $make_map;

        // Series images
        $series_map = array();
        if (!empty($_POST['series_label']) && is_array($_POST['series_label'])) {
            $labels = $_POST['series_label'];
            $ids    = $_POST['series_image'] ?? array();
            foreach ($labels as $i => $label) {
                $label = sanitize_text_field($label);
                $id = isset($ids[$i]) ? intval($ids[$i]) : 0;
                if ($label && $id > 0) $series_map[$label] = $id;
            }
        }
        $opt['series_images'] = $series_map;

        // Model images
        $model_map = array();
        if (!empty($_POST['model_label']) && is_array($_POST['model_label'])) {
            $labels = $_POST['model_label'];
            $ids    = $_POST['model_image'] ?? array();
            foreach ($labels as $i => $label) {
                $label = sanitize_text_field($label);
                $id = isset($ids[$i]) ? intval($ids[$i]) : 0;
                if ($label && $id > 0) $model_map[$label] = $id;
            }
        }
        $opt['model_images'] = $model_map;

        // Data source
        $opt['data_source'] = in_array($_POST['data_source'] ?? 'local', array('local','gsheet_csv','apps_script'), true) ? $_POST['data_source'] : 'local';
        $opt['sheet_csv_url'] = esc_url_raw($_POST['sheet_csv_url'] ?? '');
        $opt['apps_script_url'] = esc_url_raw($_POST['apps_script_url'] ?? '');
        $opt['apps_script_token'] = sanitize_text_field($_POST['apps_script_token'] ?? '');

        // Local JSON
        if (isset($_POST['price_matrix'])) {
            $json = wp_unslash($_POST['price_matrix'] ?? '');
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $opt['price_matrix'] = $decoded;
            } else {
                $message .= '<div class="error"><p>Local JSON parse failed: '.esc_html(json_last_error_msg()).'</p></div>';
            }
        }

        update_option('sfx_estimator_options', $opt);
        $message .= '<div class="updated"><p>Settings saved.</p></div>';
    }

    $json = json_encode($opt['price_matrix'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    echo $message;
    ?>
    <div class="wrap">
        <h1>SacramentoFix — Instant Estimate (by <strong>UmitSahin</strong>)</h1>

        <form method="post">
            <?php wp_nonce_field('sfx_estimator_save', 'sfx_estimator_save_nonce'); ?><?php wp_nonce_field('sfx_estimator_admin', 'sfx_estimator_admin_nonce'); ?>
            <h2 class="title">Business Info</h2>
            <table class="form-table">
                <tr><th>Business Name</th><td><input type="text" name="business_name" value="<?php echo esc_attr($opt['business_name']); ?>" class="regular-text" /></td></tr>
                <tr><th>Store Phone (display)</th><td><input type="text" name="store_phone" value="<?php echo esc_attr($opt['store_phone']); ?>" class="regular-text" placeholder="(916) 477-5995"/></td></tr>
                <tr><th>Store Phone (E.164)</th><td><input type="text" name="store_phone_e164" value="<?php echo esc_attr($opt['store_phone_e164']); ?>" class="regular-text" placeholder="+19164775995"/><p class="description">Used for tappable links and carrier compliance.</p></td></tr>
                <tr><th>Default Country Code (SMS)</th><td><input type="text" name="default_country_code" value="<?php echo esc_attr($opt['default_country_code']); ?>" class="regular-text" style="max-width:140px;" placeholder="1"/><p class="description">Prepended when visitors enter local numbers without +country. Examples: 1 (US/CA), 44 (UK), 90 (TR).</p></td></tr>
                <tr><th>Address (display)</th><td><input type="text" name="store_address" value="<?php echo esc_attr($opt['store_address']); ?>" class="regular-text" placeholder="4424 Freeport Blvd, Suite 4, Sacramento, CA 95822"/></td></tr>
                <tr><th>Address Line 1</th><td><input type="text" name="store_address_line1" value="<?php echo esc_attr($opt['store_address_line1']); ?>" class="regular-text" placeholder="4424 Freeport Blvd, Suite 4"/></td></tr>
                <tr><th>City / Region / Postal</th><td>
                    <input type="text" name="store_city" value="<?php echo esc_attr($opt['store_city']); ?>" class="regular-text" style="max-width:160px;" placeholder="Sacramento"/>
                    <input type="text" name="store_region" value="<?php echo esc_attr($opt['store_region']); ?>" class="regular-text" style="max-width:80px;" placeholder="CA"/>
                    <input type="text" name="store_postal" value="<?php echo esc_attr($opt['store_postal']); ?>" class="regular-text" style="max-width:140px;" placeholder="95822"/>
                </td></tr>
                <tr><th>Maps Destination</th><td><input type="text" name="store_maps_destination" value="<?php echo esc_attr($opt['store_maps_destination']); ?>" class="regular-text" placeholder="4424 Freeport Blvd, Suite 4, Sacramento, CA 95822"/><p class="description">Used for Google Maps deep links (auto-encoded).</p></td></tr>
                <tr><th>Time Zone</th><td><input type="text" name="store_timezone" value="<?php echo esc_attr($opt['store_timezone']); ?>" class="regular-text" placeholder="America/Los_Angeles"/><p class="description">Needed for “Open now / Closes at …” messaging.</p></td></tr>
                <tr><th>Store Hours (fallback text)</th><td><textarea name="store_hours" rows="2" class="large-text code" placeholder="Mon–Sat 9:00 AM–7:30 PM; Sun 10:00 AM–7:30 PM"><?php echo esc_textarea($opt['store_hours']); ?></textarea><p class="description">Shown if a structured schedule is unavailable.</p></td></tr>
                <tr><th>Store Hours Schedule (JSON)</th><td><textarea name="store_hours_schedule" rows="4" class="large-text code" placeholder='{"mon":{"open":"09:00","close":"19:30"},"sun":{"open":"10:00","close":"19:30"}}'><?php echo esc_textarea(is_array($opt['store_hours_schedule']) ? wp_json_encode($opt['store_hours_schedule'], JSON_PRETTY_PRINT) : ''); ?></textarea><p class="description">Optional 24h format (local time). Used to compute “Open now” summaries.</p></td></tr>
                <tr><th>Default Turnaround</th><td><input type="text" name="turnaround_default" value="<?php echo esc_attr($opt['turnaround_default']); ?>" class="regular-text" placeholder="15–60 minutes"/></td></tr>
                <tr><th>Estimate Valid (hours)</th><td><input type="number" min="1" name="estimate_valid_hours" value="<?php echo esc_attr($opt['estimate_valid_hours']); ?>" class="small-text" /> hours</td></tr>
                <tr><th>Sales Tax Rate (%)</th><td><input type="number" step="0.01" min="0" name="sales_tax_rate" value="<?php echo esc_attr($opt['sales_tax_rate']); ?>" class="small-text" /> <p class="description">Enter 0 for none. Example: 8.75</p></td></tr>
                <tr><th>Website</th><td><input type="url" name="store_website" value="<?php echo esc_attr($opt['store_website']); ?>" class="regular-text" placeholder="https://sacramentofix.com"/></td></tr>
                <tr><th>UTM Source/Campaign</th><td>
                    <input type="text" name="utm_source" value="<?php echo esc_attr($opt['utm_source']); ?>" class="regular-text" style="max-width:160px;" placeholder="estimator"/>
                    <input type="text" name="utm_campaign" value="<?php echo esc_attr($opt['utm_campaign']); ?>" class="regular-text" style="max-width:200px;" placeholder="instant_estimate"/>
                    <p class="description">UTM medium is set automatically per channel (sms/email). Content uses device family.</p>
                </td></tr>
            </table>

            <h2 class="title">Email & SMS</h2>
            <table class="form-table">
                <tr><th>From Email</th><td><input type="email" name="email_from" value="<?php echo esc_attr($opt['email_from']); ?>" class="regular-text" placeholder="noreply@yourdomain.com"/><p class="description">Use an address on your domain (improves deliverability).</p></td></tr>
                <tr><th>Reply‑To Email</th><td><input type="email" name="reply_to" value="<?php echo esc_attr($opt['reply_to']); ?>" class="regular-text" placeholder="<?php echo esc_attr($opt['email_to']); ?>"/></td></tr>
                <tr><th>Admin Email (notifications)</th><td><input type="email" name="email_to" value="<?php echo esc_attr($opt['email_to']); ?>" class="regular-text" /></td></tr>
                <tr><th>Warranty Text</th><td><input type="text" name="warranty_text" value="<?php echo esc_attr($opt['warranty_text']); ?>" class="regular-text" placeholder="3-month parts & labor warranty"/></td></tr>
                <tr><th>Email Subject</th><td><input type="text" name="email_subject" value="<?php echo esc_attr($opt['email_subject']); ?>" class="regular-text"/></td></tr>
                <tr><th>Email Template (HTML allowed)</th><td><textarea name="email_template" rows="10" class="large-text code"><?php echo esc_textarea($opt['email_template']); ?></textarea>
                    <p class="description">Available tags: {first_name}, {device_heading}, {issues}, {total}, {turnaround_sentence_html}, {warranty}, {store_phone}, {store_phone_e164}, {store_address_line1}, {store_city}, {store_region}, {store_postal}, {store_hours_summary}, {store_website}, {maps_short_email}, {estimate_id}, {estimate_ref}, {estimate_expires_local}, {issue_disclaimer_html}</p></td></tr>
            </table>

            <h3>SMS (Twilio)</h3>
            <table class="form-table">
                <tr><th>Twilio Account SID</th><td><input type="text" name="twilio_sid" value="<?php echo esc_attr($opt['twilio_sid']); ?>" class="regular-text"/></td></tr>
                <tr><th>Twilio Auth Token</th><td><input type="text" name="twilio_token" value="<?php echo esc_attr($opt['twilio_token']); ?>" class="regular-text"/></td></tr>
                <tr><th>Twilio From Number</th><td><input type="text" name="twilio_from" value="<?php echo esc_attr($opt['twilio_from']); ?>" class="regular-text" placeholder="+1XXXXXXXXXX"/></td></tr>
                <tr><th>SMS Template</th><td><textarea name="sms_template" rows="4" class="large-text code"><?php echo esc_textarea($opt['sms_template']); ?></textarea>
                    <p class="description">The live SMS copy is generated automatically for compliance. This legacy template is kept for reference.</p>
                    <p class="description">Available tags: {first_name}, {device_heading}, {issues}, {total}, {turnaround_sms_full}, {warranty}, {store_phone}, {store_phone_e164}, {store_address_line1}, {store_city}, {maps_short_sms}, {estimate_id}</p></td></tr>
            </table>

            <h2 class="title">Display</h2>
            <table class="form-table">
                <tr><th>Breadcrumbs</th><td><label><input type="checkbox" name="show_breadcrumbs" value="1" <?php checked($opt['show_breadcrumbs'], true); ?>/> Show tiny step breadcrumbs (Type / Make / Series / …)</label></td></tr>
            </table>

            <h2 class="title">Tile Images (Device Types)</h2>
            <p>Select images from Media Library for: Computer, Cell Phone, Tablet, Gaming Console, Others.</p>
            <table class="form-table">
                <?php
                $device_types = array('Computer','Cell Phone','Tablet','Gaming Console','Others');
                foreach ($device_types as $type):
                    $field_id = 'tile_images_' . md5($type);
                    $attachment_id = isset($opt['tile_images'][$type]) ? intval($opt['tile_images'][$type]) : 0;
                    $preview = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : SFX_ESTIMATOR_URL.'assets/css/placeholder.png';
                ?>
                <tr>
                    <th><?php echo esc_html($type); ?></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="<?php echo esc_url($preview); ?>" alt="" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
                            <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="tile_images[<?php echo esc_attr($type); ?>]" value="<?php echo $attachment_id; ?>">
                            <button type="button" class="button sfx-media-select" data-target="<?php echo esc_attr($field_id); ?>">Select Image</button>
                            <button type="button" class="button sfx-media-clear" data-target="<?php echo esc_attr($field_id); ?>">Clear</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php
            $matrix_types = array();
            if (!empty($opt['price_matrix']) && is_array($opt['price_matrix'])) {
                $matrix_types = array_keys($opt['price_matrix']);
            }
            $existing_types = array_keys($opt['make_images']);
            $known_types = array_unique(array_filter(array_merge($device_types, $matrix_types, $existing_types)));
            sort($known_types);
            ?>
            <datalist id="sfx-type-list">
                <?php foreach ($known_types as $type_option): ?>
                    <option value="<?php echo esc_attr($type_option); ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <h2 class="title">Brand Images (optional)</h2>
            <p>Upload tile images for the brand step (e.g., iPhone, Samsung) shown after selecting the main device type.</p>
            <table class="form-table" id="sfx-make-table">
                <tr><th>Device Type</th><th>Brand Label</th><th>Image</th></tr>
                <?php
                $make_rows = array();
                foreach ($opt['make_images'] as $type_key => $make_set) {
                    foreach ($make_set as $label => $id) {
                        $make_rows[] = array(
                            'type'  => $type_key,
                            'label' => $label,
                            'id'    => intval($id)
                        );
                    }
                }
                if (empty($make_rows)) {
                    $make_rows[] = array('type' => '', 'label' => '', 'id' => 0);
                }
                foreach ($make_rows as $row):
                    $url = $row['id'] ? wp_get_attachment_image_url($row['id'], 'thumbnail') : SFX_ESTIMATOR_URL.'assets/css/placeholder.png';
                    $field_id = 'make_image_' . uniqid();
                ?>
                <tr>
                    <td><input type="text" name="make_type[]" value="<?php echo esc_attr($row['type']); ?>" class="regular-text" list="sfx-type-list"/></td>
                    <td><input type="text" name="make_label[]" value="<?php echo esc_attr($row['label']); ?>" class="regular-text"/></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="<?php echo esc_url($url); ?>" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
                            <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="make_image[]" value="<?php echo intval($row['id']); ?>"/>
                            <button type="button" class="button sfx-media-select" data-target="<?php echo esc_attr($field_id); ?>">Select Image</button>
                            <button type="button" class="button sfx-media-clear" data-target="<?php echo esc_attr($field_id); ?>">Clear</button>
                            <button type="button" class="button sfx-make-remove">Remove Row</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p><button type="button" class="button" id="sfx-add-make-row">Add Row</button></p>

            <h2 class="title">Series Images (optional)</h2>
            <p>Add a row for each series label (e.g., <code>17 Series</code>, <code>16 Series</code>, <code>iPad Pro 12.9</code>, <code>S25 Series</code>) and select an image.</p>
            <table class="form-table" id="sfx-series-table">
                <tr><th>Label</th><th>Image</th></tr>
                <?php
                $rows = $opt['series_images'];
                if (empty($rows)) $rows = array('' => 0);
                foreach ($rows as $label => $id):
                    $url = $id ? wp_get_attachment_image_url($id, 'thumbnail') : SFX_ESTIMATOR_URL.'assets/css/placeholder.png';
                ?>
                <tr>
                    <td><input type="text" name="series_label[]" value="<?php echo esc_attr($label); ?>" class="regular-text"/></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="<?php echo esc_url($url); ?>" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
                            <input type="hidden" name="series_image[]" value="<?php echo intval($id); ?>"/>
                            <button type="button" class="button sfx-media-select-series">Select Image</button>
                            <button type="button" class="button sfx-series-remove">Remove</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p><button type="button" class="button" id="sfx-add-series-row">Add Row</button></p>

            <h2 class="title">Model Images (optional)</h2>
            <p>Add a row for any specific model label (e.g., <code>iPhone 17 Pro Max</code>, <code>Galaxy S25 Ultra</code>) to display a picture on the model tiles. If a model image is missing, the series image will be used.</p>
            <table class="form-table" id="sfx-model-table">
                <tr><th>Model Label</th><th>Image</th></tr>
                <?php
                $mrows = $opt['model_images'];
                if (empty($mrows)) $mrows = array('' => 0);
                foreach ($mrows as $label => $id):
                    $url = $id ? wp_get_attachment_image_url($id, 'thumbnail') : SFX_ESTIMATOR_URL.'assets/css/placeholder.png';
                ?>
                <tr>
                    <td><input type="text" name="model_label[]" value="<?php echo esc_attr($label); ?>" class="regular-text"/></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="<?php echo esc_url($url); ?>" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px;">
                            <input type="hidden" name="model_image[]" value="<?php echo intval($id); ?>"/>
                            <button type="button" class="button sfx-media-select-model">Select Image</button>
                            <button type="button" class="button sfx-model-remove">Remove</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p><button type="button" class="button" id="sfx-add-model-row">Add Row</button></p>

            <h2 class="title">Data Source (manual sync)</h2>
            <table class="form-table">
                <tr>
                    <th>Use pricing from</th>
                    <td>
                        <label><input type="radio" name="data_source" value="local" <?php checked($opt['data_source'],'local'); ?>/> Local JSON (below)</label><br>
                        <label><input type="radio" name="data_source" value="gsheet_csv" <?php checked($opt['data_source'],'gsheet_csv'); ?>/> Google Sheet (Published CSV URL)</label><br>
                        <label><input type="radio" name="data_source" value="apps_script" <?php checked($opt['data_source'],'apps_script'); ?>/> Google Apps Script (JSON endpoint)</label>
                    </td>
                </tr>
                <tr>
                    <th>Google Sheet CSV URL</th>
                    <td><input type="url" name="sheet_csv_url" value="<?php echo esc_attr($opt['sheet_csv_url']); ?>" class="regular-text" placeholder="https://docs.google.com/spreadsheets/d/.../export?format=csv&gid=0"/></td>
                </tr>
                <tr>
                    <th>Apps Script Web App URL</th>
                    <td><input type="url" name="apps_script_url" value="<?php echo esc_attr($opt['apps_script_url']); ?>" class="regular-text" placeholder="https://script.google.com/macros/s/.../exec"/></td>
                </tr>
                <tr>
                    <th>Apps Script Token (optional)</th>
                    <td><input type="text" name="apps_script_token" value="<?php echo esc_attr($opt['apps_script_token']); ?>" class="regular-text" placeholder="shared secret for your web app"/></td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button" name="sfx_estimator_test" value="1">Test Connection</button>
                <?php wp_nonce_field('sfx_estimator_test', 'sfx_estimator_test_nonce'); ?>
                &nbsp;
                <button type="submit" class="button" name="sfx_estimator_sync" value="1">Sync Now (Replace Matrix)</button>
                <?php wp_nonce_field('sfx_estimator_sync', 'sfx_estimator_sync_nonce'); ?>
            </p>

            <h2 class="title">Local Price Matrix (JSON)</h2>
            <p>Supports two shapes:
            <br>• <code>{"DeviceType":{"Make":{"Model":{"Issue":price}}}}</code>
            <br>• <code>{"DeviceType":{"Make":{"Series":{"Model":{"Issue":price}}}}}</code> (explicit Series)</p>
            <textarea name="price_matrix" rows="18" class="large-text code"><?php echo esc_textarea($json); ?></textarea>

            <p><button type="submit" class="button button-primary" name="sfx_estimator_save" value="1">Save Settings</button></p>
        </form>

        <hr/>
        <h2>Import / Export</h2>
        <form method="post">
            <?php wp_nonce_field('sfx_estimator_load_extended'); ?>
            <button type="submit" class="button">Load Extended Catalog (built-in)</button>
            <input type="hidden" name="sfx_estimator_load_extended" value="1">
        </form>
        <form method="get" style="margin-top:10px;">
            <input type="hidden" name="page" value="sfx-estimator-admin">
            <?php wp_nonce_field('sfx_estimator_export'); ?>
            <button type="submit" class="button">Export Current JSON</button>
            <input type="hidden" name="sfx_estimator_export" value="1">
        </form>
        <p>Quick check: <a href="<?php echo esc_url( rest_url('sfx/v1/catalog') ); ?>" target="_blank">View catalog JSON</a>.</p>
    </div>
    <?php
}

add_shortcode('sfx_estimator', 'sfx_estimator_shortcode');
function sfx_estimator_shortcode($atts) {
    $opt = get_option('sfx_estimator_options');
    if (!is_array($opt)) $opt = array();
    $opt = wp_parse_args($opt, sfx_estimator_default_options());
    sfx_estimator_maybe_upgrade_email_template($opt, false);
    if (!isset($opt['make_images']) || !is_array($opt['make_images'])) $opt['make_images'] = array();

    wp_enqueue_style('sfx-estimator-css', SFX_ESTIMATOR_URL . 'assets/css/sfx-estimator.css', array(), SFX_ESTIMATOR_VERSION);
    wp_enqueue_script('sfx-estimator-js', SFX_ESTIMATOR_URL . 'assets/js/sfx-estimator-app.js', array('jquery'), SFX_ESTIMATOR_VERSION, true);

    $tile_urls = array();
    if (!empty($opt['tile_images'])) {
        foreach ($opt['tile_images'] as $k => $id) {
            $url = wp_get_attachment_image_url(intval($id), 'medium');
            if ($url) $tile_urls[$k] = $url;
        }
    }
    $make_urls = array();
    if (!empty($opt['make_images']) && is_array($opt['make_images'])) {
        foreach ($opt['make_images'] as $type => $makes) {
            if (!is_array($makes)) continue;
            foreach ($makes as $label => $id) {
                $url = wp_get_attachment_image_url(intval($id), 'medium');
                if ($url) {
                    if (!isset($make_urls[$type])) $make_urls[$type] = array();
                    $make_urls[$type][$label] = $url;
                }
            }
        }
    }
    $series_urls = array();
    if (!empty($opt['series_images'])) {
        foreach ($opt['series_images'] as $label => $id) {
            $url = wp_get_attachment_image_url(intval($id), 'medium');
            if ($url) $series_urls[$label] = $url;
        }
    }
    $model_urls = array();
    if (!empty($opt['model_images'])) {
        foreach ($opt['model_images'] as $label => $id) {
            $url = wp_get_attachment_image_url(intval($id), 'medium');
            if ($url) $model_urls[$label] = $url;
        }
    }

    $bootstrap = sfx_estimator_prepare_catalog($opt['price_matrix'] ?? array());

    wp_localize_script('sfx-estimator-js', 'SFXEstimator', array(
        'rest' => array(
            'root' => esc_url_raw(rest_url('sfx/v1/')),
            'nonce'=> is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
        ),
        'ticket' => sfx_estimator_get_ticket_meta(),
        'brand' => array(
            'business_name' => $opt['business_name'],
            'store_phone'   => $opt['store_phone'],
            'store_address' => $opt['store_address'],
        ),
        'tiles' => array('images' => $tile_urls),
        'makeImages'  => $make_urls,
        'seriesImages' => $series_urls,
        'modelImages'  => $model_urls,
        'ui' => array('showBreadcrumbs' => (bool)$opt['show_breadcrumbs']),
        'version' => SFX_ESTIMATOR_VERSION,
        'bootstrap' => $bootstrap,
    ));
    ob_start(); ?>
    <div id="sfx-estimator" class="sfx-wrap" aria-live="polite">
        <div class="sfx-fallback">
            <?php echo esc_html__('Loading Instant Estimate Wizard…', 'sfx-estimator'); ?>
        </div>
        <noscript><?php echo esc_html__('Please enable JavaScript to use the Instant Estimate Wizard.', 'sfx-estimator'); ?></noscript>
    </div>
    <?php return ob_get_clean();
}

add_action('init', 'sfx_estimator_register_shortlink_rewrite');
function sfx_estimator_register_shortlink_rewrite() {
    add_rewrite_rule('^d/([^/]+)/?$', 'index.php?sfx_estimator_short=$matches[1]', 'top');
}

add_filter('query_vars', function ($vars) {
    $vars[] = 'sfx_estimator_short';
    return $vars;
});

add_action('template_redirect', 'sfx_estimator_handle_shortlink_redirect');
function sfx_estimator_handle_shortlink_redirect() {
    $code = get_query_var('sfx_estimator_short');
    if (!$code) {
        return;
    }
    $link = sfx_estimator_find_short_link($code);
    if (!$link) {
        status_header(404);
        nocache_headers();
        echo esc_html__('Link not found.', 'sfx-estimator');
        exit;
    }
    $channel = isset($_GET['via']) ? preg_replace('/[^a-z0-9]/', '', strtolower(wp_unslash($_GET['via']))) : $link['channel'];
    if ($channel === '') {
        $channel = $link['channel'];
    }
    $target = $link['target_url'];
    if ($channel && $channel !== $link['channel']) {
        $target = add_query_arg(
            'utm_medium',
            $channel,
            remove_query_arg('utm_medium', $target)
        );
    }
    sfx_estimator_record_link_click($link, $channel);
    wp_safe_redirect($target, 302);
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('sfx/v1', '/catalog', array(
        'methods' => 'GET',
        'callback'=> 'sfx_estimator_get_catalog',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('sfx/v1', '/quote', array(
        'methods' => 'POST',
        'callback'=> 'sfx_estimator_post_quote',
        'permission_callback' => '__return_true',
    ));
});

function sfx_estimator_default_other_phone_issues() {
    return array(
        'Screen Replacement' => 199,
        'Battery Replacement' => 79,
        'Charging Port Replacement' => 99,
        'Rear Camera Replacement' => 119,
        'Front Camera Replacement' => 99,
        'Speaker / Microphone Repair' => 79,
        'Water / Liquid Damage (Diagnostic)' => 59,
        'Data Recovery' => 119,
        'Motherboard Repair' => 199,
        'Power Button Repair' => 79,
        'Volume Button Repair' => 79,
        'No Power (Diagnostic)' => 59
    );
}

function sfx_estimator_default_other_device_issues() {
    return array(
        'Battery Replacement' => 69,
        'Screen Replacement' => 99,
        'Charging Port Replacement' => 79,
        'Data Recovery' => 99
    );
}

function sfx_estimator_reorder_assoc(array $input, array $order) {
    $ordered = array();
    foreach ($order as $key) {
        if (array_key_exists($key, $input)) {
            $ordered[$key] = $input[$key];
            unset($input[$key]);
        }
    }
    foreach ($input as $key => $value) {
        $ordered[$key] = $value;
    }
    return $ordered;
}

function sfx_estimator_upgrade_price_matrix($pm, &$changed = false) {
    if (!is_array($pm)) $pm = array();
    if (!isset($pm['Cell Phone']) || !is_array($pm['Cell Phone'])) {
        $pm['Cell Phone'] = array();
    }
    $cell =& $pm['Cell Phone'];

    if (isset($cell['Others']) && !isset($cell['Other Cell Phones'])) {
        $cell['Other Cell Phones'] = $cell['Others'];
        unset($cell['Others']);
        $changed = true;
    }

    if (!isset($cell['Other Cell Phones']) || !is_array($cell['Other Cell Phones'])) {
        $template = null;
        foreach (array('Samsung','iPhone','Motorola','Google Pixel','LG') as $candidate) {
            if (isset($cell[$candidate]['Any Model']) && is_array($cell[$candidate]['Any Model'])) {
                $template = $cell[$candidate]['Any Model'];
                break;
            }
        }
        if (!$template) $template = sfx_estimator_default_other_phone_issues();
        $cell['Other Cell Phones'] = array('Any Model' => $template);
        $changed = true;
    } else {
        if (!isset($cell['Other Cell Phones']['Any Model']) || !is_array($cell['Other Cell Phones']['Any Model'])) {
            $cell['Other Cell Phones']['Any Model'] = sfx_estimator_default_other_phone_issues();
            $changed = true;
        }
    }

    $cell = sfx_estimator_reorder_assoc($cell, array('iPhone','Samsung','Motorola','Google Pixel','LG','Other Cell Phones'));

    if (!isset($pm['Others']) || !is_array($pm['Others'])) {
        $pm['Others'] = array();
    }
    $others =& $pm['Others'];

    if (isset($others['Others']) && !isset($others['Other Devices'])) {
        $others['Other Devices'] = $others['Others'];
        unset($others['Others']);
        $changed = true;
    }

    if (isset($others['Other Devices']) && isset($others['Other Devices']['Any Model']) && is_array($others['Other Devices']['Any Model'])) {
        // already good
    } else {
        $template = null;
        if (isset($others['iPod']['Any Model']) && is_array($others['iPod']['Any Model'])) {
            $template = $others['iPod']['Any Model'];
        }
        if (!$template && isset($others['Apple Watch']['Any Model']) && is_array($others['Apple Watch']['Any Model'])) {
            $template = $others['Apple Watch']['Any Model'];
        }
        if (!$template) $template = sfx_estimator_default_other_device_issues();
        $others['Other Devices'] = array('Any Model' => $template);
        $changed = true;
    }

    if (isset($others['iPod']) && is_array($others['iPod'])) {
        if (!isset($others['iPod']['Others']) || !is_array($others['iPod']['Others'])) {
            $template = isset($others['iPod']['Any Model']) && is_array($others['iPod']['Any Model']) ? $others['iPod']['Any Model'] : sfx_estimator_default_other_device_issues();
            $others['iPod']['Others'] = array('Any Model' => $template);
            $changed = true;
        } elseif (!isset($others['iPod']['Others']['Any Model']) || !is_array($others['iPod']['Others']['Any Model'])) {
            $others['iPod']['Others']['Any Model'] = isset($others['iPod']['Any Model']) && is_array($others['iPod']['Any Model']) ? $others['iPod']['Any Model'] : sfx_estimator_default_other_device_issues();
            $changed = true;
        }
    }

    $others = sfx_estimator_reorder_assoc($others, array('Apple Watch','iPod','Other Devices'));

    return $pm;
}

function sfx_estimator_prepare_catalog($pm) {
    $catalog = array();
    $series_map = array();
    $issues_all = array();
    $issues_by_type = array();
    $issues_by_make = array();

    foreach ($pm as $type => $makes) {
        $catalog[$type] = $catalog[$type] ?? array();
        foreach ($makes as $make => $level3) {
            $catalog[$type][$make] = array();
            $series_map[$type][$make] = array();

            foreach ($level3 as $k => $v) {
                if (!is_array($v)) continue;
                $first = reset($v);
                $series_name = trim((string) $k);
                $series_key = strtolower($series_name);
                $first_is_array = is_array($first);
                $is_general_series = $first_is_array && ($series_key === 'general');

                // Flatten generic buckets like "General" so the UI skips an unnecessary series step.
                if ($is_general_series) {
                    foreach ($v as $model => $iss_arr) {
                        if ($model !== 'Any Model' && !in_array($model, $catalog[$type][$make], true)) {
                            $catalog[$type][$make][] = $model;
                        }
                        if (!is_array($iss_arr)) continue;
                        foreach ($iss_arr as $issue => $price) {
                            $issues_all[$issue] = true;
                            $issues_by_type[$type][$issue] = true;
                            $issues_by_make[$type][$make][$issue] = true;
                        }
                    }
                    continue;
                }

                if ($first_is_array) {
                    // Treat as SERIES even if it contains "Any Model"
                    $series = $series_name;
                    if (!in_array($series, $catalog[$type][$make], true)) {
                        $catalog[$type][$make][] = $series;
                    }
                    if (!isset($series_map[$type][$make][$series])) {
                        $series_map[$type][$make][$series] = array();
                    }
                    foreach ($v as $model => $iss_arr) {
                        if (!is_array($iss_arr)) continue;
                        if ($model !== 'Any Model') {
                            $series_map[$type][$make][$series][] = $model;
                        }
                        foreach ($iss_arr as $issue => $price) {
                            $issues_all[$issue] = true;
                            $issues_by_type[$type][$issue] = true;
                            $issues_by_make[$type][$make][$issue] = true;
                        }
                    }
                } else {
                    // MODEL or Any Model directly under make
                    if ($k !== 'Any Model' && !in_array($k, $catalog[$type][$make], true)) {
                        $catalog[$type][$make][] = $k;
                    }
                    foreach ($v as $issue => $price) {
                        $issues_all[$issue] = true;
                        $issues_by_type[$type][$issue] = true;
                        $issues_by_make[$type][$make][$issue] = true;
                    }
                }
            }
        }
    }
    foreach ($issues_by_type as $t => $set) {
        $issues_by_type[$t] = array_keys($set);
    }
    foreach ($issues_by_make as $t => $makes) {
        foreach ($makes as $m => $set) {
            $issues_by_make[$t][$m] = array_keys($set);
        }
    }

    return array(
        'catalog' => $catalog,
        'series_map' => $series_map,
        'issues'  => array_keys($issues_all),
        'issues_by_type' => $issues_by_type,
        'issues_by_make' => $issues_by_make,
    );
}

function sfx_estimator_get_catalog($request) {
    $opt = get_option('sfx_estimator_options');
    if (!is_array($opt)) $opt = array();
    $opt = wp_parse_args($opt, sfx_estimator_default_options());
    sfx_estimator_maybe_upgrade_email_template($opt);
    $pm = $opt['price_matrix'] ?? array();
    $original_pm = $pm;
    $matrix_changed = false;
    $pm = sfx_estimator_upgrade_price_matrix($pm, $matrix_changed);
    if (!isset($opt['price_matrix']) || $matrix_changed || $pm !== $original_pm) {
        $opt['price_matrix'] = $pm;
        update_option('sfx_estimator_options', $opt);
    }
    $data = sfx_estimator_prepare_catalog($pm);
    $data['ok'] = true;
    return rest_ensure_response($data);
}

function sfx_estimator_price_lookup($pm, $type, $make, $series, $model, $issues) {
    $total = 0.0;
    foreach ($issues as $issue) {
        $price = null;
        if ($series && isset($pm[$type][$make][$series][$model][$issue])) {
            $price = floatval($pm[$type][$make][$series][$model][$issue]);
        } elseif (isset($pm[$type][$make][$model][$issue])) {
            $price = floatval($pm[$type][$make][$model][$issue]);
        } elseif ($series && isset($pm[$type][$make][$series]['Any Model'][$issue])) {
            $price = floatval($pm[$type][$make][$series]['Any Model'][$issue]);
        } elseif (isset($pm[$type][$make]['Any Model'][$issue])) {
            $price = floatval($pm[$type][$make]['Any Model'][$issue]);
        }
        if (!is_null($price) && $price > 0) $total += $price;
    }
    return $total;
}

function sfx_estimator_prepare_estimate_context($quote_id, array $input, array $opt) {
    $created_ts_gmt = current_time('timestamp', true);
    $timezone = $opt['store_timezone'] ?? 'UTC';
    $valid_hours = max(1, (int) ($opt['estimate_valid_hours'] ?? 72));
    $expires_ts_gmt = $created_ts_gmt + ($valid_hours * HOUR_IN_SECONDS);

    $issues = array();
    if (!empty($input['issues']) && is_array($input['issues'])) {
        foreach ($input['issues'] as $issue) {
            $issue = trim((string) $issue);
            if ($issue !== '') {
                $issues[] = $issue;
            }
        }
    }
    $issue_summary = implode(', ', $issues);
    $device_heading = sfx_estimator_format_device_heading($input['make'] ?? '', $input['model'] ?? '');

    $issues_lower = array_map('strtolower', $issues);
    $contains_liquid = false;
    $contains_board = false;
    foreach ($issues_lower as $issue_word) {
        if (strpos($issue_word, 'liquid') !== false || strpos($issue_word, 'water') !== false) {
            $contains_liquid = true;
        }
        if (strpos($issue_word, 'board') !== false || strpos($issue_word, 'no power') !== false) {
            $contains_board = true;
        }
    }
    $issue_disclaimers = array();
    if ($contains_liquid) {
        $issue_disclaimers[] = 'Liquid damage can require deeper diagnostics; final price confirmed on intake.';
    }
    if ($contains_board) {
        $issue_disclaimers[] = 'Board-level repairs may extend turnaround; we will confirm after diagnosis.';
    }
    $issue_disclaimers_sms = array();
    if ($contains_liquid) {
        $issue_disclaimers_sms[] = 'Liquid damage may need extra diagnostics.';
    }
    if ($contains_board) {
        $issue_disclaimers_sms[] = 'Board repairs can take longer.';
    }

    $family_lower = strtolower($input['device_type']);
    $make_lower = strtolower($input['make']);
    $series_lower = strtolower($input['series']);
    $console_brands = array('playstation', 'xbox', 'nintendo', 'switch');
    $is_console = in_array($family_lower, array('gaming console', 'game console', 'console'), true)
        || in_array($make_lower, $console_brands, true)
        || in_array($series_lower, $console_brands, true);

    $issue_disclaimer_html = '';
    if (!empty($issue_disclaimers)) {
        $paragraphs = array();
        foreach ($issue_disclaimers as $line) {
        $paragraphs[] = '<p style="margin:12px 0 0 0;">' . $line . '</p>';
        }
        $issue_disclaimer_html = implode('', $paragraphs);
    }
    $issue_disclaimer_text = implode(' ', $issue_disclaimers);
    $issue_disclaimer_sms_text = implode(' ', $issue_disclaimers_sms);

    $turnaround_estimate_value = $opt['turnaround_default'];
    $turnaround_sentence_html = 'Most repairs are completed <strong>today</strong> (often <strong>' . $turnaround_estimate_value . '</strong>).';
    $turnaround_sentence_plain = 'Most repairs are completed today (often ' . $turnaround_estimate_value . ').';
    $turnaround_sms_full = sprintf('Most fixes done today (often %s) with %s.', $turnaround_estimate_value, $opt['warranty_text']);
    $turnaround_sms_short = sprintf('Fast repairs today with %s.', $opt['warranty_text']);

    if ($is_console) {
        $turnaround_estimate_value = 'same-day diagnostics; repairs 1-2 days depending on parts';
        $turnaround_sentence_html = 'Most console diagnostics are same day; repairs typically 1-2 days depending on parts.';
        $turnaround_sentence_plain = 'Most console diagnostics are same day; repairs typically 1-2 days depending on parts.';
        $turnaround_sms_full = 'Console diagnostics are same day; repairs typically 1-2 days depending on parts.';
        $turnaround_sms_short = 'Console repairs may take 1-2 days.';
    }

    $estimate_ref = sfx_estimator_display_quote_id($quote_id);
    $estimate_id = sfx_estimator_generate_estimate_id($quote_id);

    $subtotal = round(floatval($input['subtotal'] ?? 0), 2);
    $tax_rate_input = isset($opt['sales_tax_rate']) ? floatval($opt['sales_tax_rate']) : 0.0;
    $tax_multiplier = $tax_rate_input > 1 ? $tax_rate_input / 100 : $tax_rate_input;
    $tax = round($subtotal * $tax_multiplier, 2);
    $total = round($subtotal + $tax, 2);
    $currency = apply_filters('sfx_estimator_currency', 'USD', $quote_id);

    $hours_context = array();
    $hours_summary = sfx_estimator_compute_hours_summary($opt, $created_ts_gmt, $hours_context);
    $maps_base = sfx_estimator_maps_base_url($opt);

    try {
        $tz_object = new DateTimeZone(sfx_estimator_validate_timezone($timezone));
    } catch (Exception $e) {
        $tz_object = new DateTimeZone('UTC');
    }
    $created_local_display = '';
    $expires_local_display = '';
    if ($created_ts_gmt) {
        $created_dt = new DateTime('@' . $created_ts_gmt);
        $created_dt->setTimezone($tz_object);
        $created_local_display = $created_dt->format('F j, Y g:i A');
    }
    if ($expires_ts_gmt) {
        $expires_dt = new DateTime('@' . $expires_ts_gmt);
        $expires_dt->setTimezone($tz_object);
        $expires_local_display = $expires_dt->format('F j, Y g:i A');
    }

    $utm_source = $opt['utm_source'] ?? 'estimator';
    $utm_campaign = $opt['utm_campaign'] ?? 'instant_estimate';
    $device_family = $input['device_type'] ?? '';
    $utm_content = $device_family ? sanitize_title($device_family) : '';

    $short_links = array();
    if ($maps_base) {
        foreach (array('sms', 'email') as $channel) {
            $target = add_query_arg(array(
                'utm_source' => $utm_source,
                'utm_medium' => $channel,
                'utm_campaign' => $utm_campaign,
                'utm_content' => $utm_content,
            ), $maps_base);
            $short_links[$channel] = sfx_estimator_store_short_link($estimate_id, $channel, $target);
        }
    }

    $store = array(
        'name' => $opt['business_name'],
        'phone_display' => $opt['store_phone'],
        'phone_e164' => sfx_estimator_sanitize_e164($opt['store_phone_e164'] ?? $opt['store_phone']),
        'address_1' => $opt['store_address_line1'] ?? $opt['store_address'],
        'city' => $opt['store_city'] ?? '',
        'region' => $opt['store_region'] ?? '',
        'postal' => $opt['store_postal'] ?? '',
        'hours_summary' => $hours_summary,
        'hours_context' => $hours_context,
        'maps_link' => $maps_base,
        'maps_short_links' => array(
            'sms' => $short_links['sms']['short_url'] ?? $maps_base,
            'email' => $short_links['email']['short_url'] ?? $maps_base,
        ),
        'website' => $opt['store_website'],
        'is_open' => $hours_context['is_open'],
    );

    $customer_phone_e164 = sfx_estimator_sanitize_e164($input['phone']);

    $payload = array(
        'estimate_id' => $estimate_id,
        'estimate_ref' => $estimate_ref,
        'created_at_iso' => sfx_estimator_format_iso8601($created_ts_gmt, $timezone),
        'expires_at_iso' => sfx_estimator_format_iso8601($expires_ts_gmt, $timezone),
        'customer' => array(
            'first_name' => $input['first_name'],
            'phone_e164' => $customer_phone_e164,
            'email' => $input['email'],
        ),
        'device' => array(
            'brand' => $input['make'],
            'family' => $input['device_type'],
            'model' => $input['model'],
            'variant' => $input['series'],
            'is_console' => $is_console,
        ),
        'issue' => $issue_summary,
        'issues' => $issues,
        'price' => array(
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency,
            'tax_rate' => $tax_rate_input,
        ),
        'turnaround_estimate' => $turnaround_estimate_value,
        'turnaround_sentence_html' => $turnaround_sentence_html,
        'turnaround_sentence_plain' => $turnaround_sentence_plain,
        'turnaround_sms_full' => $turnaround_sms_full,
        'turnaround_sms_short' => $turnaround_sms_short,
        'warranty' => $opt['warranty_text'],
        'store' => $store,
        'utm' => array(
            'source' => $utm_source,
            'medium' => 'email_or_sms',
            'campaign' => $utm_campaign,
            'content' => $utm_content,
        ),
        'links' => array(
            'maps' => $short_links,
        ),
        'disclaimers' => array(
            'issues' => $issue_disclaimers,
            'issues_sms' => $issue_disclaimers_sms,
        ),
    );

    $tokens = array(
        'first_name' => $input['first_name'],
        'last_name' => $input['last_name'],
        'phone' => $input['phone'],
        'phone_e164' => $customer_phone_e164,
        'email' => $input['email'],
        'customer_first_name' => $input['first_name'],
        'customer_phone' => $input['phone'],
        'customer_email' => $input['email'],
        'device_type' => $input['device_type'],
        'make' => $input['make'],
        'series' => $input['series'],
        'model' => $input['model'],
        'device_brand' => $input['make'],
        'device_family' => $input['device_type'],
        'device_model' => $input['model'],
        'device_variant' => $input['series'],
        'issues' => $issue_summary,
        'issues_list' => $issues,
        'issue_disclaimers' => $issue_disclaimers,
        'issue_disclaimer_text' => $issue_disclaimer_text,
        'issue_disclaimer_html' => $issue_disclaimer_html,
        'issue_disclaimers_sms' => $issue_disclaimers_sms,
        'issue_disclaimer_sms_text' => $issue_disclaimer_sms_text,
        'total' => number_format($total, 2),
        'subtotal' => number_format($subtotal, 2),
        'tax' => number_format($tax, 2),
        'tax_rate' => $tax_rate_input,
        'total_numeric' => $total,
        'subtotal_numeric' => $subtotal,
        'tax_numeric' => $tax,
        'price_total_formatted' => '$' . number_format($total, 2),
        'price_subtotal_formatted' => '$' . number_format($subtotal, 2),
        'price_tax_formatted' => '$' . number_format($tax, 2),
        'price_currency' => $currency,
        'price_tax_rate' => $tax_rate_input,
        'business_name' => $opt['business_name'],
        'store_name' => $opt['business_name'],
        'store_phone' => $opt['store_phone'],
        'store_phone_e164' => $store['phone_e164'],
        'store_address' => $opt['store_address'],
        'store_address_line1' => $store['address_1'],
        'store_city' => $store['city'],
        'store_region' => $store['region'],
        'store_postal' => $store['postal'],
        'store_hours' => $opt['store_hours'],
        'store_hours_summary' => $hours_summary,
        'store_hours_detail' => $hours_summary,
        'store_is_open' => $hours_context['is_open'],
        'store_closes_at' => $hours_context['closes_at'],
        'store_closes_label' => $hours_context['closes_label'],
        'store_opens_at' => $hours_context['opens_at'],
        'store_opens_label' => $hours_context['opens_label'],
        'store_website' => $opt['store_website'],
        'warranty' => $opt['warranty_text'],
        'quote_id' => $quote_id,
        'estimate_ref' => $estimate_ref,
        'estimate_id' => $estimate_id,
        'device_heading' => $device_heading,
        'device_is_console' => $is_console,
        'turnaround' => $turnaround_estimate_value,
        'turnaround_estimate' => $turnaround_estimate_value,
        'turnaround_sentence' => $turnaround_sentence_plain,
        'turnaround_sentence_html' => $turnaround_sentence_html,
        'turnaround_sms_full' => $turnaround_sms_full,
        'turnaround_sms_short' => $turnaround_sms_short,
        'maps_link' => $maps_base,
        'maps_short_sms' => $short_links['sms']['short_url'] ?? $maps_base,
        'maps_short_email' => $short_links['email']['short_url'] ?? $maps_base,
        'maps_short' => $short_links['sms']['short_url'] ?? ($short_links['email']['short_url'] ?? $maps_base),
        'estimate_expires_iso' => sfx_estimator_format_iso8601($expires_ts_gmt, $timezone),
        'estimate_expires_local' => $expires_local_display,
        'expires_at_iso' => sfx_estimator_format_iso8601($expires_ts_gmt, $timezone),
        'created_at_iso' => sfx_estimator_format_iso8601($created_ts_gmt, $timezone),
        'created_at_local' => $created_local_display,
        'estimate_created_local' => $created_local_display,
        'utm_source' => $utm_source,
        'utm_campaign' => $utm_campaign,
        'utm_content' => $utm_content,
    );

    return array(
        'payload' => $payload,
        'tokens' => $tokens,
        'short_links' => $short_links,
        'meta' => array(
            'created_ts_gmt' => $created_ts_gmt,
            'expires_ts_gmt' => $expires_ts_gmt,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'tax_rate' => $tax_rate_input,
            'hours_summary' => $hours_summary,
        ),
    );
}

function sfx_estimator_utf8_chars($text) {
    if ($text === '') {
        return array();
    }
    if (function_exists('mb_strlen')) {
        $len = mb_strlen($text, 'UTF-8');
        $chars = array();
        for ($i = 0; $i < $len; $i++) {
            $chars[] = mb_substr($text, $i, 1, 'UTF-8');
        }
        return $chars;
    }
    return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
}

function sfx_estimator_normalize_sms_text($text) {
    $map = array(
        '’' => "'",
        '‘' => "'",
        '“' => '"',
        '”' => '"',
        '–' => '-',
        '—' => '-',
        '…' => '...',
        '•' => '-',
        '·' => '-',
        '™' => 'TM',
        '®' => '(R)',
        '©' => '(C)',
        '°' => ' deg ',
    );
    $text = strtr($text, $map);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function sfx_estimator_get_gsm_charsets() {
    static $charsets = null;
    if ($charsets === null) {
        $charsets = array(
            'basic' => "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ !\"#¤%&'()*+,-./0123456789:;<>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà",
            'extended' => "^{}\\[~]|€",
        );
    }
    return $charsets;
}

function sfx_estimator_detect_sms_encoding($text) {
    $charsets = sfx_estimator_get_gsm_charsets();
    $gsm_basic = $charsets['basic'];
    $gsm_extended = $charsets['extended'];
    foreach (sfx_estimator_utf8_chars($text) as $ch) {
        if (strpos($gsm_basic, $ch) !== false) {
            continue;
        }
        if (strpos($gsm_extended, $ch) !== false) {
            continue;
        }
        return 'UCS-2';
    }
    return 'GSM-7';
}

function sfx_estimator_sms_gsm_length($text) {
    $gsm_extended = sfx_estimator_get_gsm_charsets()['extended'];
    $length = 0;
    foreach (sfx_estimator_utf8_chars($text) as $ch) {
        if (strpos($gsm_extended, $ch) !== false) {
            $length += 2;
        } else {
            $length += 1;
        }
    }
    return $length;
}

function sfx_estimator_count_sms_segments($text, $encoding = null) {
    if ($encoding === null) {
        $encoding = sfx_estimator_detect_sms_encoding($text);
    }
    if ($encoding === 'GSM-7') {
        $length = sfx_estimator_sms_gsm_length($text);
        if ($length <= 160) {
            return 1;
        }
        return (int) ceil($length / 153);
    }
    if (function_exists('mb_strlen')) {
        $length = mb_strlen($text, 'UTF-16BE');
    } else {
        $length = strlen($text);
    }
    if ($length <= 70) {
        return 1;
    }
    return (int) ceil($length / 67);
}

function sfx_estimator_shorten_warranty($warranty) {
    if (!$warranty) {
        return '';
    }
    $warranty = sfx_estimator_normalize_sms_text($warranty);
    $warranty = str_replace(array('parts and labor', 'parts & labor'), 'parts & labor', $warranty);
    $warranty = str_replace('months', 'mo', $warranty);
    $warranty = str_replace('month', 'mo', $warranty);
    return $warranty;
}

function sfx_estimator_build_sms_message($payload, $variant = 'primary') {
    $variant = in_array($variant, array('primary','nudge','final'), true) ? $variant : 'primary';
    $store = $payload['store'] ?? array();
    $customer = $payload['customer'] ?? array();
    $device = $payload['device'] ?? array();
    $price = $payload['price'] ?? array();
    $links = $payload['links']['maps'] ?? array();

    $name = trim((string) ($customer['first_name'] ?? ''));
    if ($name === '') {
        $name = 'there';
    }

    $issues = $payload['issues'] ?? array();
    $primary_issue = '';
    if (!empty($issues)) {
        $primary_issue = trim((string) $issues[0]);
    }
    if ($primary_issue === '' && !empty($payload['issue'])) {
        $primary_issue = trim((string) $payload['issue']);
    }
    $issue_phrase = $primary_issue !== '' ? $primary_issue : 'repair';

    $device_label = trim((string) ($device['model'] ?? ''));
    if ($device_label === '') {
        $device_label = trim(($device['brand'] ?? '') . ' ' . ($device['family'] ?? 'device'));
    }

    $turnaround = trim((string) ($payload['turnaround_estimate'] ?? 'today'));
    $warranty = sfx_estimator_shorten_warranty($payload['warranty'] ?? '3-month parts & labor warranty');
    $is_console_device = !empty($payload['device']['is_console']);
    $turnaround_sms_full = $payload['turnaround_sms_full'] ?? '';
    $turnaround_sms_short = $payload['turnaround_sms_short'] ?? '';
    if ($turnaround_sms_full === '') {
        $turnaround_sms_full = sprintf('Most fixes done today (often %s) with %s.', $turnaround, $warranty);
    }
    if ($turnaround_sms_short === '') {
        $turnaround_sms_short = sprintf('Fast repairs today with %s.', $warranty);
    }

    $address_line = $store['address_1'] ?? '';
    $address_line = str_replace('Suite', 'Ste', $address_line);
    $address_line = str_replace('Street', 'St', $address_line);
    $address_city = $store['city'] ?? '';
    $address_region = $store['region'] ?? '';
    $address_components = array_filter(array(
        trim($address_line),
        trim($address_city),
        trim($address_region),
    ));
    $address_display = implode(', ', $address_components);
    if ($address_display === '') {
        $address_display = $store['name'] ?? 'Fast Repair';
    }

    $short_link = '';
    if (!empty($links['sms']['short_url'])) {
        $short_link = $links['sms']['short_url'];
    } elseif (!empty($store['maps_short_links']['sms'])) {
        $short_link = $store['maps_short_links']['sms'];
    } elseif (!empty($store['maps_link'])) {
        $short_link = $store['maps_link'];
    } elseif (!empty($store['website'])) {
        $short_link = $store['website'];
    }

    $price_total = isset($price['total']) ? number_format((float) $price['total'], 2) : '0.00';
    $phone_display = $store['phone_display'] ?? '(916) 477-5995';
    $stop_clause = 'Reply STOP to opt out, HELP for help.';
    $hours_ctx = $store['hours_context'] ?? array();
    $is_open_now = isset($hours_ctx['is_open']) ? (bool) $hours_ctx['is_open'] : null;
    $opens_label = $hours_ctx['opens_label'] ?? '';
    $issue_notes_sms = $payload['disclaimers']['issues_sms'] ?? array();
    $issue_note_text = implode(' ', $issue_notes_sms);

    $pieces = array();

    if ($variant === 'primary') {
        $line1 = sprintf('Fast Repair: %s, your %s %s estimate is $%s.', $name, $device_label, $issue_phrase, $price_total);
        $line2 = $turnaround_sms_full;
        $line2_short = $turnaround_sms_short ?: 'Fast repairs today with same-day turnarounds.';
        if ($is_open_now === false && $opens_label) {
            if ($short_link) {
                $line3 = sprintf('We open %s - directions: %s', $opens_label, $short_link);
            } else {
                $line3 = sprintf('We open %s - call %s for directions.', $opens_label, $phone_display);
            }
        } else {
            if ($short_link) {
                $line3 = sprintf('Walk in: %s. Directions: %s', $address_display, $short_link);
            } else {
                $line3 = sprintf('Walk in: %s. Call %s for directions.', $address_display, $phone_display);
            }
        }
        $line4 = sprintf('Questions? Reply here or call %s.', $phone_display);
        $pieces = array(
            array('text' => $line1, 'optional' => false),
            array('text' => $line2, 'optional' => true, 'replacement' => $line2_short, 'priority' => 2),
            array('text' => $line3, 'optional' => false),
            array('text' => $line4, 'optional' => true, 'priority' => 1),
            array('text' => $stop_clause, 'optional' => false),
        );
    } elseif ($variant === 'nudge') {
        $line1 = sprintf('Fast Repair: Still need your %s fixed? Your estimate ($%s) is ready.', $device_label, $price_total);
        if ($is_open_now === false && $opens_label) {
            if ($short_link) {
                $line2 = sprintf('We open %s - directions: %s', $opens_label, $short_link);
                $nudge_replacement = $line2;
            } else {
                $line2 = sprintf('We open %s - call %s for directions.', $opens_label, $phone_display);
                $nudge_replacement = $line2;
            }
        } else {
            if ($is_console_device) {
                if ($short_link) {
                    $line2 = sprintf('Console diagnostics are same day; repairs 1-2 days depending on parts. Directions: %s', $short_link);
                } else {
                    $line2 = 'Console diagnostics are same day; repairs 1-2 days depending on parts.';
                }
                $nudge_replacement = $short_link ? sprintf('Console repairs ready when you are. Directions: %s', $short_link) : 'Console repairs ready when you are.';
            } else {
                if ($short_link) {
                    $line2 = sprintf('We are open for walk-ins - many repairs in %s. Directions: %s', $turnaround, $short_link);
                } else {
                    $line2 = sprintf('We are open for walk-ins - many repairs in %s.', $turnaround);
                }
                $nudge_replacement = $short_link ? sprintf('Walk-ins welcome. Directions: %s', $short_link) : 'Walk-ins welcome.';
            }
        }
        $line3 = 'Questions? Reply here.';
        $pieces = array(
            array('text' => $line1, 'optional' => false),
            array('text' => $line2, 'optional' => true, 'replacement' => $nudge_replacement, 'priority' => 2),
            array('text' => $line3, 'optional' => true, 'priority' => 1),
            array('text' => $stop_clause, 'optional' => false),
        );
    } else { // final
        $line1 = sprintf('Fast Repair: Last reminder for your %s repair estimate ($%s).', $device_label, $price_total);
        if ($is_open_now === false && $opens_label) {
            if ($short_link) {
                $line2 = sprintf('We open %s - directions: %s', $opens_label, $short_link);
            } else {
                $line2 = sprintf('We open %s - call %s for directions.', $opens_label, $phone_display);
            }
        } else {
            if ($short_link) {
                $line2 = sprintf('Walk in when convenient: %s. Directions: %s', $address_display, $short_link);
            } else {
                $line2 = sprintf('Walk in when convenient: %s.', $address_display);
            }
        }
        $line3 = 'We are here to help.';
        $pieces = array(
            array('text' => $line1, 'optional' => false),
            array('text' => $line2, 'optional' => false),
            array('text' => $line3, 'optional' => true, 'priority' => 1, 'replacement' => ''),
            array('text' => $stop_clause, 'optional' => false),
        );
    }

    if ($issue_note_text !== '') {
        $pieces[] = array('text' => $issue_note_text, 'optional' => true, 'priority' => 0);
    }

    $message = implode(' ', array_map('trim', array_filter(array_column($pieces, 'text'))));
    $message = sfx_estimator_normalize_sms_text($message);
    $encoding = sfx_estimator_detect_sms_encoding($message);
    $segments = sfx_estimator_count_sms_segments($message, $encoding);

    while ($segments > 2) {
        $index = null;
        $best_priority = PHP_INT_MAX;
        foreach ($pieces as $idx => $piece) {
            if (empty($piece['optional']) || !empty($piece['removed'])) {
                continue;
            }
            $priority = isset($piece['priority']) ? (int) $piece['priority'] : 5;
            if ($priority < $best_priority) {
                $best_priority = $priority;
                $index = $idx;
            }
        }
        if ($index === null) {
            break;
        }
        if (!empty($pieces[$index]['replacement']) && empty($pieces[$index]['replacement_used'])) {
            $pieces[$index]['text'] = $pieces[$index]['replacement'];
            $pieces[$index]['replacement_used'] = true;
            $pieces[$index]['optional'] = false;
        } else {
            $pieces[$index]['text'] = '';
            $pieces[$index]['removed'] = true;
            $pieces[$index]['optional'] = false;
        }
        $message = implode(' ', array_map('trim', array_filter(array_column($pieces, 'text'))));
        $message = sfx_estimator_normalize_sms_text($message);
        $encoding = sfx_estimator_detect_sms_encoding($message);
        $segments = sfx_estimator_count_sms_segments($message, $encoding);
    }

    return apply_filters('sfx_estimator_sms_message', $message, $payload, $variant, array(
        'encoding' => $encoding,
        'segments' => $segments,
    ));
}

function sfx_estimator_fill_tokens($tpl, $data) {
    $normalize = function ($value) {
        if (is_array($value)) {
            $filtered = array();
            foreach ($value as $entry) {
                if ($entry !== '' && $entry !== null) {
                    $filtered[] = $entry;
                }
            }
            $value = implode(', ', $filtered);
        }
        return (string) $value;
    };

    $tpl = preg_replace_callback('/\{([a-z0-9_]+)\|([^}]+)\}/i', function ($matches) use ($data, $normalize) {
        $key = strtolower($matches[1]);
        $fallback = $matches[2];
        if (array_key_exists($key, $data)) {
            $value = $normalize($data[$key]);
            if ($value !== '') {
                return $value;
            }
        }
        return $fallback;
    }, $tpl);

    $tpl = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function ($matches) use ($data, $normalize) {
        $key = strtolower($matches[1]);
        if (!array_key_exists($key, $data)) {
            return '';
        }
        return $normalize($data[$key]);
    }, $tpl);

    return $tpl;
}

function sfx_estimator_post_quote($request) {
    $params = $request->get_json_params();
    $opt = get_option('sfx_estimator_options');
    if (!is_array($opt)) $opt = array();
    $opt = wp_parse_args($opt, sfx_estimator_default_options());
    sfx_estimator_maybe_upgrade_email_template($opt);
    $pm  = $opt['price_matrix'] ?? array();

    $first = sanitize_text_field($params['first_name'] ?? '');
    $last  = sanitize_text_field($params['last_name'] ?? '');
    $phone = sanitize_text_field($params['phone'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $notify= 'both'; // Force email + SMS delivery
    $type  = sanitize_text_field($params['device_type'] ?? '');
    $make  = sanitize_text_field($params['make'] ?? '');
    $series= sanitize_text_field($params['series'] ?? '');
    $model = sanitize_text_field($params['model'] ?? '');
    $issues = (isset($params['issues']) && is_array($params['issues'])) ? array_map('sanitize_text_field', $params['issues']) : array();
    $notes  = sanitize_textarea_field($params['notes'] ?? '');
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '';

    // Soften validation: require first name, device type/make, at least one issue, and at least one contact (email or phone).
    // Default model/series when not provided to avoid empty-model errors with model-less flows.
    if ($series === null) $series = '';
    if ($model === '' || $model === null) $model = 'Any Model';

    $has_email = $email !== '';
    $has_phone = $phone !== '';
    if (!$first || !$type || !$make || empty($issues) || (!$has_email && !$has_phone)) {
        return new WP_REST_Response(array('ok' => false, 'message' => 'Missing required fields.'), 400);
    }

    $subtotal = sfx_estimator_price_lookup($pm, $type, $make, $series, $model, $issues);
    $tax_rate_input = isset($opt['sales_tax_rate']) ? floatval($opt['sales_tax_rate']) : 0.0;
    $tax_multiplier = $tax_rate_input > 1 ? $tax_rate_input / 100 : $tax_rate_input;
    $tax_amount = round($subtotal * $tax_multiplier, 2);
    $grand_total = round($subtotal + $tax_amount, 2);
    $created_ts_gmt = current_time('timestamp', true);
    $valid_hours = max(1, (int) ($opt['estimate_valid_hours'] ?? 72));
    $expires_ts_gmt = $created_ts_gmt + ($valid_hours * HOUR_IN_SECONDS);
    $expires_mysql = gmdate('Y-m-d H:i:s', $expires_ts_gmt);

    global $wpdb;
    $table = $wpdb->prefix . 'sfx_quotes';
    $wpdb->insert($table, array(
        'first_name' => $first,
        'last_name'  => $last,
        'phone'      => $phone,
        'email'      => $email,
        'notify'     => $notify,
        'device_type'=> $type,
        'make'       => $make,
        'series'     => $series,
        'model'      => $model,
        'issues'     => implode(', ', $issues),
        'notes'      => $notes,
        'estimate_total' => $subtotal,
        'estimate_tax' => $tax_amount,
        'estimate_grand_total' => $grand_total,
        'expires_at' => $expires_mysql,
        'source_ip'  => $ip,
    ), array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s'));
    $quote_id = $wpdb->insert_id;

    $context = sfx_estimator_prepare_estimate_context($quote_id, array(
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'email' => $email,
        'device_type' => $type,
        'make' => $make,
        'series' => $series,
        'model' => $model,
        'issues' => $issues,
        'subtotal' => $subtotal,
    ), $opt);

    $tokens = $context['tokens'];
    $payload = $context['payload'];
    $short_links = $context['short_links'];
    $meta = $context['meta'];

    $wpdb->update($table, array(
        'estimate_ref' => $payload['estimate_ref'],
        'estimate_id' => $payload['estimate_id'],
        'estimate_total' => $meta['subtotal'],
        'estimate_tax' => $meta['tax'],
        'estimate_grand_total' => $meta['total'],
        'expires_at' => gmdate('Y-m-d H:i:s', $meta['expires_ts_gmt']),
        'shortlink_sms' => $short_links['sms']['short_url'] ?? '',
        'shortlink_email' => $short_links['email']['short_url'] ?? '',
    ), array('id' => $quote_id), array('%s','%s','%f','%f','%f','%s','%s','%s'), array('%d'));

    if ($notify === 'email' || $notify === 'both') {
        $subject = sfx_estimator_fill_tokens($opt['email_subject'], $tokens);
        $email_body = sfx_estimator_fill_tokens($opt['email_template'], $tokens);
        add_filter('wp_mail_from', function() use ($opt){ return $opt['email_from']; });
        add_filter('wp_mail_from_name', function() use ($opt){ return $opt['business_name']; });
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if (!empty($opt['reply_to'])) $headers[] = 'Reply-To: ' . $opt['reply_to'];
        if ($email) wp_mail($email, $subject, $email_body, $headers);
        remove_all_filters('wp_mail_from');
        remove_all_filters('wp_mail_from_name');
    }

    if (!empty($opt['twilio_sid']) && !empty($opt['twilio_token']) && !empty($opt['twilio_from'])) {
        $to_number = $tokens['phone_e164'] ?: sfx_estimator_sanitize_e164($phone);
        if ($to_number) {
            $sms = sfx_estimator_build_sms_message($payload, 'primary');
            $sender = sfx_estimator_prepare_twilio_sender($opt['twilio_from']);
            if ($sender['value'] === '') {
                sfx_estimator_debug_log('twilio_sms_invalid_from_number', array(
                    'estimate_id' => $payload['estimate_id'],
                    'raw_from' => sfx_estimator_mask_phone_for_log($opt['twilio_from']),
                ));
                error_log('Twilio SMS skipped: missing From/MessagingServiceSid for estimate ' . $payload['estimate_id']);
                $sender = null;
            } elseif ($sender['field'] === 'From' && !$sender['is_sanitized']) {
                sfx_estimator_debug_log('twilio_sms_invalid_from_number', array(
                    'estimate_id' => $payload['estimate_id'],
                    'raw_from' => sfx_estimator_mask_phone_for_log($opt['twilio_from']),
                ));
            }
            $segments = sfx_estimator_count_sms_segments($sms);
            $body_length = function_exists('mb_strlen') ? mb_strlen($sms, 'UTF-8') : strlen($sms);
            sfx_estimator_debug_log('twilio_sms_prepare', array(
                'estimate_id' => $payload['estimate_id'],
                'quote_id' => $quote_id,
                'notify' => $notify,
                'to' => sfx_estimator_mask_phone_for_log($to_number),
                'from' => $sender ? ($sender['field'] === 'MessagingServiceSid' ? 'MessagingService:' . $sender['value'] : $sender['masked']) : '',
                'segments' => $segments,
                'body_length' => $body_length,
            ));
            if ($sender) {
                $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($opt['twilio_sid']) . '/Messages.json';
                $body = array('To' => $to_number, 'Body' => $sms);
                $body[$sender['field']] = $sender['value'];
                $args = array(
                    'body' => $body,
                    'headers' => array('Authorization' => 'Basic ' . base64_encode($opt['twilio_sid'] . ':' . $opt['twilio_token'])),
                    'timeout' => 20,
                );
                $resp = wp_remote_post($url, $args);
                if (is_wp_error($resp)) {
                    error_log('Twilio SMS error: ' . $resp->get_error_message());
                    sfx_estimator_debug_log('twilio_sms_request_error', array(
                        'estimate_id' => $payload['estimate_id'],
                        'error' => $resp->get_error_message(),
                    ));
                } else {
                    $http_code = (int) wp_remote_retrieve_response_code($resp);
                    $resp_body = wp_remote_retrieve_body($resp);
                    $resp_json = json_decode($resp_body, true);
                    if ($http_code >= 200 && $http_code < 300) {
                        $sid = is_array($resp_json) && isset($resp_json['sid']) ? $resp_json['sid'] : '';
                        sfx_estimator_debug_log('twilio_sms_sent', array(
                            'estimate_id' => $payload['estimate_id'],
                            'quote_id' => $quote_id,
                            'http_code' => $http_code,
                            'sid' => $sid,
                        ));
                        sfx_estimator_schedule_sms_followups($quote_id, $payload['estimate_id']);
                        sfx_estimator_update_followup_stage($quote_id, 'primary_sent');
                    } else {
                        $twilio_code = is_array($resp_json) && isset($resp_json['code']) ? $resp_json['code'] : null;
                        $twilio_message = is_array($resp_json) && isset($resp_json['message']) ? $resp_json['message'] : '';
                        $twilio_more = is_array($resp_json) && isset($resp_json['more_info']) ? $resp_json['more_info'] : '';
                        sfx_estimator_debug_log('twilio_sms_api_error', array(
                            'estimate_id' => $payload['estimate_id'],
                            'quote_id' => $quote_id,
                            'http_code' => $http_code,
                            'twilio_code' => $twilio_code,
                            'twilio_message' => $twilio_message,
                            'twilio_more_info' => sfx_estimator_trim_for_log($twilio_more),
                            'response' => sfx_estimator_trim_for_log($resp_body),
                        ));
                        $extra = '';
                        if ($twilio_code || $twilio_message) {
                            $extra = sprintf(' (Twilio %s: %s)', $twilio_code ?: 'n/a', $twilio_message);
                        }
                        error_log('Twilio SMS error HTTP: ' . $http_code . $extra);
                    }
                }
            }
        } else {
            error_log('Twilio SMS skipped: invalid recipient number for estimate ' . $payload['estimate_id']);
            sfx_estimator_debug_log('twilio_sms_invalid_to_number', array(
                'estimate_id' => $payload['estimate_id'],
                'quote_id' => $quote_id,
                'raw_phone' => sfx_estimator_mask_phone_for_log($phone),
            ));
        }
    }

    // Notify store
    wp_mail(
        $opt['email_to'],
        "New estimate {$payload['estimate_ref']}",
        "Lead:\n{$first} {$last}\n{$phone}\n{$email}\nNotify: {$notify}\n{$type} / {$make} / {$series} / {$model}\nIssues: ".$tokens['issues']."\nTotal: $".$tokens['total']."\nTicket: ".$payload['estimate_ref']."\nNotes: ".$notes."\nIP: ".$ip
    );

    return rest_ensure_response(array(
        'ok' => true,
        'quote_id' => intval($quote_id),
        'estimate_ref' => $payload['estimate_ref'],
        'estimate_id' => $payload['estimate_id'],
        'total' => number_format($meta['total'], 2),
        'estimate' => $payload,
        'links' => array(
            'maps' => $short_links,
        ),
        'message' => 'Estimate created and notifications sent.',
    ));
}

/* Manual Sync helpers */
function sfx_estimator_sync_from_source($test_only=false) {
    $opt = get_option('sfx_estimator_options');
    $src = $opt['data_source'];
    $rows = array();
    $source_name = 'local';

    if ($src === 'gsheet_csv') {
        $source_name = 'Google Sheet (CSV)';
        $url = trim($opt['sheet_csv_url']);
        if (!$url) return array('ok'=>false,'message'=>'No Google Sheet CSV URL set.','source'=>$source_name);
        $res = wp_remote_get($url, array('timeout'=>20));
        if (is_wp_error($res)) return array('ok'=>false, 'message'=>$res->get_error_message(),'source'=>$source_name);
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ($code !== 200 || !$body) return array('ok'=>false,'message'=>'CSV fetch failed (HTTP '.$code.').','source'=>$source_name);
        if (sfx_estimator_body_looks_like_html($body)) {
            return array('ok'=>false,'message'=>'CSV URL returned HTML/JS. Publish the sheet (File → Share → Anyone with link) and use the “export?format=csv” link.','source'=>$source_name);
        }
        $rows = sfx_estimator_parse_csv_rows($body);
    } elseif ($src === 'apps_script') {
        $source_name = 'Apps Script';
        $url = trim($opt['apps_script_url']);
        if (!$url) return array('ok'=>false,'message'=>'No Apps Script URL set.','source'=>$source_name);
        $args = array('timeout'=>20);
        $token = trim($opt['apps_script_token']);
        if ($token) $url = add_query_arg('token', rawurlencode($token), $url);
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) return array('ok'=>false, 'message'=>$res->get_error_message(),'source'=>$source_name);
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ($code !== 200 || !$body) return array('ok'=>false,'message'=>'Apps Script fetch failed (HTTP '.$code.').','source'=>$source_name);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) return array('ok'=>false,'message'=>'Apps Script returned invalid JSON.','source'=>$source_name);
        if (isset($data['rows']) && is_array($data['rows'])) $rows = $data['rows'];
        elseif (isset($data[0]) && is_array($data)) $rows = $data;
        else return array('ok'=>false,'message'=>'Apps Script JSON missing rows.','source'=>$source_name);
    } else {
        return array('ok'=>false,'message'=>'Data source is Local JSON; nothing to sync.','source'=>'local','rows'=>0);
    }

    $matrix = sfx_estimator_rows_to_matrix($rows);
    if ($test_only) return array('ok'=>true,'rows'=>count($rows),'source'=>$source_name);
    if (!is_array($matrix) || empty($matrix)) {
        $extra = sfx_estimator_diagnose_empty_matrix($rows);
        $message = 'Parsed matrix is empty.';
        if ($extra) $message .= ' ' . $extra;
        return array('ok'=>false,'message'=>$message,'source'=>$source_name);
    }

    $opt['price_matrix'] = $matrix;
    $opt['sync_last'] = time();
    update_option('sfx_estimator_options', $opt);
    return array('ok'=>true,'rows'=>count($rows),'source'=>$source_name);
}

function sfx_estimator_parse_csv_rows($csv) {
    $csv = str_replace("\r\n", "\n", $csv);
    $csv = str_replace("\r", "\n", $csv);
    $lines = explode("\n", trim($csv));
    $rows = array();
    $header = null;
    foreach ($lines as $line) {
        if ($line === '') continue;
        $fields = str_getcsv($line);
        if (!$header) {
            $candidate = array();
            foreach ($fields as $field) {
                $candidate[] = sfx_estimator_clean_header_label($field);
            }
            if (!sfx_estimator_fields_look_like_header($candidate)) {
                continue;
            }
            $header = $candidate;
            continue;
        }
        $row = array();
        foreach ($fields as $i => $v) {
            $key = isset($header[$i]) && $header[$i] !== '' ? $header[$i] : 'col'.$i;
            $row[$key] = trim(sfx_estimator_strip_utf8_bom($v));
        }
        if (!empty(array_filter($row, 'strlen'))) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function sfx_estimator_rows_to_matrix($rows) {
    $matrix = array();
    foreach ($rows as $r) {
        if (!is_array($r)) continue;

        $normalized = array();
        foreach ($r as $key => $value) {
            $norm_key = sfx_estimator_normalize_column_key($key);
            if ($norm_key === '') continue;
            if (!array_key_exists($norm_key, $normalized) || $normalized[$norm_key] === '') {
                $normalized[$norm_key] = trim((string) $value);
            }
        }

        $type   = sfx_estimator_pick_column_value($normalized, array('devicetype','device','type'));
        $make   = sfx_estimator_pick_column_value($normalized, array('make','brand','manufacturer'));
        $series = sfx_estimator_normalize_optional_field(sfx_estimator_pick_column_value($normalized, array('series','generation','gen','family')));
        $model  = sfx_estimator_pick_column_value($normalized, array('model','devicevariant','sku'));
        $issue  = sfx_estimator_pick_column_value($normalized, array('issue','repair','problem','service'));
        $price_raw = sfx_estimator_pick_column_value($normalized, array('price','cost','amount','quote'));

        if ($type === '' || $make === '' || $model === '' || $issue === '') continue;

        if (!isset($matrix[$type])) $matrix[$type] = array();
        if (!isset($matrix[$type][$make])) $matrix[$type][$make] = array();

        $price_value = sfx_estimator_parse_price_value($price_raw);

        if ($series !== '') {
            if (!isset($matrix[$type][$make][$series])) $matrix[$type][$make][$series] = array();
            if (!isset($matrix[$type][$make][$series][$model])) $matrix[$type][$make][$series][$model] = array();
            $matrix[$type][$make][$series][$model][$issue] = $price_value;
        } else {
            if (!isset($matrix[$type][$make][$model])) $matrix[$type][$make][$model] = array();
            $matrix[$type][$make][$model][$issue] = $price_value;
        }
    }
    return $matrix;
}

function sfx_estimator_body_looks_like_html($body) {
    $snippet = trim(substr($body, 0, 200));
    if ($snippet === '') return false;
    if ($snippet[0] === '<') return true;
    if (stripos($snippet, '<!doctype html') === 0) return true;
    if (stripos($snippet, '<html') === 0) return true;
    if (stripos($snippet, '<script') === 0) return true;
    if (stripos($snippet, '<body') === 0) return true;
    return false;
}

function sfx_estimator_diagnose_empty_matrix($rows) {
    if (empty($rows)) {
        return 'The CSV only contained a header row or blank lines.';
    }
    $sample = null;
    foreach ($rows as $row) {
        if (is_array($row) && !empty($row)) {
            $sample = $row;
            break;
        }
    }
    if (!$sample) {
        return 'No usable rows were found in the CSV.';
    }
    $missing = array();
    $requirements = sfx_estimator_required_column_groups();
    foreach ($requirements as $label => $candidates) {
        if ($label === 'Series (optional)') continue;
        if (!sfx_estimator_row_has_candidate_key($sample, $candidates)) {
            $missing[] = $label;
        }
    }
    if (!empty($missing)) {
        $headers_seen = implode(', ', array_keys($sample));
        $message = 'Required columns missing: ' . implode(', ', $missing) . '. Confirm the header row matches DeviceType, Make, Series, Model, Issue, Price.';
        if ($headers_seen !== '') {
            $message .= ' Headers detected: ' . $headers_seen . '.';
        }
        return $message;
    }
    return '';
}

function sfx_estimator_row_has_candidate_key($row, $candidates) {
    if (!is_array($row)) return false;
    $normalized = array();
    foreach (array_keys($row) as $key) {
        $normalized[] = sfx_estimator_normalize_column_key($key);
    }
    foreach ($candidates as $candidate) {
        if ($candidate === '') continue;
        foreach ($normalized as $normalized_key) {
            if ($normalized_key === $candidate || strpos($normalized_key, $candidate) !== false) {
                return true;
            }
        }
    }
    return false;
}

function sfx_estimator_strip_utf8_bom($value) {
    $value = (string) $value;
    if (substr($value, 0, 3) === "\xEF\xBB\xBF") {
        $value = substr($value, 3);
    }
    return $value;
}

function sfx_estimator_clean_header_label($value) {
    $value = sfx_estimator_strip_utf8_bom($value);
    $value = str_replace(array("\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D"), ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function sfx_estimator_required_column_groups() {
    return array(
        'Device Type' => array('devicetype','device','type'),
        'Make / Brand' => array('make','brand','manufacturer'),
        'Series (optional)' => array('series','generation','gen','family'),
        'Model' => array('model','devicevariant','sku'),
        'Issue' => array('issue','repair','problem','service'),
        'Price' => array('price','cost','amount','quote'),
    );
}

function sfx_estimator_fields_look_like_header($fields) {
    if (empty($fields)) return false;
    $non_empty = 0;
    $normalized = array();
    foreach ($fields as $field) {
        $clean = sfx_estimator_clean_header_label($field);
        if ($clean !== '') {
            $non_empty++;
        }
        $norm = sfx_estimator_normalize_column_key($clean);
        if ($norm !== '') {
            $normalized[] = $norm;
        }
    }
    if ($non_empty < 3) {
        return false;
    }
    if (empty($normalized)) {
        return false;
    }
    $requirements = sfx_estimator_required_column_groups();
    $found = 0;
    foreach ($requirements as $label => $candidates) {
        if ($label === 'Series (optional)') continue;
        foreach ($candidates as $candidate) {
            if ($candidate === '') continue;
            if (in_array($candidate, $normalized, true)) {
                $found++;
                break;
            }
        }
    }
    return $found >= 3;
}

function sfx_estimator_normalize_column_key($label) {
    $label = sfx_estimator_clean_header_label($label);
    $label = strtolower((string) $label);
    if ($label === '') return '';
    $label = preg_replace('/[^a-z0-9]/', '', $label);
    return $label;
}

function sfx_estimator_pick_column_value($normalized_row, $candidates) {
    foreach ($candidates as $candidate) {
        if ($candidate === '') continue;
        if (isset($normalized_row[$candidate])) {
            $value = trim((string) $normalized_row[$candidate]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    foreach ($candidates as $candidate) {
        if ($candidate === '') continue;
        foreach ($normalized_row as $key => $value) {
            if ($key === $candidate) continue;
            if (strpos($key, $candidate) !== false) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }
    return '';
}

function sfx_estimator_normalize_optional_field($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    $normalized = strtolower($value);
    $empty_markers = array('n/a','na','none','n.a.','n\\a','-','--');
    if (in_array($normalized, $empty_markers, true)) {
        return '';
    }
    return $value;
}

function sfx_estimator_parse_price_value($value) {
    $value = trim((string) $value);
    if ($value === '') return 0.0;
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    if ($value === '' || $value === '.' || $value === '-') return 0.0;
    return floatval($value);
}
