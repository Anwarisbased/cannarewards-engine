<?php
namespace CannaRewards\Api\Responders;

class NotFoundResponder implements ResponderInterface {
    public function __construct(private string $message = 'Resource not found.') {}
    
    public function toWpRestResponse(): \WP_REST_Response {
        $error = new \WP_Error('not_found', $this->message, ['status' => 404]);
        return rest_ensure_response($error);
    }
}