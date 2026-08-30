<?php
/**
 * Dashboard Widget Base Class
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Hikmah_Dashboard_Widget {

    /**
     * Widget unique ID
     *
     * @var string
     */
    protected $id = '';

    /**
     * Widget title
     *
     * @var string
     */
    protected $title = '';

    /**
     * Widget priority (display order)
     *
     * @var int
     */
    protected $priority = 50;

    /**
     * Widget location: 'main', 'sidebar', 'full'
     *
     * @var string
     */
    protected $location = 'main';

    /**
     * Required capability to view widget
     *
     * @var string
     */
    protected $capability = 'read';

    /**
     * CSS classes for the widget wrapper
     *
     * @var array
     */
    protected $classes = [];

    /**
     * Constructor
     *
     * @param array $args Widget configuration.
     */
    public function __construct($args = []) {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get widget ID
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function get_title() {
        return $this->title;
    }

    /**
     * Get widget priority
     *
     * @return int
     */
    public function get_priority() {
        return $this->priority;
    }

    /**
     * Get widget location
     *
     * @return string
     */
    public function get_location() {
        return $this->location;
    }

    /**
     * Check if current user can view this widget
     *
     * @return bool
     */
    public function user_can_view() {
        return current_user_can($this->capability);
    }

    /**
     * Render the complete widget with wrapper
     *
     * @return void
     */
    public function render() {
        if (!$this->user_can_view()) {
            return;
        }

        $classes = array_merge(
            ['hikmah-dashboard__widget', 'hikmah-dashboard__widget--' . $this->id],
            $this->classes
        );

        /**
         * Filter widget wrapper classes
         *
         * @param array  $classes   CSS classes.
         * @param string $widget_id Widget ID.
         */
        $classes = apply_filters('hikmah_dashboard_widget_classes', $classes, $this->id);
        ?>

        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>"
             id="hikmah-widget-<?php echo esc_attr($this->id); ?>">

            <?php if (!empty($this->title)) : ?>
                <div class="hikmah-dashboard__widget-header">
                    <h4 class="hikmah-dashboard__widget-title">
                        <?php echo esc_html($this->title); ?>
                    </h4>
                    <?php $this->render_widget_actions(); ?>
                </div>
            <?php endif; ?>

            <div class="hikmah-dashboard__widget-body">
                <?php $this->render_content(); ?>
            </div>

        </div>

        <?php
    }

    /**
     * Render widget header actions (override in child classes)
     *
     * @return void
     */
    protected function render_widget_actions() {
        // Override in child classes to add action buttons
    }

    /**
     * Render widget content (must be implemented by child classes)
     *
     * @return void
     */
    abstract protected function render_content();
}
