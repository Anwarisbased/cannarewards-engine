<?php
namespace CannaRewards\Api\Responders;

class ForbiddenResponder implements ResponderInterface {
    public function __construct(private string $message = 'You do not have permission to perform this action.') {}
    
    public function toWpRestResponse(): \WP_REST_Response {
        $error = new \WP_Error('forbidden', $this->message, ['status' => 403]);
        return rest_ensure_response($error);
    }
}