<?php
namespace CannaRewards\Includes;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Event Broadcaster
 *
 * A simple implementation of the Observer pattern for event-driven architecture.
 * Allows services to be decoupled by communicating through broadcasted events
 * instead of direct method calls.
 */
class Event {
    /**
     * A static array to hold all the registered event listeners.
     *
     * @var array
     */
    private static $listeners = [];

    /**
     * Registers a listener for a specific event.
     *
     * @param string   $event_name The name of the event to listen for (e.g., 'product_scanned').
     * @param callable $callback   The function or method to execute when the event is broadcast.
     * @param int      $priority   The priority of the listener (lower numbers run first).
     * @return void
     */
    public static function listen( string $event_name, callable $callback, int $priority = 10 ) {
        self::$listeners[ $event_name ][ $priority ][] = $callback;
    }

    /**
     * Broadcasts an event to all registered listeners.
     *
     * @param string $event_name The name of the event to broadcast.
     * @param array  $payload    The data to pass to the event listeners.
     * @return void
     */
    public static function broadcast( string $event_name, array $payload = [] ) {
        if ( ! isset( self::$listeners[ $event_name ] ) ) {
            return; // No listeners for this event.
        }

        // Sort listeners by priority to ensure a predictable execution order.
        ksort( self::$listeners[ $event_name ] );

        foreach ( self::$listeners[ $event_name ] as $priority_group ) {
            foreach ( $priority_group as $callback ) {
                call_user_func( $callback, $payload );
            }
        }
    }
}