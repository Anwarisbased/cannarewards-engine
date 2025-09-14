<?php
namespace CannaRewards\Api\Responders;

interface ResponderInterface {
    public function toWpRestResponse(): \WP_REST_Response;
}