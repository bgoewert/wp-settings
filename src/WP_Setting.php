<?php

namespace BGoewert\WP_Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Setting class used to create an option and define the setting to be displayed in the admin panel.
 */
class WP_Setting
{

    /**
     * The name of the option/setting. Shorthand slug, minus the prefix.
     *
     * @var string
     */
    public $name;

    /**
     * The machine slug for the option/setting.
     *
     * @var string
     */
    public $slug;

    /**
     * The setting's title, or display name.
     *
     * @var string
     */
    public $title;

    /**
     * Default value for the option/setting.
     *
     * @var mixed
     */
    public $default_value;

    /**
     * The setting's description
     *
     * @var string
     */
    public $description;

    /**
     * Whether the setting is required.
     *
     * @var bool
     */
    public $required;

    /**
     * The field type.
     *
     * @var string
     */
    public $type;

    /**
     * Input width.
     *
     * @var string
     */
    public $width;

    /**
     * The page slug
     *
     * @var string
     */
    public $page;

    /**
     * The section slug.
     *
     * @var string
     */
    public $section;

    /**
     * Array of options to use for the select or radio inputs.
     * @var array{value:string,label:string}
     */
    public $options;

    /**
     * The setting's callback used to display in the admin panel.
     *
     * @var callable
     */
    protected $callback;

    /**
     * The arguments for the setting.
     * 
     * Example Keys:
     * - options => _array{value:string,label:string}_ Array of options to use for the select or radio inputs.
     *
     * @var array
     */
    public $args;

    /**
     * Plugin text domain.
     *
     * @var string
     */
    private static $text_domain;

    /**
     * Array of allowed HTML tags.
     *
     * @var array
     */
    public static $allowed_html = array(
        'p'        => array(
            'class' => array(),
        ),
        'a'        => array(
            'href'   => array(),
            'target' => array(),
            'title'  => array(),
        ),
        'br'       => array(),
        'span'     => array(
            'class' => array(),
            'style' => array(),
        ),
        'code'     => array(),
        'label'   => array(
            'for' => array(),
            'class' => array(),
        ),
        'input'    => array(
            'type'         => array(),
            'name'         => array(),
            'disabled'     => array(),
            'checked'      => array(),
            'class'        => array(),
            'id'           => array(),
            'value'        => array(),
            'autocomplete' => array(),
            'style'        => array(),
            'required'     => array(),
            'placeholder'  => array(),
            'form'         => array(),
            'max'          => array(),
            'min'          => array(),
            'minlength'    => array(),
            'maxlength'    => array(),
            'multiple'     => array(),
            'pattern'      => array(),
            'readonly'     => array(),
            'size'         => array(),
            'step'         => array(),
            'hidden'       => array(),
        ),
        'textarea' => array(
            'name'         => array(),
            'class'        => array(),
            'rows'         => array(),
            'cols'         => array(),
            'id'           => array(),
            'disabled'     => array(),
            'value'        => array(),
            'required'     => array(),
            'autocomplete' => array(),
            'form'         => array(),
            'minlength'    => array(),
            'maxlength'    => array(),
            'placeholder'  => array(),
            'readonly'     => array(),
            'spellcheck'   => array(),
            'wrap'         => array(),
        ),
        'select'   => array(
            'id'           => array(),
            'autofocus'    => array(),
            'autocomplete' => array(),
            'name'         => array(),
            'disabled'     => array(),
            'class'        => array(),
            'multiple'     => array(),
            'required'     => array(),
            'size'         => array(),
            'form'         => array(),
            'style'        => array(),
        ),
        'table'    => array(
            'autofocus' => array(),
            'class'     => array(),
            'id'        => array(),
            'style'     => array(),
        ),
        'button'   => array(
            'type'         => array(),
            'class'        => array(),
            'id'           => array(),
            'value'        => array(),
            'disabled'     => array(),
            'aria-label'   => array(),
            'aria-expanded' => array(),
            'aria-controls' => array(),
            'aria-hidden'  => array(),
            'style'        => array(),
            'data-toggle'   => array(),
        ),
    );

