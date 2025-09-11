<?php
namespace CannaRewards\Infrastructure;

use CannaRewards\Includes\EventBusInterface;

/**
 * A simple, in-memory event bus implementation that lasts for a single request.
 * Implements the EventBusInterface.
 */
final class WordPressEventBus implements EventBusInterface {
    private array $listeners = [];

    public function listen(string $event_name, $callback, int $priority = 10) {
        if (!is_callable($callback, false, $callable_name)) {
            $type = gettype($callback);
            $details = '';
            if (is_array($callback)) {
                $part1 = is_object($callback[0]) ? get_class($callback[0]) : (string)($callback[0] ?? 'NULL');
                $part2 = (string)($callback[1] ?? 'NULL');
                $details = "Array( {$part1}, {$part2} )";
            } else {
                $details = (string) $callback;
            }
            trigger_error(
                "EventBus::listen() was passed a non-callable {$type} for event '{$event_name}'. The invalid callback was: {$details}. The system interpreted it as '{$callable_name}'.",
                E_USER_ERROR
            );
            return;
        }
        $this->listeners[$event_name][$priority][] = $callback;
    }

    public function broadcast(string $event_name, array $payload = []) {
        $listeners_for_event = $this->listeners[$event_name] ?? [];
        if (empty($listeners_for_event)) return;
        
        ksort($listeners_for_event);
        
        foreach ($listeners_for_event as $priority_group) {
            foreach ($priority_group as $callback) {
                call_user_func($callback, $payload, $event_name);
            }
        }
    }
}