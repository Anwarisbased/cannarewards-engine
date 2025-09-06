<?php
namespace CannaRewards\Includes;
class Event {
    private static $listeners = [];
    public static function listen( string $event_name, callable $callback, int $priority = 10 ) {
        self::$listeners[ $event_name ][ $priority ][] = $callback;
    }
    public static function broadcast( string $event_name, array $payload = [] ) {
        $listeners_for_event = self::$listeners[ $event_name ] ?? [];
        if ( empty( $listeners_for_event ) ) return;
        ksort( $listeners_for_event );
        foreach ( $listeners_for_event as $priority_group ) {
            foreach ( $priority_group as $callback ) {
                call_user_func( $callback, $payload, $event_name );
            }
        }
    }
}