    /**
     * Creates an option/setting.
     *
     * @param string        $name          The name of the setting. Shorthand slug, minus the plugin's prefix.
     * @param string        $title         The title (display name) of the setting.
     * @param string        $type          The type of the input to be used for the setting.
     * @param string        $page          Page name for where to display the setting.
     * @param string        $section       Section name under which to display the setting.
     * @param string|null   $width         Width of the input.
     * @param string|null   $description   Description to use for the setting.
     * @param mixed|null    $default_value Default value to use for the option/setting.
     * @param callable|null $callback      Callback function that displays the input for the setting.
     * @param array|null    $options       Array of options to use for the select or radio inputs.
     */
    public function __construct($text_domain, $name, $title, $type, $page, $section, $width = \null, $description = \null, $required = \false, $default_value = \null, $callback = \null, $args = array())
    {
        self::$text_domain   = $text_domain;
        $this->name          = $name;
        $this->slug          = self::$text_domain . '_' . $this->name;
        $this->title         = $title;
        $this->type          = $type;
        $this->width         = $width;
        $this->page          = $page;
        $this->section       = preg_replace('/\s+/', '_', strtolower($section));
        $this->default_value = $default_value;
        $this->description   = $description;
        $this->required      = $required;
        $this->callback      = $callback;
        $this->args          = $args;

        if (null === $this->callback) {
            switch ($this->type) {
                case 'checkbox':
                    $this->callback = array($this, 'init_checkbox');
                    if ($this->default_value && 'on' !== $this->default_value) {
                        $this->default_value = 'on';
                    }
                    break;

                case 'textarea':
                    $this->callback = array($this, 'init_textarea');
                    break;

                /* case 'password':
                $this->callback = array($this, 'init_password');
                break; */
                case 'select':
                    $this->callback = array($this, 'init_select');
                    break;

                case 'radio':
                    $this->callback = array($this, 'init_radio');
                    break;

                default:
                    $this->callback = array($this, 'init_type');
                    break;
            }
        }
    }

    /**
     * Initialize the setting.
     */
    public function init()
    {
        $this->add_setting();
    }

    /**
     * Create the option/setting.
     */
    private function add_setting()
    {
        add_option($this->slug, $this->default_value);
        register_setting(self::$text_domain . '_' . $this->page, $this->slug, array('default' => $this->default_value));
        add_settings_field($this->slug . '_field', $this->title . ($this->required ? ' <span class="required">*</span>' : ''), $this->callback, self::$text_domain . '_' . $this->page, self::$text_domain . '_section_' . $this->section, $this->args);
    }

    /**
     * Get a defined option. This can either be prefixed with 'seiler-salesforce_' or not. It will only look for options related to this plugin.
     *
     * @param string $setting The name of the setting. Expected not to be SQL-escaped.
     * @param mixed $default_value The default value for the setting if no value exists.
     */
    public static function get($setting, $default_value = \false, $decrypt = \false)
    {
        if (self::$text_domain && \false === strpos($setting, self::$text_domain)) {
            $setting = self::$text_domain . '_' . $setting;
        }
        $value = get_option($setting, $default_value);
        if ($decrypt) {
            return self::decrypt($value);
        }
        return $value;
    }

    /**
     * Set an option to a defined value. This can either be prefixed with 'seiler-salesforce_' or not. It will only look for options related to this plugin.
     *
     * @param string $setting The name of the setting. Expected not to be SQL-escaped.
     * @param mixed  $value Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
     */
    public static function set($setting, $value, $encrypt = \false)
    {
        if ($encrypt) $value = self::encrypt($value);
        if (self::$text_domain && \false === strpos($setting, self::$text_domain)) {
            $setting = self::$text_domain . '_' . $setting;
        }
        return update_option($setting, $value);
    }

    public function save()
    {
        $value = isset($_POST[$this->slug]) ? $_POST[$this->slug] : null;

        switch ($this->type) {
            case 'checkbox':
                self::set($this->slug, $value == 'on' ? true : false);
                break;

            case 'password':
                self::set($this->slug, $value, \true);
                break;

            default:
                self::set($this->slug, $value);
                break;
        }
    }

