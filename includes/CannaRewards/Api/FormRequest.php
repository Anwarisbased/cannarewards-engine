<?php
namespace CannaRewards\Api;

use CannaRewards\Api\Exceptions\ValidationException;
use Valitron\Validator;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Base class for all API form request validation.
 */
abstract class FormRequest {
    protected Validator $validator;
    protected array $validated_data = [];

    /**
     * Define the validation rules for the request.
     *
     * @return array
     */
    abstract protected function rules(): array;

    public function __construct(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        if (empty($data)) {
            $data = $request->get_body_params();
        }

        // Initialize Valitron with the data
        $this->validator = new Validator($data);
        
        // Apply rules manually to avoid issues with rule registration
        $rules = $this->rules();
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                if (is_array($rule)) {
                    // Rule with parameters like ['minLength', 8]
                    $ruleName = $rule[0];
                    $params = array_slice($rule, 1);
                    $this->validator->rule($ruleName, $field, ...$params);
                } else {
                    // Simple rule like 'required' or 'email'
                    $this->validator->rule($rule, $field);
                }
            }
        }

        if (!$this->validator->validate()) {
            throw new ValidationException($this->validator->errors());
        }

        $this->validated_data = $this->validator->data();
    }

    /**
     * Get the validated data from the request.
     *
     * @return array
     */
    public function validated(): array {
        return $this->validated_data;
    }
}