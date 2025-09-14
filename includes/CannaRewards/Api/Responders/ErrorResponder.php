<?php
namespace CannaRewards\Api\Responders;

class ErrorResponder implements ResponderInterface {
    public function __construct(private string $message, private string $code, private int $status = 500) {}
    
    public function toWpRestResponse(): \WP_REST_Response {
        $error = new \WP_Error($this->code, $this->message, ['status' => $this->status]);
        return rest_ensure_response($error);
    }
}