    /**
     * Create an input using a defined type.
     */
    public function init_type()
    {
        $value = self::get($this->slug);
        if (!$value) {
            $value = $this->default_value;
        }

        if ($value && 'password' === $this->type) {
            $value = self::decrypt($value);
        }

        $atts = '';
        if ($this->width) {
            $atts .= ' style="width:' . $this->width . ';"';
        }
        if ($this->required) {
            $atts .= ' required';
        }

        echo wp_kses(sprintf('<input type="%s" name="%s" value="%s"%s>', $this->type, $this->slug, $value, $atts), self::$allowed_html);
        if ('password' === $this->type) {
            echo wp_kses('<button type="button" class="button wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Show password"><span class="text">Show</span></button>', self::$allowed_html);
        }
        if ($this->description) {
            echo wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }
    /**
     * Create a textarea.
     */
    public function init_textarea()
    {
        $value = self::get($this->slug);

        if (!$value) {
            $value = $this->default_value;
        }

        $atts = '';
        if ($this->width) {
            $atts .= ' style="width:' . $this->width . ';"';
        }
        if ($this->required) {
            $atts .= ' required';
        }

        echo wp_kses(sprintf('<textarea name="%s"%s>%s</textarea>', $this->slug, $atts, $value), self::$allowed_html);
        if ($this->description) {
            echo wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    /**
     * Create a checkbox input.
     */
    public function init_checkbox()
    {
        $value = boolval(self::get($this->slug));
        $atts = ' ' . checked($value, true, false);
        if ($this->required) {
            $atts .= ' required';
        }
        echo wp_kses(sprintf('<input id="%s" type="checkbox" name="%s"%s>', $this->slug,  $this->slug, $atts), self::$allowed_html);
        if ($this->description) {
            echo wp_kses(sprintf('<label for="%s" class="description" style="vertical-align:middle;margin-left:1em;">%s</label>', $this->slug, $this->description), self::$allowed_html);
        }
    }

    /**
     * Create a select input.
     */
    public function init_select()
    {
        $value = self::get($this->slug);
        if (!$value) {
            $value = $this->default_value;
        }
        echo sprintf('<select name="%s">', $this->slug);
        foreach ($this->args['options'] as $option => $label) {
            $atts = ' ' . selected($value, $option, false);
            if ($this->required) {
                $atts .= ' required';
            }
            echo sprintf('<option value="%s"%s>%s</option>', $option, $atts, $label);
        }
        echo '</select>';
        if ($this->description) {
            echo wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    /**
     * Create a radio input. If there are more than one option, it will create a field set.
     */
    public function init_radio()
    {
        $value = self::get($this->slug);
        if (!$value) {
            $value = $this->default_value;
        }

        $option_count = count($this->options);

        if ($option_count > 1) {
            echo '<fieldset>';
        }

        foreach ($this->options as $option => $label) {
            $atts = ' ' . checked($value, $option, false);
            if ($this->required) {
                $atts .= ' required';
            }
            echo sprintf('<label><input type="radio" name="%s" value="%s"%s>%s</label><br>', $this->slug, $option, $atts, $label);
        }

        if ($option_count > 1) {
            echo '</fieldset>';
        }

        if ($this->description) {
            echo wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    public static function random_bytes($length)
    {
        if (function_exists('random_bytes')) {
            /** @disregard p1010 Undefined function */
            return \random_bytes($length);
        }

        return openssl_random_pseudo_bytes($length);
    }

    /**
     * Decrypt a value.
     *
     * @param string $value The value to decrypt.
     *
     * @return string The decrypted value.
     */
    public static function decrypt($value)
    {

        if (empty($value)) {
            return $value;
        }

        $crypt = new WP_Data_Encryption(
            strtoupper(str_replace('-', '_', self::$text_domain . '_key')),
            strtoupper(str_replace('-', '_', self::$text_domain . '_nonce'))
        );

        try {
            $decrypted_value = $crypt->decrypt($value);
        } catch (\Exception $e) {
            trigger_error('Decryption failed: ' . $e->getMessage(), E_USER_WARNING);
            return $value;
        }
        return $decrypted_value;
    }

    /**
     * Encrypt a value.
     *
     * @param string $value The value to encrypt.
     *
     * @return string The encrypted value.
     */
    public static function encrypt($value)
    {
        $crypt = new WP_Data_Encryption(
            strtoupper(str_replace('-', '_', self::$text_domain . '_key')),
            strtoupper(str_replace('-', '_', self::$text_domain . '_nonce'))
        );

        try {
            return $crypt->encrypt($value);
        } catch (\Exception $e) {
            trigger_error('Encryption failed: ' . $e->getMessage(), E_USER_WARNING);
            return $value;
        }
    }
}
