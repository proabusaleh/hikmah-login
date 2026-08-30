<?php
/**
 * Dashboard Overview Tab
 *
 * Displays a summary dashboard with widgets showing
 * key user information at a glance.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Overview {

    /**
     * Registered overview widgets
     *
     * @var array
     */
    private $widgets = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_default_widgets();

        // Hook into dashboard
        add_action('hikmah_dashboard_register_tabs', [$this, 'override_overview_callback']);
    }

    /**
     * Override the overview tab callback
     *
     * @param Hikmah_Dashboard $dashboard Dashboard instance.
     * @return void
     */
    public function override_overview_callback($dashboard) {
        // Already registered in core, just update callback
        // The core class will call render_tab_overview which we hook into
    }

    /**
     * Register default overview widgets
     *
     * @return void
     */
    private function register_default_widgets() {
        // Account Summary Widget
        $this->register_widget(new Hikmah_Widget_Account_Summary());

        // Quick Actions Widget
        $this->register_widget(new Hikmah_Widget_Quick_Actions());

        // Security Status Widget
        $this->register_widget(new Hikmah_Widget_Security_Status());

        // Recent Login Activity Widget
        $this->register_widget(new Hikmah_Widget_Recent_Activity());

        /**
         * Action to register custom overview widgets
         *
         * @param Hikmah_Tab_Overview $overview Overview tab instance.
         */
        do_action('hikmah_register_overview_widgets', $this);
    }

    /**
     * Register a widget
     *
     * @param Hikmah_Dashboard_Widget $widget Widget instance.
     * @return void
     */
    public function register_widget(Hikmah_Dashboard_Widget $widget) {
        $this->widgets[$widget->get_id()] = $widget;
    }

    /**
     * Render the overview tab content
     *
     * @return void
     */
    public function render() {
        $widgets = $this->get_sorted_widgets();

        $main_widgets    = [];
        $sidebar_widgets = [];
        $full_widgets    = [];

        foreach ($widgets as $widget) {
            switch ($widget->get_location()) {
                case 'sidebar':
                    $sidebar_widgets[] = $widget;
                    break;
                case 'full':
                    $full_widgets[] = $widget;
                    break;
                default:
                    $main_widgets[] = $widget;
                    break;
            }
        }
        ?>

        <div class="hikmah-dashboard__overview">

            <?php if (!empty($full_widgets)) : ?>
                <div class="hikmah-dashboard__overview-full">
                    <?php foreach ($full_widgets as $widget) : ?>
                        <?php $widget->render(); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="hikmah-dashboard__overview-grid">

                <div class="hikmah-dashboard__overview-main">
                    <?php foreach ($main_widgets as $widget) : ?>
                        <?php $widget->render(); ?>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($sidebar_widgets)) : ?>
                    <div class="hikmah-dashboard__overview-sidebar">
                        <?php foreach ($sidebar_widgets as $widget) : ?>
                            <?php $widget->render(); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <?php
    }

    /**
     * Get widgets sorted by priority
     *
     * @return Hikmah_Dashboard_Widget[]
     */
    private function get_sorted_widgets() {
        $widgets = $this->widgets;

        uasort($widgets, function ($a, $b) {
            return $a->get_priority() - $b->get_priority();
        });

        return $widgets;
    }
}
