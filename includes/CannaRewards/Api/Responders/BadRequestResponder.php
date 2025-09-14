<?php
namespace CannaRewards\Api\Responders;

class BadRequestResponder implements ResponderInterface {
    public function __construct(private string $message = 'The request was malformed or is missing required parameters.') {}
    
    public function toWpRestResponse(): \WP_REST_Response {
        $error = new \WP_Error('bad_request', $this->message, ['status' => 400]);
        return rest_ensure_response($error);
    }
}