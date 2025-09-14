<?php
namespace CannaRewards\Api\Responders;

class SuccessResponder implements ResponderInterface {
    public function __construct(private array $data, private int $statusCode = 200) {}
    
    public function toWpRestResponse(): \WP_REST_Response {
        return new \WP_REST_Response(['success' => true, 'data' => $this->data], $this->statusCode);
    }
}