<?php
declare(strict_types=1);
namespace SharpAPI\Core\DTO;

use stdClass;

/**
 * Class SharpApiJob
 *
 * Represents a job retrieved from the SharpAPI, containing the job ID, type, status, and result.
 */
class SharpApiJob
{

    /**
     * SharpApiJob constructor.
     *
     * @param string $id The unique identifier for the job.
     * @param string $type The type of job being processed.
     * @param string $status The current status of the job.
     * @param ?stdClass $result The result of the job, if available.
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public ?stdClass $result
    )
    {
    }

    /**
     * Converts the SharpApiJob instance to an array for easier logging or serialization.
     *
     * @return array An associative array representation of the job.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'result' => $this->result,
        ];
    }

    /**
     * Returns the job result as a prettified JSON string.
     * @api
     * @return string|bool|null The JSON-encoded result, or null if no result.
     */
    public function getResultJson(): string|bool|null
    {
        return $this->result ? json_encode($this->result, JSON_PRETTY_PRINT) : null;
    }

    /**
     * Returns the job result as a PHP associative array.
     * @api
     * @return array|null The result as an associative array, or null if no result.
     */
    public function getResultArray(): ?array
    {
        return $this->result ? (array) $this->result : null;
    }

    /**
     * Returns the job result as a PHP stdClass object.
     * @api
     * @return stdClass|null The result as an object, or null if no result.
     */
    public function getResultObject(): ?stdClass
    {
        return $this->result;
    }
}
