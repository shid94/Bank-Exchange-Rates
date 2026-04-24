<?php
/**
 * Plugin Name: Bank Exchange Rates
 * Description: Dynamic bank exchange rates with currencies, flags, sorting, timestamp, and calculator.
 * Version: 5.0
 * Author: Rashid Migadde
 * Plugin URI: https://github.com/shid94/Bank-Exchange-Rates/
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Tags: exchange rates, forex, currency, bank rates, ugx, calculator
 * Author URI: https://github.com/shid94/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

class Bank_Exchange_Rates {

    private $currencies;
    private $flags;

    public function __construct() {

        $this->currencies = include plugin_dir_path(__FILE__) . 'currencies.php';

        $this->flags = [
            'USD'=>'🇺🇸','EUR'=>'🇪🇺','GBP'=>'🇬🇧','KES'=>'🇰🇪',
            'TZS'=>'🇹🇿','UGX'=>'🇺🇬','RWF'=>'🇷🇼','ZAR'=>'🇿🇦','AED'=>'🇦🇪'
        ];

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_shortcode('bank_rates', [$this, 'display']);
        add_shortcode('bank_rates_marquee', [$this, 'marquee']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function assets() {
        wp_register_style('bank-css', plugin_dir_url(__FILE__) . 'assets/css/style.css');
        wp_register_script('bank-js', plugin_dir_url(__FILE__) . 'assets/js/script.js', [], false, true);
    }

    public function menu() {
        add_menu_page(
            'Exchange Rates',
            'Exchange Rates',
            'manage_options',
            'bank-rates',
            [$this, 'page'],
            'dashicons-money-alt',
            25
        );
    }

    public function settings() {
        register_setting('bank_group', 'bank_rates');
    }

    /**
     * SANITIZE INPUT (IMPORTANT FIX)
     */
    public function save($input) {

        $clean = [];

        if (is_array($input)) {
            foreach ($input as $item) {
                if (!isset($item['code'])) continue;

                $clean[] = [
                    'code' => sanitize_text_field($item['code']),
                    'buy'  => isset($item['buy']) ? floatval($item['buy']) : 0,
                    'sell' => isset($item['sell']) ? floatval($item['sell']) : 0,
                ];
            }
        }

        update_option('bank_last_update', current_time('mysql'));

        return $clean;
    }

    private function sort($rates) {
        usort($rates, function($a,$b){
            if (($a['code'] ?? '') == 'USD') return -1;
            if (($b['code'] ?? '') == 'USD') return 1;
            return strcmp($a['code'] ?? '', $b['code'] ?? '');
        });

        return $rates;
    }

    private function get_flag($code) {
        return $this->flags[$code] ?? '🏳️';
    }

    public function page() {

        if (!current_user_can('manage_options')) {
            return;
        }

        $rates = get_option('bank_rates', []);
        ?>
        <div class="wrap">
            <h1>Exchange Rates</h1>

            <form method="post" action="options.php">
                <?php settings_fields('bank_group'); ?>

                <table class="widefat striped" id="rates-table">
                    <thead>
                        <tr>
                            <th>Currency</th>
                            <th>Buy</th>
                            <th>Sell</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rates as $i => $r): ?>
                        <tr>
                            <td>
                                <select name="bank_rates[<?php echo esc_attr($i); ?>][code]">
                                    <?php foreach ($this->currencies as $code => $name): ?>
                                        <option value="<?php echo esc_attr($code); ?>"
                                            <?php selected($r['code'] ?? '', $code); ?>>
                                            <?php echo esc_html($code . ' - ' . $name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <input type="number" step="0.01"
                                    name="bank_rates[<?php echo esc_attr($i); ?>][buy]"
                                    value="<?php echo esc_attr($r['buy'] ?? ''); ?>" required>
                            </td>

                            <td>
                                <input type="number" step="0.01"
                                    name="bank_rates[<?php echo esc_attr($i); ?>][sell]"
                                    value="<?php echo esc_attr($r['sell'] ?? ''); ?>" required>
                            </td>

                            <td>
                                <button type="button" class="button remove-row">X</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="button" class="button button-primary" id="add-row">Add Currency</button>

                <?php submit_button('Save Rates'); ?>
            </form>
        </div>

        <script>
        const currencies = <?php echo json_encode($this->currencies); ?>;

        document.addEventListener('DOMContentLoaded', function () {

            document.getElementById('add-row').onclick = function () {
                let table = document.querySelector('#rates-table tbody');
                let index = table.rows.length;

                let options = '';
                for (let code in currencies) {
                    options += `<option value="${code}">${code} - ${currencies[code]}</option>`;
                }

                let row = table.insertRow();
                row.innerHTML = `
                    <td><select name="bank_rates[${index}][code]">${options}</select></td>
                    <td><input type="number" step="0.01" name="bank_rates[${index}][buy]" required></td>
                    <td><input type="number" step="0.01" name="bank_rates[${index}][sell]" required></td>
                    <td><button type="button" class="button remove-row">X</button></td>
                `;
            };

            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-row')) {
                    e.target.closest('tr').remove();
                }
            });

        });
        </script>
        <?php
    }

    public function display() {

        wp_enqueue_style('bank-css');
        wp_enqueue_script('bank-js');

        $rates = $this->sort(get_option('bank_rates', []));
        $updated = get_option('bank_last_update');

        ob_start();
        ?>

        <div class="bank-wrapper">

            <div class="bank-header">
                <strong>Exchange Rates</strong>
                <span>
                    <?php echo $updated ? esc_html(date('d M Y, H:i', strtotime($updated))) : 'Not updated'; ?>
                </span>
            </div>

            <table class="bank-rates-table">
                <thead>
                    <tr>
                        <th>Currency</th>
                        <th>Buy</th>
                        <th>Sell</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rates as $r): 
                        $code = $r['code'] ?? '';
                        $name = $this->currencies[$code] ?? '';
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html($this->get_flag($code) . ' ' . $code . ' - ' . $name); ?>
                        </td>
                        <td><?php echo esc_html(number_format((float)($r['buy'] ?? 0), 2)); ?></td>
                        <td><?php echo esc_html(number_format((float)($r['sell'] ?? 0), 2)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="bank-calculator">
                <h4>Forex Calculator</h4>

                <input type="number" id="amount" placeholder="Enter amount">

                <select id="type">
                    <option value="buy">Selling Currency to Bank</option>
                    <option value="sell">Buying Currency from Bank</option>
                </select>

                <select id="currency">
                    <?php foreach ($rates as $r): ?>
                        <option value="<?php echo esc_attr($r['buy']); ?>"
                                data-sell="<?php echo esc_attr($r['sell']); ?>">
                            <?php echo esc_html($r['code']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button onclick="convertCurrency()">Convert</button>

                <p id="result"></p>
            </div>

        </div>

        <?php
        return ob_get_clean();
    }

    public function marquee() {

        wp_enqueue_style('bank-css');

        $rates = $this->sort(get_option('bank_rates', []));

        if (empty($rates)) return '';

        ob_start();
        ?>

        <div class="marquee">
            <div class="marquee-content">

                <?php foreach ($rates as $r): ?>
                    <span>
                        <?php echo esc_html($this->get_flag($r['code']) . ' ' . $r['code']); ?>
                        - B: <?php echo number_format((float)$r['buy'], 2); ?>
                        | S: <?php echo number_format((float)$r['sell'], 2); ?>
                    </span>
                <?php endforeach; ?>

            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}

new Bank_Exchange_Rates();
