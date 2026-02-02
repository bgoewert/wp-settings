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
     * The setting's sanitize callback for WordPress register_setting.
     *
     * @var callable|null
     */
    protected $sanitize_callback;

    /**
     * The arguments for the setting.
     *
     * Example Keys:
     * - options => _array{value:string,label:string}_ Array of options to use for the select or radio inputs.
     * - children => _WP_Setting[]_ Array of child settings for advanced field type.
     * - sanitize_callback => _callable_ Sanitization callback for register_setting.
     * - conditions => _array_ Conditional visibility rules. Each condition has:
     *   - 'field' => string - Field name to check
     *   - 'operator' => string - 'equals', 'not_equals', 'in', 'not_in', 'empty', 'not_empty'
     *   - 'value' => mixed - Value(s) to compare (not needed for empty/not_empty)
     *
     * @var array
     */
    public $args;

    /**
     * Child settings for advanced field type.
     *
     * @var WP_Setting[]
     */
    public $children;

    /**
     * Conditional visibility rules for the field.
     *
     * Each condition is an array with:
     * - 'field' => string - The name of the field to check
     * - 'operator' => string - One of: 'equals', 'not_equals', 'in', 'not_in', 'empty', 'not_empty'
     * - 'value' => mixed - The value(s) to compare against (not needed for empty/not_empty)
     *
     * Multiple conditions are combined with AND logic.
     *
     * @var array
     */
    public $conditions;

    /**
     * Plugin text domain. Set by WP_Settings during initialization or manually via $text_domain assignment.
     *
     * @var string
     */
    public static $text_domain;

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
            'style'        => array(),
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
        'details'  => array(
            'class' => array(),
            'style' => array(),
            'open'  => array(),
        ),
        'summary'  => array(
            'class' => array(),
            'style' => array(),
        ),
        'div'      => array(
            'class' => array(),
            'style' => array(),
            'id'    => array(),
        ),
        'hr'       => array(
            'class' => array(),
            'style' => array(),
        ),
        'h4'       => array(
            'class' => array(),
            'style' => array(),
        ),
        'ul'       => array(
            'class'      => array(),
            'style'      => array(),
            'id'         => array(),
            'data-field' => array(),
        ),
        'li'       => array(
            'class'    => array(),
            'style'    => array(),
            'id'       => array(),
            'data-key' => array(),
        ),
        'tr'       => array(
            'class'           => array(),
            'style'           => array(),
            'id'              => array(),
            'data-conditions' => array(),
            'data-field'      => array(),
        ),
        'th'       => array(
            'class' => array(),
            'scope' => array(),
        ),
        'td'       => array(
            'class'   => array(),
            'colspan' => array(),
        ),
    );

    /**
     * Creates an option/setting.
     *
     * Note: text_domain is no longer a parameter. Use WP_Setting::set_text_domain() or WP_Settings class to configure.
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
     * @param array|null    $args          Array of options to use for the select or radio inputs.
     */
    public function __construct($name, $title, $type, $page, $section, $width = \null, $description = \null, $required = \false, $default_value = \null, $callback = \null, $args = array())
    {
        $this->name          = $name;
        $prefix              = !array_key_exists('prefix', $args) ? self::$text_domain : ($args['prefix'] ?? '');
        $this->slug          = ($prefix === '' ? '' : $prefix . '_') . $this->name;
        $this->title         = $title;
        $this->type          = $type;
        $this->width         = $width;
        $this->page          = $page;
        $this->section       = preg_replace('/\s+/', '_', strtolower($section ?? ''));
        $this->default_value = $default_value;
        $this->description   = $description;
        $this->required      = $required;
        $this->callback      = $callback;
        $this->args          = $args;

        // Extract children from args for advanced field type
        $this->children = isset($args['children']) ? $args['children'] : array();

        // Extract conditions from args for conditional visibility
        $this->conditions = isset($args['conditions']) ? $args['conditions'] : array();

        // Extract sanitize_callback from args if provided
        $this->sanitize_callback = isset($args['sanitize_callback']) ? $args['sanitize_callback'] : null;

        // Set default sanitize_callback based on field type if not provided
        if (null === $this->sanitize_callback) {
            switch ($this->type) {
                case 'email':
                    $this->sanitize_callback = array(__CLASS__, 'sanitize_email');
                    break;

                case 'url':
                    $this->sanitize_callback = array(__CLASS__, 'sanitize_url');
                    break;

                case 'number':
                    $this->sanitize_callback = function($value) {
                        if (empty($value) && $value !== '0' && $value !== 0) {
                            return '';
                        }
                        return is_numeric($value) ? $value : '';
                    };
                    break;

                case 'sortable':
                    $this->sanitize_callback = function($value) {
                        if (!is_array($value)) {
                            return array();
                        }

                        $options = $this->resolve_options();
                        $allowed_keys = array_map('strval', array_keys($options));

                        $sanitized = array();
                        foreach ($value as $item) {
                            if (!is_scalar($item)) {
                                continue;
                            }
                            $item = \sanitize_text_field((string) $item);
                            if ($item === '') {
                                continue;
                            }
                            if (!empty($allowed_keys) && !in_array($item, $allowed_keys, true)) {
                                continue;
                            }
                            if (!in_array($item, $sanitized, true)) {
                                $sanitized[] = $item;
                            }
                        }

                        if (!empty($allowed_keys)) {
                            $missing = array_values(array_diff($allowed_keys, $sanitized));
                            $sanitized = array_merge($sanitized, $missing);
                        }

                        return array_values($sanitized);
                    };
                    break;

                case 'text':
                case 'textarea':
                    $this->sanitize_callback = array(__CLASS__, 'sanitize_text');
                    break;
            }
        }

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

                case 'hidden':
                    $this->callback = array($this, 'init_hidden');
                    break;

                case 'advanced':
                    $this->callback = array($this, 'init_advanced');
                    break;

                case 'fieldset':
                    $this->callback = array($this, 'init_fieldset');
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

        // For fieldset and advanced fields, also register children so they save properly
        // This allows these field types to work in both settings pages and table modals
        if (($this->type === 'fieldset' || $this->type === 'advanced') && !empty($this->children)) {
            foreach ($this->children as $child) {
                $child->init();
            }
        }
    }

    /**
     * Create the option/setting.
     */
    private function add_setting()
    {
        \add_option($this->slug, $this->default_value);

        $register_args = array('default' => $this->default_value);
        if ($this->sanitize_callback !== null) {
            $register_args['sanitize_callback'] = $this->sanitize_callback;
        }

        \register_setting(self::$text_domain . '_' . $this->page, $this->slug, $register_args);

        // Skip add_settings_field for hidden fields to avoid empty table rows
        // Advanced and fieldset fields handle their own rendering including child fields
        if ($this->type !== 'hidden' && $this->type !== 'advanced' && $this->type !== 'fieldset') {
            \add_settings_field($this->slug . '_field', $this->title . ($this->required ? ' <span class="required">*</span>' : ''), $this->callback, self::$text_domain . '_' . $this->page, self::$text_domain . '_section_' . $this->section, $this->args);
        } elseif ($this->type === 'advanced' || $this->type === 'fieldset') {
            // For advanced/fieldset fields, only register the parent field (not children)
            // Children will be registered separately when their init() is called
            \add_settings_field($this->slug . '_field', '', $this->callback, self::$text_domain . '_' . $this->page, self::$text_domain . '_section_' . $this->section, $this->args);
        }
    }

    /**
     * Get a defined option.
     *
     * @param string $setting The name of the setting. Expected not to be SQL-escaped.
     * @param mixed $default_value The default value for the setting if no value exists.
     */
    public static function get($setting, $default_value = \false, $decrypt = \false)
    {
        if (self::$text_domain && \false === strpos($setting, self::$text_domain)) {
            $setting = self::$text_domain . '_' . $setting;
        }
        $value = \get_option($setting, $default_value);
        if ($decrypt) {
            return self::decrypt($value);
        }
        return $value;
    }

    /**
     * Set an option to a defined value.
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
        return \update_option($setting, $value);
    }

    public function save()
    {
        $value = isset($_POST[$this->slug]) ? $_POST[$this->slug] : null;

        switch ($this->type) {
            case 'checkbox':
                self::set($this->slug, $value == 'on' ? true : false);
                break;

            case 'password':
                // Only save password if a value was provided (don't overwrite with empty)
                if (!empty($value)) {
                    self::set($this->slug, $value, \true);
                }
                break;

            case 'advanced':
                // Save all child settings
                if (!empty($this->children)) {
                    foreach ($this->children as $child) {
                        $child->save();
                    }
                }
                break;

            default:
                // Apply sanitize_callback if defined
                if ($this->sanitize_callback && is_callable($this->sanitize_callback)) {
                    $value = call_user_func($this->sanitize_callback, $value);
                }
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
        $this->render_unbound($value, $this->slug, $this->slug);
    }
    /**
     * Create a textarea.
     */
    public function init_textarea()
    {
        $value = self::get($this->slug);
        $this->render_unbound($value, $this->slug, $this->slug);
    }

    /**
     * Create a checkbox input.
     */
    public function init_checkbox()
    {
        $value = self::get($this->slug);
        $this->render_unbound($value, $this->slug, $this->slug);
    }

    /**
     * Create a select input.
     */
    public function init_select()
    {
        $value = self::get($this->slug);
        $this->render_unbound($value, $this->slug, $this->slug);
    }

    /**
     * Create a radio input. If there are more than one option, it will create a field set.
     */
    public function init_radio()
    {
        $value = self::get($this->slug);
        $this->render_unbound($value, $this->slug, $this->slug);
    }

    /**
     * Create a hidden input field.
     */
    public function init_hidden()
    {
        $value = self::get($this->slug);
        $this->render_unbound($value, $this->slug, $this->slug);
    }

    /**
     * Render the setting without binding to an option value.
     *
     * @param mixed       $value Optional value override.
     * @param string|null $name  Optional field name override.
     * @param string|null $id    Optional field id override.
     */
    public function render_unbound($value = null, $name = null, $id = null)
    {
        $field_name = $name ?? $this->name;
        $field_id   = $id ?? $field_name;

        // Wrap field in container with conditions data if applicable.
        if ($this->has_conditions()) {
            echo '<div class="wps-field-wrapper" data-field="' . \esc_attr($field_name) . '" data-conditions="' . \esc_attr($this->get_conditions_json()) . '">';
        }

        $this->render_with_value($field_name, $field_id, $value);

        if ($this->has_conditions()) {
            echo '</div>';
        }
    }

    /**
     * Sanitize a value using the setting's sanitize callback (if any).
     *
     * @param mixed $value Raw value.
     * @return mixed
     */
    public function sanitize_value($value)
    {
        if ($this->sanitize_callback && is_callable($this->sanitize_callback)) {
            return call_user_func($this->sanitize_callback, $value);
        }
        return $value;
    }

    /**
     * Check if this field has conditional visibility rules.
     *
     * @return bool
     */
    public function has_conditions()
    {
        return !empty($this->conditions);
    }

    /**
     * Get the conditions as a JSON-encoded string for use in data attributes.
     *
     * @return string JSON-encoded conditions or empty string if none.
     */
    public function get_conditions_json()
    {
        if (empty($this->conditions)) {
            return '';
        }
        return \wp_json_encode($this->conditions);
    }

    /**
     * Render a field using the provided value.
     *
     * @param string $name  Field name.
     * @param string $id    Field id.
     * @param mixed  $value Field value.
     */
    protected function render_with_value($name, $id, $value)
    {
        switch ($this->type) {
            case 'textarea':
                $this->render_textarea_value($name, $id, $value);
                break;
            case 'checkbox':
                $this->render_checkbox_value($name, $id, $value);
                break;
            case 'select':
                $this->render_select_value($name, $id, $value);
                break;
            case 'radio':
                $this->render_radio_value($name, $id, $value);
                break;
            case 'sortable':
                $this->render_sortable_value($name, $id, $value);
                break;
            case 'table':
                $this->render_table_value($name, $id, $value);
                break;
            case 'hidden':
                $this->render_hidden_value($name, $id, $value);
                break;
            case 'advanced':
                $this->init_advanced();
                break;
            default:
                $this->render_text_value($name, $id, $value);
                break;
        }
    }

    protected function render_text_value($name, $id, $value)
    {
        // Safety check: if value is an array, convert to empty string
        if (is_array($value)) {
            $value = '';
        }

        $has_existing_value = !empty($value);
        if (!$value) {
            $value = $this->default_value;
        }

        // For password fields, don't pre-fill the value for security reasons
        if ('password' === $this->type) {
            $placeholder = $has_existing_value ? 'placeholder="Value saved. Not displayed for security."' : '';
            $value = '';
        } else {
            $placeholder = '';
        }

        $atts = '';
        if ($this->width) {
            $atts .= ' style="width:' . $this->width . ';"';
        }
        if ($this->required && !$has_existing_value) {
            $atts .= ' required';
        }
        if ($placeholder) {
            $atts .= ' ' . $placeholder;
        }

        echo \wp_kses(sprintf('<input type="%s" name="%s" id="%s" value="%s"%s>', $this->type, $name, $id, $value, $atts), self::$allowed_html);
        if ('password' === $this->type) {
            echo \wp_kses('<button type="button" class="button wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="Show password"><span class="text">Show</span></button>', self::$allowed_html);
        }
        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    protected function render_textarea_value($name, $id, $value)
    {
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

        echo \wp_kses(sprintf('<textarea name="%s" id="%s"%s>%s</textarea>', $name, $id, $atts, $value), self::$allowed_html);
        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    protected function render_checkbox_value($name, $id, $value)
    {
        $checked = !empty($value) && $value !== '0' && $value !== 0 && $value !== false;
        $atts = ' ' . \checked($checked, true, false);
        if ($this->required) {
            $atts .= ' required';
        }
        // Hidden field ensures unchecked boxes send a value (0)
        echo \wp_kses(sprintf('<input type="hidden" name="%s" value="0">', $name), self::$allowed_html);
        echo \wp_kses(sprintf('<input id="%s" type="checkbox" name="%s" value="on"%s>', $id, $name, $atts), self::$allowed_html);
        if ($this->description) {
            echo \wp_kses(sprintf('<label for="%s" class="description" style="vertical-align:middle;margin-left:1em;">%s</label>', $id, $this->description), self::$allowed_html);
        }
    }

    protected function render_select_value($name, $id, $value)
    {
        if (!$value) {
            $value = $this->default_value;
        }
        $options = $this->resolve_options();
        echo sprintf('<select name="%s" id="%s">', $name, $id);
        foreach ($options as $option => $label) {
            $atts = ' ' . \selected($value, $option, false);
            if ($this->required) {
                $atts .= ' required';
            }
            echo sprintf('<option value="%s"%s>%s</option>', $option, $atts, $label);
        }
        echo '</select>';
        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    protected function render_radio_value($name, $id, $value)
    {
        if (!$value) {
            $value = $this->default_value;
        }

        $options = $this->resolve_options();
        $option_count = count($options);

        if ($option_count > 1) {
            echo '<fieldset>';
        }

        foreach ($options as $option => $label) {
            $atts = ' ' . \checked($value, $option, false);
            if ($this->required) {
                $atts .= ' required';
            }
            echo sprintf('<label><input type="radio" name="%s" value="%s"%s>%s</label><br>', $name, $option, $atts, $label);
        }

        if ($option_count > 1) {
            echo '</fieldset>';
        }

        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    protected function render_hidden_value($name, $id, $value)
    {
        // Safety check: if value is an array, convert to empty string
        if (is_array($value)) {
            $value = '';
        }

        if (!$value && $this->default_value) {
            $value = $this->default_value;
        }

        // Output hidden field with no visible markup
        echo \wp_kses(sprintf('<input type="hidden" name="%s" id="%s" value="%s">', $name, $id, \esc_attr($value)), self::$allowed_html);
    }

    protected function render_sortable_value($name, $id, $value)
    {
        $options = $this->resolve_options();
        if (empty($options)) {
            return;
        }

        if (!is_array($value)) {
            $value = array();
        }
        if (empty($value) && is_array($this->default_value)) {
            $value = $this->default_value;
        }

        $ordered_keys = $this->normalize_sortable_value($value, $options);
        $count = count($ordered_keys);

        echo \wp_kses(
            sprintf('<ul class="wps-sortable-list" data-field="%s">', \esc_attr($id)),
            self::$allowed_html
        );

        // Get item metadata (badges, classes, etc.).
        $item_meta = $this->args['item_meta'] ?? array();

        foreach ($ordered_keys as $index => $key) {
            $label = $options[$key] ?? $key;
            $order_id = $id . '_order_' . $index;
            $label_text = \sprintf('%s %s', $this->title, $label);

            // Build item classes.
            $item_classes = array('wps-sortable-item');
            if (isset($item_meta[$key]['class'])) {
                $item_classes[] = \esc_attr($item_meta[$key]['class']);
            }
            $item_class_attr = implode(' ', $item_classes);

            echo \wp_kses(
                sprintf('<li class="%s" data-key="%s">', $item_class_attr, \esc_attr($key)),
                self::$allowed_html
            );
            echo \wp_kses('<span class="wps-sortable-handle dashicons dashicons-menu" aria-hidden="true"></span>', self::$allowed_html);
            echo \wp_kses(sprintf('<span class="wps-sortable-label">%s</span>', \esc_html($label)), self::$allowed_html);

            // Render badge if provided.
            if (isset($item_meta[$key]['badge'])) {
                $badge_class = 'wps-sortable-badge';
                if (isset($item_meta[$key]['badge_class'])) {
                    $badge_class .= ' ' . \esc_attr($item_meta[$key]['badge_class']);
                }
                echo \wp_kses(
                    sprintf('<span class="%s">%s</span>', $badge_class, \esc_html($item_meta[$key]['badge'])),
                    self::$allowed_html
                );
            }

            echo \wp_kses(
                sprintf('<label class="screen-reader-text" for="%s">%s</label>', \esc_attr($order_id), \esc_html($label_text)),
                self::$allowed_html
            );
            echo \wp_kses(
                sprintf(
                    '<input type="number" class="wps-sortable-order" id="%s" min="1" max="%s" value="%s" inputmode="numeric">',
                    \esc_attr($order_id),
                    \esc_attr($count),
                    \esc_attr($index + 1)
                ),
                self::$allowed_html
            );
            echo \wp_kses(
                sprintf('<input type="hidden" name="%s[]" value="%s">', \esc_attr($name), \esc_attr($key)),
                self::$allowed_html
            );
            echo '</li>';
        }

        echo '</ul>';

        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }
    }

    /**
     * Render a table field (embeds a WP_Settings_Table).
     *
     * @param string $name  Field name (unused for tables).
     * @param string $id    Field id (unused for tables).
     * @param mixed  $value Field value (unused for tables).
     */
    protected function render_table_value($name, $id, $value)
    {
        // Get the table instance from args.
        $table = $this->args['table'] ?? null;

        if (!$table || !($table instanceof \BGoewert\WP_Settings\WP_Settings_Table)) {
            echo \wp_kses('<p class="description" style="color: red;">Error: Invalid table configuration.</p>', self::$allowed_html);
            return;
        }

        // Extract text_domain and tab from the current context if available.
        // These are typically set by the parent WP_Settings class during rendering.
        $text_domain = static::$text_domain ?? '';
        $tab = $this->page ?? '';

        // Strip prefix from tab if it exists (e.g., 'prefix_tab' -> 'tab').
        if ($text_domain && strpos($tab, $text_domain . '_') === 0) {
            $tab = substr($tab, strlen($text_domain) + 1);
        }

        // Render description before table if provided.
        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }

        // Render the table.
        $table->render($text_domain, $tab);
    }

    protected function resolve_options()
    {
        $options = $this->args['options'] ?? $this->options ?? array();

        if (is_callable($options)) {
            $options = call_user_func($options);
        }

        return is_array($options) ? $options : array();
    }

    protected function normalize_sortable_value($value, $options)
    {
        $ordered = array();
        $option_keys = array_map('strval', array_keys($options));

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $item = (string) $item;
                if (in_array($item, $option_keys, true) && !in_array($item, $ordered, true)) {
                    $ordered[] = $item;
                }
            }
        }

        foreach ($option_keys as $key) {
            if (!in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    /**
     * Create an advanced collapsible field with child settings.
     */
    public function init_advanced()
    {
        // Create collapsible details section
        // Note: We don't render hidden fields for children because the visible inputs
        // inside the details section are the actual form inputs that WordPress will save

        // Check if collapsed parameter is set (defaults to true).
        $collapsed = $this->args['collapsed'] ?? true;
        $open_attr = $collapsed ? '' : ' open';

        echo '<details style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;"' . $open_attr . '>';
        echo '<summary style="cursor: pointer; font-weight: 600; font-size: 14px;">' . \esc_html($this->title) . ' (click to expand)</summary>';
        echo '<div style="margin-top: 15px; padding-left: 10px;">';

        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }

        // Render each child setting as a visible control
        foreach ($this->children as $child) {
            echo '<p>';

            // Render based on child type
            switch ($child->type) {
                case 'checkbox':
                    // Use child's get method
                    $value = boolval($child::get($child->slug));
                    // Hidden field ensures unchecked boxes send a value (0)
                    echo sprintf('<input type="hidden" name="%s" value="0">', \esc_attr($child->slug));
                    echo sprintf(
                        '<label><input type="checkbox" name="%s" id="%s" value="on" %s /> <strong>%s</strong></label>',
                        \esc_attr($child->slug),
                        \esc_attr($child->slug),
                        \checked($value, true, false),
                        \esc_html($child->title)
                    );
                    if ($child->description) {
                        echo sprintf('<p class="description" style="margin: 0 0 15px 25px;">%s</p>', \esc_html($child->description));
                    }
                    break;

                case 'text':
                case 'email':
                case 'url':
                case 'number':
                    $value = self::get($child->slug, $child->default_value);
                    echo '<p><strong>' . \esc_html($child->title) . '</strong></p>';
                    $atts = '';
                    if ($child->width) {
                        $atts .= ' style="width:' . $child->width . ';"';
                    }
                    if ($child->required) {
                        $atts .= ' required';
                    }
                    echo \wp_kses(sprintf('<input type="%s" name="%s" id="%s" value="%s"%s>', $child->type, $child->slug, $child->slug, \esc_attr($value), $atts), self::$allowed_html);
                    if ($child->description) {
                        echo \wp_kses(sprintf('<p class="description">%s</p>', $child->description), self::$allowed_html);
                    }
                    break;

                case 'textarea':
                    $value = self::get($child->slug, $child->default_value);
                    echo '<p><strong>' . \esc_html($child->title) . '</strong></p>';
                    $atts = '';
                    if ($child->width) {
                        $atts .= ' style="width:' . $child->width . ';"';
                    }
                    if ($child->required) {
                        $atts .= ' required';
                    }
                    if (isset($child->args['rows'])) {
                        $atts .= ' rows="' . $child->args['rows'] . '"';
                    }
                    echo \wp_kses(sprintf('<textarea name="%s" id="%s"%s>%s</textarea>', $child->slug, $child->slug, $atts, \esc_textarea($value)), self::$allowed_html);
                    if ($child->description) {
                        echo \wp_kses(sprintf('<p class="description">%s</p>', $child->description), self::$allowed_html);
                    }
                    break;

                case 'select':
                    $value = self::get($child->slug, $child->default_value);
                    echo '<p><strong>' . \esc_html($child->title) . '</strong></p>';
                    echo sprintf('<select name="%s" id="%s">', $child->slug, $child->slug);
                    foreach ($child->args['options'] as $option => $label) {
                        echo sprintf('<option value="%s"%s>%s</option>', \esc_attr($option), \selected($value, $option, false), \esc_html($label));
                    }
                    echo '</select>';
                    if ($child->description) {
                        echo \wp_kses(sprintf('<p class="description">%s</p>', $child->description), self::$allowed_html);
                    }
                    break;
            }

            echo '</p>';
        }

        echo '</div></details>';
    }

    /**
     * Create a fieldset grouping with child settings.
     */
    public function init_fieldset()
    {
        // Create fieldset grouping
        echo '<fieldset style="border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">';
        echo '<legend style="padding: 0 10px; font-weight: 600;">' . \esc_html($this->title) . '</legend>';

        if ($this->description) {
            echo \wp_kses(sprintf('<p class="description">%s</p>', $this->description), self::$allowed_html);
        }

        // Render each child setting as a table row
        echo '<table class="form-table" role="presentation">';
        foreach ($this->children as $child) {
            echo '<tr>';
            echo '<th scope="row"><label for="' . \esc_attr($child->slug) . '">' . \esc_html($child->title) . '</label></th>';
            echo '<td>';

            // Render the child field input
            $value = self::get($child->slug, $child->default_value);

            switch ($child->type) {
                case 'checkbox':
                    echo sprintf('<input type="hidden" name="%s" value="0">', \esc_attr($child->slug));
                    echo sprintf(
                        '<label><input type="checkbox" name="%s" id="%s" value="on" %s /> %s</label>',
                        \esc_attr($child->slug),
                        \esc_attr($child->slug),
                        \checked($value, true, false),
                        \esc_html($child->title)
                    );
                    break;

                case 'text':
                case 'email':
                case 'url':
                case 'number':
                    $atts = ' style="width:' . ($child->width ?: '400px') . ';"';
                    if ($child->required) {
                        $atts .= ' required';
                    }
                    echo \wp_kses(sprintf('<input type="%s" name="%s" id="%s" value="%s"%s>', $child->type, $child->slug, $child->slug, \esc_attr($value), $atts), self::$allowed_html);
                    break;

                case 'textarea':
                    $atts = ' style="width:' . ($child->width ?: '400px') . ';"';
                    if ($child->required) {
                        $atts .= ' required';
                    }
                    if (isset($child->args['rows'])) {
                        $atts .= ' rows="' . $child->args['rows'] . '"';
                    }
                    echo \wp_kses(sprintf('<textarea name="%s" id="%s"%s>%s</textarea>', $child->slug, $child->slug, $atts, \esc_textarea($value)), self::$allowed_html);
                    break;

                case 'select':
                    echo sprintf('<select name="%s" id="%s">', $child->slug, $child->slug);
                    foreach ($child->args['options'] as $option => $label) {
                        echo sprintf('<option value="%s"%s>%s</option>', \esc_attr($option), \selected($value, $option, false), \esc_html($label));
                    }
                    echo '</select>';
                    break;
            }

            if ($child->description) {
                echo \wp_kses(sprintf('<p class="description">%s</p>', $child->description), self::$allowed_html);
            }

            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';

        echo '</fieldset>';
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

        $crypt = new WP_Setting_Encryption(
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
        $crypt = new WP_Setting_Encryption(
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

    /**
     * Validates if a value is a valid URL.
     *
     * @param string $value The value to validate.
     *
     * @return bool True if valid URL, false otherwise.
     */
    public static function is_valid_url($value)
    {
        if (empty($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validates if a value is a valid email address.
     *
     * @param string $value The value to validate.
     *
     * @return bool True if valid email, false otherwise.
     */
    public static function is_valid_email($value)
    {
        if (empty($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates if a value is not empty.
     *
     * @param mixed $value The value to validate.
     *
     * @return bool True if not empty, false otherwise.
     */
    public static function is_not_empty($value)
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return !empty($value);
    }

    /**
     * Sanitizes and validates a URL.
     *
     * @param string $value The URL to sanitize.
     *
     * @return string|false Sanitized URL or false if invalid.
     */
    public static function sanitize_url($value)
    {
        if (empty($value)) {
            return '';
        }

        $sanitized = \esc_url_raw($value);

        if (self::is_valid_url($sanitized)) {
            return $sanitized;
        }

        return false;
    }

    /**
     * Sanitizes and validates an email address.
     *
     * @param string $value The email to sanitize.
     *
     * @return string|false Sanitized email or false if invalid.
     */
    public static function sanitize_email($value)
    {
        if (empty($value)) {
            return '';
        }

        $sanitized = \sanitize_email($value);

        if (self::is_valid_email($sanitized)) {
            return $sanitized;
        }

        return false;
    }

    /**
     * Sanitizes text input.
     *
     * @param string $value The text to sanitize.
     *
     * @return string Sanitized text.
     */
    public static function sanitize_text($value)
    {
        return \sanitize_text_field($value);
    }
}
