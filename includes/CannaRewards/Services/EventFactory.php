<?php
namespace CannaRewards\Services;

use JsonSchema\Validator;
use Exception;

final class EventFactory {
    private ContextBuilderService $contextBuilder;
    private Validator $validator;
    private string $schemaPath;

    public function __construct(ContextBuilderService $contextBuilder) {
        $this->contextBuilder = $contextBuilder;
        $this->validator = new Validator();
        $this->schemaPath = CANNA_PLUGIN_DIR . 'schemas/';
    }

    /**
     * Creates a fully-formed and validated 'product_scanned' event payload.
     */
    public function createProductScannedEvent(int $userId, \WP_Post $productPost, bool $isFirstScan): array {
        $payload = $this->contextBuilder->build_event_context($userId, $productPost);
        $payload['is_first_scan'] = $isFirstScan;

        $this->validate('events/product_scanned.v1', $payload);

        return $payload;
    }

    /**
     * Validates a payload against a given JSON schema.
     * Throws a fatal exception if validation fails.
     */
    private function validate(string $schemaName, array $payload): void {
        $schemaFilePath = $this->schemaPath . $schemaName . '.json';
        if (!file_exists($schemaFilePath)) {
            throw new Exception("Schema file not found: {$schemaFilePath}");
        }

        $schema = (object)['$ref' => 'file://' . $schemaFilePath];
        $dataToValidate = json_decode(json_encode($payload)); // Deep convert to object

        $this->validator->validate($dataToValidate, $schema);

        if (!$this->validator->isValid()) {
            $errors = [];
            foreach ($this->validator->getErrors() as $error) {
                $errors[] = "[{$error['property']}] {$error['message']}";
            }
            // This is a developer error and should be fatal.
            throw new Exception("Event Validation Failed for {$schemaName}: " . implode(', ', $errors));
        }
    }
}