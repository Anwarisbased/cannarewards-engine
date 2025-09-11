<?php
namespace CannaRewards\Includes;

/**
 * Defines the contract for an application-wide event bus.
 * This allows for decoupling services from the specific eventing implementation.
 */
interface EventBusInterface {
    /**
     * Registers a callback to be executed when a specific event is broadcast.
     *
     * @param string $event_name The name of the event to listen for.
     * @param callable $callback The function or method to execute.
     * @param int $priority Lower numbers are executed first.
     */
    public function listen(string $event_name, $callback, int $priority = 10);

    /**
     * Broadcasts an event to all registered listeners.
     *
     * @param string $event_name The name of the event to broadcast.
     * @param array $payload The data to pass to the listeners.
     */
    public function broadcast(string $event_name, array $payload = []);
}